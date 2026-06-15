<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Core\Auth;
use CityBus\Models\AuditLog;

final class InvoiceService
{
    private TaxV4Service $tax;
    private AccountingV4Service $acc;

    public function __construct() {
        $this->tax = new TaxV4Service();
        $this->acc = new AccountingV4Service();
    }

    public function create(array $header, array $lines): int
    {
        $number = $this->nextNumber();
        $totHt = 0; $totTax = 0; $totTtc = 0;
        $preparedLines = [];
        foreach ($lines as $i => $l) {
            $taxCode = $l['tax_code'] ?? Setting::get('finance.tax_rate_default', 'TVA_CG_18');
            $qty = max(1, (int)($l['quantity'] ?? 1));
            $isTtc = $l['is_ttc'] ?? true;
            $unit = (int)($l['unit_price'] ?? 0);
            $amount = $unit * $qty;
            $b = $isTtc
                ? $this->tax->breakdownFromTtc($amount, $taxCode)
                : $this->tax->breakdownFromHt($amount, $taxCode);
            $totHt  += $b['amount_ht'];
            $totTax += $b['tax_amount'];
            $totTtc += $b['amount_ttc'];
            $preparedLines[] = [
                'line_type'    => $l['line_type'] ?? 'ticket',
                'description'  => $l['description'] ?? '',
                'quantity'     => $qty,
                'unit_price_ht'=> (int)round($b['amount_ht'] / max(1,$qty)),
                'amount_ht'    => $b['amount_ht'],
                'tax_rate_id'  => $b['tax_rate_id'],
                'tax_pct'      => $b['tax_pct'],
                'tax_amount'   => $b['tax_amount'],
                'amount_ttc'   => $b['amount_ttc'],
                'sequence'     => $i + 1,
            ];
        }

        $id = Database::insert('invoices', [
            'invoice_number' => $number,
            'type'           => $header['type'] ?? 'sale',
            'customer_id'    => $header['customer_id'] ?? null,
            'corporate_id'   => $header['corporate_id'] ?? null,
            'pnr_id'         => $header['pnr_id'] ?? null,
            'issued_at'      => $header['issued_at'] ?? date('Y-m-d H:i:s'),
            'due_at'         => $header['due_at'] ?? null,
            'currency'       => Setting::get('finance.currency_default', 'XAF'),
            'total_ht'       => $totHt,
            'total_tax'      => $totTax,
            'total_ttc'      => $totTtc,
            'status'         => $header['status'] ?? 'issued',
            'created_by'     => Auth::id(),
            'notes'          => $header['notes'] ?? null,
        ]);

        foreach ($preparedLines as $pl) {
            $pl['invoice_id'] = $id;
            Database::insert('invoice_lines', $pl);
        }

        // Génère écritures comptables auto
        $this->acc->postInvoice($id);

        AuditLog::record('invoice.create', 'invoice', $id, ['number' => $number, 'total' => $totTtc]);
        return $id;
    }

    public function markPaid(int $invoiceId, ?int $amount = null): void
    {
        $inv = Database::selectOne("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
        if (!$inv) return;
        $paid = (int)$inv['paid_amount'] + (int)($amount ?? $inv['total_ttc']);
        $status = $paid >= (int)$inv['total_ttc'] ? 'paid' : 'partial';
        Database::update('invoices', [
            'paid_amount' => $paid,
            'status'      => $status,
            'paid_at'     => $status === 'paid' ? date('Y-m-d H:i:s') : null,
        ], 'id = ?', [$invoiceId]);
        AuditLog::record('invoice.payment', 'invoice', $invoiceId, ['amount' => $amount]);
    }

    public function void(int $invoiceId, string $reason): void
    {
        Database::update('invoices', ['status' => 'void'], 'id = ?', [$invoiceId]);
        AuditLog::record('invoice.void', 'invoice', $invoiceId, ['reason' => $reason]);
    }

    public function get(int $id): ?array
    {
        $i = Database::selectOne("SELECT * FROM invoices WHERE id = ?", [$id]);
        if (!$i) return null;
        $i['lines'] = Database::select("SELECT * FROM invoice_lines WHERE invoice_id = ? ORDER BY sequence", [$id]);
        return $i;
    }

    public function listRecent(int $limit = 100): array
    {
        return Database::select(
            "SELECT i.*, c.first_name, c.last_name, ca.company_name AS corporate_name
             FROM invoices i
             LEFT JOIN customers c ON c.id = i.customer_id
             LEFT JOIN corporate_accounts ca ON ca.id = i.corporate_id
             ORDER BY i.issued_at DESC LIMIT $limit"
        );
    }

    private function nextNumber(): string
    {
        $prefix = Setting::get('finance.invoice_prefix', 'FAC');
        $year = date('Y');
        $last = Database::scalar(
            "SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number,'-',-1) AS UNSIGNED))
             FROM invoices WHERE invoice_number LIKE ?", ["$prefix-$year-%"]
        );
        $seq = ((int)$last) + 1;
        return sprintf('%s-%s-%05d', $prefix, $year, $seq);
    }
}
