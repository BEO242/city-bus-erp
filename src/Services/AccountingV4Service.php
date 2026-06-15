<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Core\Auth;
use CityBus\Models\AuditLog;

/**
 * V4 — Compta SYSCOHADA via tables accounting_entries / accounting_lines.
 * Coexiste avec AccountingService legacy.
 */
final class AccountingV4Service
{
    public function postInvoice(int $invoiceId): int
    {
        $inv = Database::selectOne("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
        if (!$inv) throw new \RuntimeException("Invoice introuvable");

        $entryId = Database::insert('accounting_entries', [
            'journal'   => 'sales',
            'entry_date'=> date('Y-m-d', strtotime($inv['issued_at'])),
            'label'     => 'Facture ' . $inv['invoice_number'],
            'reference' => $inv['invoice_number'],
            'pnr_id'    => $inv['pnr_id'],
            'invoice_id'=> $invoiceId,
        ]);

        $clientAcc = $inv['corporate_id'] ? '411300' : '411100';
        Database::insert('accounting_lines', [
            'entry_id' => $entryId, 'account_code' => $clientAcc,
            'label' => 'Vente ' . $inv['invoice_number'],
            'debit' => (int)$inv['total_ttc'], 'sequence' => 1,
        ]);

        $lines = Database::select(
            "SELECT line_type, SUM(amount_ht) AS ht FROM invoice_lines WHERE invoice_id = ? GROUP BY line_type",
            [$invoiceId]
        );
        $seq = 2;
        foreach ($lines as $l) {
            $accCode = match($l['line_type']) {
                'baggage' => '706200',
                'parcel'  => '706300',
                default   => '706100',
            };
            Database::insert('accounting_lines', [
                'entry_id' => $entryId, 'account_code' => $accCode,
                'label' => 'Recettes ' . $l['line_type'],
                'credit' => (int)$l['ht'], 'sequence' => $seq++,
            ]);
        }
        if ((int)$inv['total_tax'] > 0) {
            Database::insert('accounting_lines', [
                'entry_id' => $entryId, 'account_code' => '44561',
                'label' => 'TVA collectée', 'credit' => (int)$inv['total_tax'], 'sequence' => $seq,
            ]);
        }

        if (Setting::getBool('finance.auto_post', false)) $this->post($entryId);
        return $entryId;
    }

    public function postPayment(int $invoiceId, int $amount, string $method = 'cash'): int
    {
        $inv = Database::selectOne("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
        if (!$inv) throw new \RuntimeException("Invoice introuvable");

        $entryId = Database::insert('accounting_entries', [
            'journal'    => $method === 'cash' ? 'cash' : 'bank',
            'entry_date' => date('Y-m-d'),
            'label'      => 'Encaissement ' . $inv['invoice_number'],
            'reference'  => $inv['invoice_number'],
            'pnr_id'     => $inv['pnr_id'],
            'invoice_id' => $invoiceId,
        ]);

        $cashAcc = match($method) {
            'mobile_money' => '531200',
            'cash'         => '531100',
            'card', 'bank' => '512100',
            default        => '531100',
        };
        $clientAcc = $inv['corporate_id'] ? '411300' : '411100';

        Database::insert('accounting_lines', ['entry_id'=>$entryId,'account_code'=>$cashAcc,'label'=>"Encaissement $method",'debit'=>$amount,'sequence'=>1]);
        Database::insert('accounting_lines', ['entry_id'=>$entryId,'account_code'=>$clientAcc,'label'=>'Solde client','credit'=>$amount,'sequence'=>2]);

        if (Setting::getBool('finance.auto_post', false)) $this->post($entryId);
        return $entryId;
    }

    public function postFuelExpense(int $tripId, int $amount, string $reference = ''): int
    {
        $entryId = Database::insert('accounting_entries', [
            'journal' => 'purchases', 'entry_date' => date('Y-m-d'),
            'label' => "Carburant voyage #$tripId",
            'reference' => $reference ?: "FUEL-$tripId", 'trip_id' => $tripId,
        ]);
        Database::insert('accounting_lines', ['entry_id'=>$entryId,'account_code'=>'606100','label'=>'Carburant','debit'=>$amount,'sequence'=>1,'cost_center'=>"trip_$tripId"]);
        Database::insert('accounting_lines', ['entry_id'=>$entryId,'account_code'=>'531100','label'=>'Caisse','credit'=>$amount,'sequence'=>2]);
        return $entryId;
    }

    public function post(int $entryId): void
    {
        Database::update('accounting_entries',
            ['posted_at' => date('Y-m-d H:i:s'), 'posted_by' => Auth::id()],
            'id = ?', [$entryId]
        );
        AuditLog::record('accounting.post', 'entry', $entryId, []);
    }

    public function journal(string $journalCode, string $from, string $to): array
    {
        return Database::select(
            "SELECT e.*, COUNT(l.id) AS line_count, COALESCE(SUM(l.debit),0) AS sum_debit, COALESCE(SUM(l.credit),0) AS sum_credit
             FROM accounting_entries e
             LEFT JOIN accounting_lines l ON l.entry_id = e.id
             WHERE e.journal = ? AND e.entry_date BETWEEN ? AND ?
             GROUP BY e.id ORDER BY e.entry_date ASC, e.id ASC",
            [$journalCode, $from, $to]
        );
    }

    public function generalLedger(string $accountCode, string $from, string $to): array
    {
        return Database::select(
            "SELECT e.entry_date, e.label, e.reference, l.debit, l.credit
             FROM accounting_lines l
             JOIN accounting_entries e ON e.id = l.entry_id
             WHERE l.account_code = ? AND e.entry_date BETWEEN ? AND ?
             ORDER BY e.entry_date ASC, e.id ASC",
            [$accountCode, $from, $to]
        );
    }

    public function balance(string $from, string $to): array
    {
        return Database::select(
            "SELECT l.account_code, c.label,
                    COALESCE(SUM(l.debit),0) AS total_debit,
                    COALESCE(SUM(l.credit),0) AS total_credit,
                    COALESCE(SUM(l.debit),0) - COALESCE(SUM(l.credit),0) AS solde
             FROM accounting_lines l
             JOIN accounting_entries e ON e.id = l.entry_id
             LEFT JOIN chart_of_accounts c ON c.code = l.account_code
             WHERE e.entry_date BETWEEN ? AND ?
             GROUP BY l.account_code, c.label ORDER BY l.account_code",
            [$from, $to]
        );
    }

    public function exportCsv(string $from, string $to): string
    {
        $rows = Database::select(
            "SELECT e.entry_date, e.journal, e.reference, e.label,
                    l.account_code, l.cost_center, l.debit, l.credit
             FROM accounting_lines l
             JOIN accounting_entries e ON e.id = l.entry_id
             WHERE e.entry_date BETWEEN ? AND ? AND e.posted_at IS NOT NULL
             ORDER BY e.entry_date, e.id, l.sequence",
            [$from, $to]
        );
        $csv = "Date;Journal;Reference;Libelle;Compte;Analytique;Debit;Credit\n";
        foreach ($rows as $r) {
            $csv .= sprintf("%s;%s;%s;%s;%s;%s;%d;%d\n",
                $r['entry_date'], $r['journal'], $r['reference'] ?: '',
                str_replace(';', ',', (string)($r['label'] ?? '')),
                $r['account_code'], $r['cost_center'] ?: '',
                (int)$r['debit'], (int)$r['credit']);
        }
        return $csv;
    }
}
