<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Auth;
use CityBus\Models\AuditLog;

final class ReconciliationService
{
    public function newBatch(int $providerId, string $periodStart, string $periodEnd, ?string $statementFile = null): int
    {
        $expected = (int)Database::scalar(
            "SELECT COALESCE(SUM(amount),0) FROM payments
             WHERE provider_id = ? AND DATE(paid_at) BETWEEN ? AND ? AND provider_status = 'confirmed'",
            [$providerId, $periodStart, $periodEnd]
        );
        return Database::insert('reconciliation_batches', [
            'provider_id'    => $providerId,
            'period_start'   => $periodStart,
            'period_end'     => $periodEnd,
            'statement_file' => $statementFile,
            'expected_total' => $expected,
            'created_by'     => Auth::id(),
        ]);
    }

    /**
     * Match transactions externes vs payments locaux par provider_transaction_id.
     * Format CSV attendu : tx_id,amount,date,...
     */
    public function processCsv(int $batchId, string $csvContent): array
    {
        $rows = array_map('str_getcsv', explode("\n", trim($csvContent)));
        $header = array_map('trim', array_shift($rows));
        $idxTx = array_search('tx_id', $header) ?: 0;
        $idxAmt = array_search('amount', $header) ?: 1;
        $idxDate = array_search('date', $header) ?: 2;

        $matched = 0; $unmatched = 0; $matchedTotal = 0; $unmatchedTotal = 0;

        foreach ($rows as $row) {
            if (count($row) < 2) continue;
            $extTxId = trim($row[$idxTx]);
            $extAmt = (int)$row[$idxAmt];
            $extDate = $row[$idxDate] ?? null;

            $local = Database::selectOne(
                "SELECT id, amount FROM payments WHERE provider_transaction_id = ? AND reconciled_at IS NULL",
                [$extTxId]
            );
            if ($local && (int)$local['amount'] === $extAmt) {
                Database::update('payments', [
                    'reconciled_at' => date('Y-m-d H:i:s'),
                    'reconciliation_batch_id' => $batchId,
                ], 'id = ?', [$local['id']]);
                $matched++; $matchedTotal += $extAmt;
            } else {
                Database::insert('reconciliation_unmatched', [
                    'batch_id'        => $batchId,
                    'external_tx_id'  => $extTxId,
                    'external_amount' => $extAmt,
                    'external_date'   => $extDate,
                    'raw_data'        => json_encode($row),
                ]);
                $unmatched++; $unmatchedTotal += $extAmt;
            }
        }

        $status = $unmatched === 0 ? 'complete' : 'partial';
        Database::update('reconciliation_batches', [
            'matched_total'   => $matchedTotal,
            'unmatched_total' => $unmatchedTotal,
            'matched_count'   => $matched,
            'unmatched_count' => $unmatched,
            'status'          => $status,
            'completed_at'    => $status === 'complete' ? date('Y-m-d H:i:s') : null,
        ], 'id = ?', [$batchId]);

        AuditLog::record('reconcile.process', 'batch', $batchId, [
            'matched' => $matched, 'unmatched' => $unmatched,
        ]);

        return ['matched' => $matched, 'unmatched' => $unmatched, 'status' => $status];
    }

    public function listBatches(int $limit = 30): array
    {
        return Database::select(
            "SELECT b.*, p.code AS provider_code, p.label AS provider_label
             FROM reconciliation_batches b
             JOIN payment_providers p ON p.id = b.provider_id
             ORDER BY b.id DESC LIMIT $limit"
        );
    }
}
