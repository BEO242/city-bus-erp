<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Core\Cache;

/**
 * V4 — TVA/Tax extension : code-based rates (tax_rates table V4).
 * Coexiste avec TaxService legacy (id-based).
 */
final class TaxV4Service
{
    public function rateByCode(string $code): ?array
    {
        return Cache::remember("taxrate.code.$code", 600, fn() =>
            Database::selectOne(
                "SELECT * FROM tax_rates WHERE code = ? AND is_active = 1
                 AND (valid_from IS NULL OR valid_from <= CURDATE())
                 AND (valid_until IS NULL OR valid_until >= CURDATE())", [$code]
            )
        );
    }

    public function defaultRate(): ?array
    {
        return $this->rateByCode(Setting::get('finance.tax_rate_default', 'TVA_CG_18'));
    }

    public function listAll(bool $activeOnly = true): array
    {
        return Database::select(
            "SELECT * FROM tax_rates" . ($activeOnly ? " WHERE is_active = 1" : "") . " ORDER BY type, code"
        );
    }

    public function breakdownFromTtc(int $ttc, string $taxCode = 'TVA_CG_18'): array
    {
        $rate = $this->rateByCode($taxCode);
        $pct = $rate ? (float)$rate['rate_percent'] : 0;
        $ht  = (int)round($ttc / (1 + $pct / 100));
        return [
            'amount_ht'   => $ht,
            'tax_amount'  => $ttc - $ht,
            'amount_ttc'  => $ttc,
            'tax_pct'     => $pct,
            'tax_code'    => $taxCode,
            'tax_rate_id' => $rate ? (int)$rate['id'] : null,
        ];
    }

    public function breakdownFromHt(int $ht, string $taxCode = 'TVA_CG_18'): array
    {
        $rate = $this->rateByCode($taxCode);
        $pct = $rate ? (float)$rate['rate_percent'] : 0;
        $tax = (int)round($ht * $pct / 100);
        return [
            'amount_ht'   => $ht,
            'tax_amount'  => $tax,
            'amount_ttc'  => $ht + $tax,
            'tax_pct'     => $pct,
            'tax_code'    => $taxCode,
            'tax_rate_id' => $rate ? (int)$rate['id'] : null,
        ];
    }

    public function vatDeclaration(string $monthYM): array
    {
        $byRate = Database::select(
            "SELECT il.tax_pct, COALESCE(SUM(il.amount_ht),0) AS ht, COALESCE(SUM(il.tax_amount),0) AS tax
             FROM invoice_lines il
             JOIN invoices i ON i.id = il.invoice_id
             WHERE DATE_FORMAT(i.issued_at, '%Y-%m') = ?
               AND i.status NOT IN ('void','cancelled') AND i.type IN ('sale','corporate')
             GROUP BY il.tax_pct ORDER BY il.tax_pct DESC",
            [$monthYM]
        );
        $totHt  = array_sum(array_column($byRate, 'ht'));
        $totTax = array_sum(array_column($byRate, 'tax'));
        return [
            'period'    => $monthYM,
            'total_ht'  => (int)$totHt,
            'total_tax' => (int)$totTax,
            'total_ttc' => (int)$totHt + (int)$totTax,
            'by_rate'   => $byRate,
        ];
    }
}
