<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;

/**
 * Calcul de TVA, génération de numéros de facture et déclaration fiscale.
 */
final class TaxService
{
    /**
     * Calcule la ventilation HT / TVA / TTC.
     *
     * @param int $amount Le montant donné (HT ou TTC selon $isTtc).
     * @param int|null $rateId ID du taux ; si null, prend le taux par défaut actif.
     * @param bool|null $isTtc Force HT/TTC ; si null, lit le paramètre tax.prices_include_tax.
     * @return array{ht:int, tax:int, ttc:int, rate_id:int|null, rate_percent:float}
     */
    public function breakdown(int $amount, ?int $rateId = null, ?bool $isTtc = null): array
    {
        $rate = $this->resolveRate($rateId);
        $pct  = $rate ? (float)$rate['rate_percent'] : 0.0;
        $isTtc ??= Setting::getBool('tax.prices_include_tax', true);

        if ($pct <= 0) {
            return [
                'ht'           => $amount,
                'tax'          => 0,
                'ttc'          => $amount,
                'rate_id'      => $rate ? (int)$rate['id'] : null,
                'rate_percent' => $pct,
            ];
        }

        if ($isTtc) {
            $ht  = (int)round($amount / (1 + $pct / 100));
            $tax = $amount - $ht;
            $ttc = $amount;
        } else {
            $ht  = $amount;
            $tax = (int)round($amount * $pct / 100);
            $ttc = $ht + $tax;
        }

        return [
            'ht'           => $ht,
            'tax'          => $tax,
            'ttc'          => $ttc,
            'rate_id'      => $rate ? (int)$rate['id'] : null,
            'rate_percent' => $pct,
        ];
    }

    /**
     * Génère un numéro de facture séquentiel pour l'année courante (par agence si fournie).
     */
    public function nextInvoiceNumber(?int $agencyId = null): string
    {
        $year   = (int)date('Y');
        $prefix = Setting::getString('tax.invoice_prefix', 'FCT');

        // Verrou applicatif simple par UPDATE conditionnel
        Database::execute(
            "INSERT INTO invoice_sequences (`year`, agency_id, next_number)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE next_number = next_number + 1",
            [$year, $agencyId]
        );
        $row = Database::selectOne(
            "SELECT next_number FROM invoice_sequences WHERE `year` = ? AND " .
            ($agencyId ? "agency_id = ?" : "agency_id IS NULL"),
            $agencyId ? [$year, $agencyId] : [$year]
        );
        $seq = (int)($row['next_number'] ?? 1);

        return sprintf('%s-%d-%06d', $prefix, $year, $seq);
    }

    /** Récupère le taux par défaut. */
    public function defaultRate(): ?array
    {
        return $this->resolveRate(null);
    }

    /** Liste tous les taux actifs (pour selects). */
    public function activeRates(): array
    {
        return Database::select(
            "SELECT * FROM tax_rates
              WHERE is_active = 1
                AND (valid_from  IS NULL OR valid_from  <= CURDATE())
                AND (valid_until IS NULL OR valid_until >= CURDATE())
              ORDER BY rate_percent"
        );
    }

    /**
     * Déclaration TVA d'une période — agrège ventes/cargo/bagages par taux.
     */
    public function vatReport(string $from, string $to): array
    {
        $tickets = Database::select(
            "SELECT COALESCE(tax_rate_percent, 0) AS rate,
                    COALESCE(SUM(price_ht_fcfa),    SUM(price_fcfa)) AS ht,
                    COALESCE(SUM(tax_amount_fcfa),  0)               AS tax,
                    COUNT(*) AS count_docs
               FROM tickets
              WHERE deleted_at IS NULL
                AND DATE(created_at) BETWEEN ? AND ?
                AND status IN ('emis','controle','utilise')
              GROUP BY rate ORDER BY rate",
            [$from, $to]
        );
        $bags = Database::select(
            "SELECT COALESCE(tax_rate_percent, 0) AS rate,
                    COALESCE(SUM(price_ht_fcfa),    SUM(total_price_fcfa)) AS ht,
                    COALESCE(SUM(tax_amount_fcfa),  0) AS tax,
                    COUNT(*) AS count_docs
               FROM baggage_tickets
              WHERE deleted_at IS NULL
                AND DATE(created_at) BETWEEN ? AND ?
              GROUP BY rate ORDER BY rate",
            [$from, $to]
        );
        $cargo = Database::select(
            "SELECT 0 AS rate,
                    COALESCE(SUM(base_price_fcfa + insurance_fee_fcfa), 0) AS ht,
                    COALESCE(SUM(tax_amount_fcfa), 0) AS tax,
                    COUNT(*) AS count_docs
               FROM parcels
              WHERE deleted_at IS NULL
                AND DATE(deposited_at) BETWEEN ? AND ?",
            [$from, $to]
        );

        return [
            'tickets' => $tickets,
            'baggage' => $bags,
            'cargo'   => $cargo,
            'period'  => ['from' => $from, 'to' => $to],
        ];
    }

    private function resolveRate(?int $rateId): ?array
    {
        if ($rateId) {
            return Database::selectOne("SELECT * FROM tax_rates WHERE id = ? AND is_active = 1", [$rateId]);
        }
        $defaultId = Setting::getInt('tax.default_rate_id', 0);
        if ($defaultId > 0) {
            $r = Database::selectOne("SELECT * FROM tax_rates WHERE id = ? AND is_active = 1", [$defaultId]);
            if ($r) return $r;
        }
        return Database::selectOne("SELECT * FROM tax_rates WHERE is_default = 1 AND is_active = 1 LIMIT 1");
    }
}
