<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;

/**
 * Calcul du compte de résultat (P&L) d'un voyage.
 *
 * - Recettes : tickets + bagages + cargo (HT + TVA)
 * - Coûts directs : carburant alloué, primes équipage, péages
 * - Coûts indirects : amortissement, assurance, maintenance, structure (% recettes)
 *
 * Le snapshot est immuable une fois calculé pour un voyage clôturé : la
 * recomputation force une réécriture (utile pour ajustements rétroactifs).
 */
final class TripPnlService
{
    /** Calcule et persiste le P&L d'un voyage. Retourne le snapshot. */
    public function compute(int $tripId, ?int $userId = null, bool $force = false): array
    {
        $trip = Database::selectOne(
            "SELECT tr.*, l.distance_km AS line_distance_km
             FROM trips tr
             LEFT JOIN bus_lines l ON l.id = tr.line_id
             WHERE tr.id = ?",
            [$tripId]
        );
        if (!$trip) throw new \RuntimeException("Voyage #$tripId introuvable.");

        // Si déjà calculé et non forcé, retourner snapshot existant
        $existing = Database::selectOne("SELECT * FROM trip_pnl WHERE trip_id = ?", [$tripId]);
        if ($existing && !$force) return $existing;

        $rules = $this->rules();
        $distance = (float)($trip['distance_km'] ?? $trip['line_distance_km'] ?? 0);

        // ─── Recettes ───────────────────────────────────────────────
        $tickets = Database::selectOne(
            "SELECT
                COUNT(*) AS pax,
                COALESCE(SUM(price_fcfa), 0) AS total,
                COALESCE(SUM(price_ht_fcfa), 0) AS ht,
                COALESCE(SUM(tax_amount_fcfa), 0) AS tax
             FROM tickets
             WHERE trip_id = ? AND deleted_at IS NULL
               AND status IN ('emis','controle','utilise','embarque')",
            [$tripId]
        ) ?: ['pax'=>0,'total'=>0,'ht'=>0,'tax'=>0];

        $baggage = Database::selectOne(
            "SELECT COALESCE(SUM(total_price_fcfa), 0) AS total,
                    COALESCE(SUM(price_ht_fcfa), 0) AS ht,
                    COALESCE(SUM(tax_amount_fcfa), 0) AS tax
             FROM baggage_tickets
             WHERE trip_id = ? AND deleted_at IS NULL",
            [$tripId]
        ) ?: ['total'=>0,'ht'=>0,'tax'=>0];

        $cargo = Database::selectOne(
            "SELECT COUNT(*) AS cnt,
                    COALESCE(SUM(total_price_fcfa), 0) AS total,
                    COALESCE(SUM(base_price_fcfa + insurance_fee_fcfa), 0) AS ht,
                    COALESCE(SUM(tax_amount_fcfa), 0) AS tax
             FROM parcels
             WHERE trip_id = ? AND deleted_at IS NULL",
            [$tripId]
        ) ?: ['cnt'=>0,'total'=>0,'ht'=>0,'tax'=>0];

        $revenueTotal = (int)$tickets['total'] + (int)$baggage['total'] + (int)$cargo['total'];
        $taxTotal     = (int)$tickets['tax']   + (int)$baggage['tax']   + (int)$cargo['tax'];
        $revenueHt    = $revenueTotal - $taxTotal;

        // ─── Coûts directs ──────────────────────────────────────────
        $fuel = Database::selectOne(
            "SELECT COALESCE(SUM(total_cost), 0) AS total,
                    COALESCE(SUM(liters), 0) AS liters
             FROM fuel_logs
             WHERE bus_id = ?
               AND DATE(logged_at) = DATE(?)",
            [(int)$trip['bus_id'], $trip['trip_date']]
        ) ?: ['total'=>0,'liters'=>0];

        // Si pas de plein le jour même, on alloue au prorata du km / consommation moyenne
        $costFuel = (int)$fuel['total'];
        if ($costFuel === 0 && $distance > 0) {
            $avgConsumption = (float)\CityBus\Core\Setting::getString('flotte.avg_consumption_l_per_100km', '35');
            $avgFuelPrice   = \CityBus\Core\Setting::getInt('flotte.avg_fuel_price_per_liter', 700);
            $estLiters      = $distance * $avgConsumption / 100;
            $costFuel       = (int)round($estLiters * $avgFuelPrice);
            $fuel['liters'] = $estLiters;
        }

        $crewBonus = $this->ruleValue($rules, 'crew_bonus_per_trip', 'per_trip', 1, 1);
        $tolls     = 0;
        $misc      = 0;
        $costDirectTotal = $costFuel + $crewBonus + $tolls + $misc;

        // ─── Coûts indirects ────────────────────────────────────────
        $depreciation = $this->ruleValue($rules, 'depreciation_per_km', 'per_km', $distance, $revenueTotal);
        $insurance    = $this->ruleValue($rules, 'insurance_per_km',    'per_km', $distance, $revenueTotal);
        $maintenance  = $this->ruleValue($rules, 'maintenance_per_km',  'per_km', $distance, $revenueTotal);
        $overhead     = $this->ruleValue($rules, 'overhead_pct',  'percent_revenue', $distance, $revenueTotal);
        $costIndirectTotal = $depreciation + $insurance + $maintenance + $overhead;

        // ─── Marges ─────────────────────────────────────────────────
        $marginContribution = $revenueTotal - $costDirectTotal;
        $marginNet          = $revenueTotal - $costDirectTotal - $costIndirectTotal;

        // ─── Volumétrie ─────────────────────────────────────────────
        $busCapacity = (int)(Database::selectOne(
            "SELECT seats FROM buses WHERE id = ?", [(int)$trip['bus_id']]
        )['seats'] ?? 0);
        $loadFactor = $busCapacity > 0 ? round(((int)$tickets['pax'] / $busCapacity) * 100, 2) : null;

        // ─── Persiste ──────────────────────────────────────────────
        $columns = [
            'trip_id'             => $tripId,
            'line_id'             => $trip['line_id'] ?? null,
            'bus_id'              => $trip['bus_id'] ?? null,
            'revenue_tickets'     => (int)$tickets['total'],
            'revenue_baggage'     => (int)$baggage['total'],
            'revenue_cargo'       => (int)$cargo['total'],
            'revenue_total'       => $revenueTotal,
            'tax_total'           => $taxTotal,
            'revenue_ht'          => $revenueHt,
            'cost_fuel'           => $costFuel,
            'cost_crew_bonus'     => $crewBonus,
            'cost_tolls'          => $tolls,
            'cost_misc'           => $misc,
            'cost_direct_total'   => $costDirectTotal,
            'cost_depreciation'   => $depreciation,
            'cost_insurance'      => $insurance,
            'cost_maintenance'    => $maintenance,
            'cost_overhead'       => $overhead,
            'cost_indirect_total' => $costIndirectTotal,
            'margin_contribution' => $marginContribution,
            'margin_net'          => $marginNet,
            'distance_km'         => $distance ?: null,
            'passengers_count'    => (int)$tickets['pax'],
            'parcels_count'       => (int)$cargo['cnt'],
            'fuel_liters'         => $fuel['liters'] ?: null,
            'load_factor_pct'     => $loadFactor,
            'computed_by'         => $userId,
        ];

        if ($existing) {
            $set    = implode(',', array_map(fn($k) => "`$k` = ?", array_keys($columns)));
            Database::execute(
                "UPDATE trip_pnl SET $set, computed_at = NOW() WHERE trip_id = ?",
                [...array_values($columns), $tripId]
            );
        } else {
            $cols   = implode(',', array_map(fn($k) => "`$k`", array_keys($columns)));
            $place  = implode(',', array_fill(0, count($columns), '?'));
            Database::insert(
                "INSERT INTO trip_pnl ($cols) VALUES ($place)",
                array_values($columns)
            );
        }

        return Database::selectOne("SELECT * FROM trip_pnl WHERE trip_id = ?", [$tripId]) ?? $columns;
    }

    public function getByTrip(int $tripId): ?array
    {
        return Database::selectOne("SELECT * FROM trip_pnl WHERE trip_id = ?", [$tripId]);
    }

    /** Top N voyages par marge nette descendante / ascendante. */
    public function ranking(string $from, string $to, int $limit = 20, bool $worst = false): array
    {
        $order = $worst ? 'ASC' : 'DESC';
        return Database::select(
            "SELECT p.*, tr.trip_code, tr.trip_date,
                    l.code AS line_code, l.name AS line_name,
                    b.code AS bus_code
             FROM trip_pnl p
             JOIN trips tr ON tr.id = p.trip_id
             LEFT JOIN bus_lines l ON l.id = p.line_id
             LEFT JOIN buses b ON b.id = p.bus_id
             WHERE tr.trip_date BETWEEN ? AND ?
             ORDER BY p.margin_net $order
             LIMIT $limit",
            [$from, $to]
        );
    }

    /** Agrégation par ligne sur la période. */
    public function byLine(string $from, string $to): array
    {
        return Database::select(
            "SELECT l.id AS line_id, l.code, l.name,
                    COUNT(p.id) AS trips_count,
                    COALESCE(SUM(p.revenue_total),0)       AS revenue,
                    COALESCE(SUM(p.cost_direct_total),0)   AS cost_direct,
                    COALESCE(SUM(p.cost_indirect_total),0) AS cost_indirect,
                    COALESCE(SUM(p.margin_contribution),0) AS margin_contrib,
                    COALESCE(SUM(p.margin_net),0)          AS margin_net,
                    COALESCE(AVG(p.load_factor_pct),0)     AS avg_load
             FROM bus_lines l
             LEFT JOIN trip_pnl p ON p.line_id = l.id
             LEFT JOIN trips tr ON tr.id = p.trip_id AND tr.trip_date BETWEEN ? AND ?
             WHERE l.deleted_at IS NULL
             GROUP BY l.id, l.code, l.name
             ORDER BY margin_net DESC",
            [$from, $to]
        );
    }

    // ─── Privé ──────────────────────────────────────────────────────

    private function rules(): array
    {
        $rows = Database::select("SELECT * FROM cost_allocation_rules WHERE is_active = 1");
        $out  = [];
        foreach ($rows as $r) $out[$r['rule_key']] = $r;
        return $out;
    }

    private function ruleValue(array $rules, string $key, string $defaultMethod, float $distance, int $revenue): int
    {
        if (!isset($rules[$key])) return 0;
        $rule = $rules[$key];
        $value = (float)$rule['value_numeric'];
        return match ($rule['method']) {
            'per_km'          => (int)round($value * $distance),
            'per_trip'        => (int)round($value),
            'percent_revenue' => (int)round($revenue * $value / 100),
            'fixed'           => (int)round($value),
            default           => 0,
        };
    }
}
