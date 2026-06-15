<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Auth;
use CityBus\Models\AuditLog;

final class CorporateService
{
    public function checkCredit(int $corporateId, int $amount): bool
    {
        $c = Database::selectOne("SELECT credit_limit_fcfa, current_balance_fcfa FROM corporate_accounts WHERE id = ?", [$corporateId]);
        if (!$c) return false;
        return ((int)$c['current_balance_fcfa'] + $amount) <= (int)$c['credit_limit_fcfa'];
    }

    public function debit(int $corporateId, int $amount, string $reason = ''): void
    {
        Database::execute(
            "UPDATE corporate_accounts SET current_balance_fcfa = current_balance_fcfa + ? WHERE id = ?",
            [$amount, $corporateId]
        );
        AuditLog::record('corporate.debit', 'corporate', $corporateId, ['amount' => $amount, 'reason' => $reason]);
    }

    public function credit(int $corporateId, int $amount, string $reason = ''): void
    {
        Database::execute(
            "UPDATE corporate_accounts SET current_balance_fcfa = GREATEST(0, current_balance_fcfa - ?) WHERE id = ?",
            [$amount, $corporateId]
        );
        AuditLog::record('corporate.credit', 'corporate', $corporateId, ['amount' => $amount, 'reason' => $reason]);
    }

    public function generateMonthlyInvoice(int $corporateId, string $periodYM): int
    {
        [$year, $month] = explode('-', $periodYM);
        $from = date('Y-m-01', strtotime($periodYM . '-01'));
        $to   = date('Y-m-t', strtotime($from));

        $tickets = Database::select(
            "SELECT t.id, t.price, t.passenger_name, tr.trip_code, tr.trip_date
             FROM tickets t JOIN trips tr ON tr.id = t.trip_id
             WHERE t.corporate_id = ? AND tr.trip_date BETWEEN ? AND ?
               AND t.status NOT IN ('annule','rembourse')
             ORDER BY tr.trip_date",
            [$corporateId, $from, $to]
        );
        if (empty($tickets)) throw new \RuntimeException("Aucun ticket sur la période");

        $totalHt = 0;
        $lines = [];
        foreach ($tickets as $t) {
            $totalHt += (int)$t['price'];
            $lines[] = [
                'line_type'   => 'ticket',
                'description' => $t['trip_code'] . ' · ' . $t['passenger_name'] . ' (' . $t['trip_date'] . ')',
                'quantity'    => 1,
                'unit_price'  => (int)$t['price'],
                'is_ttc'      => true,
                'tax_code'    => 'TVA_CG_18',
            ];
        }

        $invoiceId = (new InvoiceService())->create([
            'type' => 'corporate',
            'corporate_id' => $corporateId,
            'issued_at'    => date('Y-m-d H:i:s'),
            'due_at'       => date('Y-m-d', strtotime("+30 days")),
            'notes'        => "Facturation période $periodYM · " . count($tickets) . " billets",
        ], $lines);

        AuditLog::record('corporate.monthly_invoice', 'corporate', $corporateId, ['invoice' => $invoiceId, 'period' => $periodYM]);
        return $invoiceId;
    }

    public function listContracts(int $corporateId): array
    {
        return Database::select(
            "SELECT * FROM corporate_contracts WHERE corporate_id = ? ORDER BY valid_from DESC", [$corporateId]
        );
    }

    public function createContract(int $corporateId, array $data): int
    {
        return Database::insert('corporate_contracts', [
            'corporate_id'    => $corporateId,
            'contract_number' => $data['contract_number'] ?? 'CT-' . date('Y') . '-' . substr(uniqid(), -6),
            'valid_from'      => $data['valid_from'],
            'valid_until'     => $data['valid_until'] ?? null,
            'discount_pct'    => $data['discount_pct'] ?? 0,
            'free_seats_per_year' => $data['free_seats_per_year'] ?? 0,
            'quota_seats_per_month' => $data['quota_seats_per_month'] ?? null,
            'auto_renew'      => $data['auto_renew'] ?? 0,
            'notes'           => $data['notes'] ?? null,
        ]);
    }
}
