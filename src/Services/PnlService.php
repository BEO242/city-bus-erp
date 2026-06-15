<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;

/**
 * P&L analytique par voyage : revenus directs - coûts directs - quote-part fixe.
 */
final class PnlService
{
    public function recomputeForTrip(int $tripId): array
    {
        $trip = Database::selectOne("SELECT * FROM trips WHERE id = ?", [$tripId]);
        if (!$trip) throw new \RuntimeException("Voyage introuvable");

        // Revenus passagers (tickets)
        $rPax = (int)Database::scalar(
            "SELECT COALESCE(SUM(price),0) FROM tickets WHERE trip_id = ? AND status NOT IN ('annule','rembourse')",
            [$tripId]
        );
        // Revenus bagages
        $rBag = (int)Database::scalar(
            "SELECT COALESCE(SUM(price),0) FROM baggage_tickets WHERE trip_id = ? AND status NOT IN ('annule','rembourse')",
            [$tripId]
        );
        // Revenus colis (cargo)
        $rPar = (int)Database::scalar(
            "SELECT COALESCE(SUM(total_price),0) FROM parcels WHERE trip_id = ? AND status NOT IN ('cancelled','lost')",
            [$tripId]
        );
        // Costs : check trip_costs table if exists
        $costFuel = (int)Database::scalar(
            "SELECT COALESCE(SUM(amount),0) FROM trip_costs WHERE trip_id = ? AND cost_type IN ('carburant','fuel')",
            [$tripId]
        ) ?: 0;
        $costToll = (int)Database::scalar(
            "SELECT COALESCE(SUM(amount),0) FROM trip_costs WHERE trip_id = ? AND cost_type IN ('peage','toll')",
            [$tripId]
        ) ?: 0;
        $costMaint = (int)Database::scalar(
            "SELECT COALESCE(SUM(amount),0) FROM trip_costs WHERE trip_id = ? AND cost_type IN ('entretien','maintenance')",
            [$tripId]
        ) ?: 0;
        $costDriver = (int)Database::scalar(
            "SELECT COALESCE(SUM(amount),0) FROM trip_costs WHERE trip_id = ? AND cost_type IN ('prime','bonus','salary_bonus')",
            [$tripId]
        ) ?: 0;

        $revenue = $rPax + $rBag + $rPar;
        $directCosts = $costFuel + $costToll + $costMaint + $costDriver;
        $indirectPct = (int)Setting::get('finance.pnl.indirect_pct', 15);
        $indirect = (int)round($revenue * $indirectPct / 100);
        $totalCost = $directCosts + $indirect;
        $margin = $revenue - $totalCost;
        $marginPct = $revenue > 0 ? round($margin / $revenue * 100, 2) : 0;

        $paxCount = (int)Database::scalar(
            "SELECT COUNT(*) FROM tickets WHERE trip_id = ? AND status NOT IN ('annule','rembourse')",
            [$tripId]
        );

        $data = [
            'trip_id'           => $tripId,
            'revenue_pax'       => $rPax,
            'revenue_baggage'   => $rBag,
            'revenue_parcel'    => $rPar,
            'revenue_other'     => 0,
            'revenue_total'     => $revenue,
            'cost_fuel'         => $costFuel,
            'cost_toll'         => $costToll,
            'cost_driver_bonus' => $costDriver,
            'cost_maintenance'  => $costMaint,
            'cost_indirect_alloc'=> $indirect,
            'cost_total'        => $totalCost,
            'margin'            => $margin,
            'margin_pct'        => $marginPct,
            'pax_count'         => $paxCount,
            'cost_per_pax'      => $paxCount > 0 ? (int)round($totalCost / $paxCount) : 0,
            'revenue_per_pax'   => $paxCount > 0 ? (int)round($revenue / $paxCount) : 0,
        ];

        Database::execute(
            "INSERT INTO trip_pnl (trip_id, revenue_pax, revenue_baggage, revenue_parcel, revenue_other, revenue_total,
                cost_fuel, cost_toll, cost_driver_bonus, cost_maintenance, cost_indirect_alloc, cost_total,
                margin, margin_pct, pax_count, cost_per_pax, revenue_per_pax, computed_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())
             ON DUPLICATE KEY UPDATE
                revenue_pax=VALUES(revenue_pax), revenue_baggage=VALUES(revenue_baggage), revenue_parcel=VALUES(revenue_parcel),
                revenue_other=VALUES(revenue_other), revenue_total=VALUES(revenue_total),
                cost_fuel=VALUES(cost_fuel), cost_toll=VALUES(cost_toll), cost_driver_bonus=VALUES(cost_driver_bonus),
                cost_maintenance=VALUES(cost_maintenance), cost_indirect_alloc=VALUES(cost_indirect_alloc),
                cost_total=VALUES(cost_total), margin=VALUES(margin), margin_pct=VALUES(margin_pct),
                pax_count=VALUES(pax_count), cost_per_pax=VALUES(cost_per_pax), revenue_per_pax=VALUES(revenue_per_pax),
                computed_at=NOW()",
            array_values($data)
        );

        return $data;
    }

    public function recomputeBatch(string $from, string $to): int
    {
        $trips = Database::select(
            "SELECT id FROM trips WHERE trip_date BETWEEN ? AND ?",
            [$from, $to]
        );
        foreach ($trips as $t) {
            try { $this->recomputeForTrip((int)$t['id']); }
            catch (\Throwable $e) { /* skip */ }
        }
        return count($trips);
    }

    public function summaryByLine(string $from, string $to): array
    {
        return Database::select(
            "SELECT l.id, l.code, l.name,
                    COUNT(p.trip_id) AS trips,
                    COALESCE(SUM(p.revenue_total),0) AS revenue,
                    COALESCE(SUM(p.cost_total),0) AS cost,
                    COALESCE(SUM(p.margin),0) AS margin,
                    COALESCE(AVG(p.margin_pct),0) AS avg_margin_pct,
                    COALESCE(SUM(p.pax_count),0) AS pax
             FROM trip_pnl p
             JOIN trips t ON t.id = p.trip_id
             JOIN bus_lines l ON l.id = t.line_id
             WHERE t.trip_date BETWEEN ? AND ?
             GROUP BY l.id, l.code, l.name
             ORDER BY margin DESC",
            [$from, $to]
        );
    }

    public function summaryGlobal(string $from, string $to): array
    {
        $g = Database::selectOne(
            "SELECT
                COUNT(*) AS trips,
                COALESCE(SUM(p.revenue_total),0) AS revenue,
                COALESCE(SUM(p.cost_total),0) AS cost,
                COALESCE(SUM(p.margin),0) AS margin,
                COALESCE(SUM(p.pax_count),0) AS pax
             FROM trip_pnl p
             JOIN trips t ON t.id = p.trip_id
             WHERE t.trip_date BETWEEN ? AND ?",
            [$from, $to]
        );
        $g['margin_pct'] = ($g['revenue'] ?? 0) > 0 ? round($g['margin'] / $g['revenue'] * 100, 2) : 0;
        return $g;
    }
}
