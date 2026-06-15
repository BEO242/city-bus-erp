<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;

final class KpiService
{
    public function snapshotDay(string $date): array
    {
        // Global
        $g = $this->computeForScope('global', null, $date);
        $this->upsert($date, 'global', null, $g);

        // Par ligne
        $lines = Database::select("SELECT id FROM bus_lines WHERE deleted_at IS NULL");
        foreach ($lines as $l) {
            $r = $this->computeForScope('line', (int)$l['id'], $date);
            $this->upsert($date, 'line', (int)$l['id'], $r);
        }
        return $g;
    }

    private function computeForScope(string $type, ?int $id, string $date): array
    {
        $where = ["t.trip_date = ?"];
        $params = [$date];
        if ($type === 'line' && $id) { $where[] = 't.line_id = ?'; $params[] = $id; }
        if ($type === 'agency' && $id) { $where[] = 't.agency_id = ?'; $params[] = $id; }

        $whereSql = implode(' AND ', $where);

        $cap = (int)Database::scalar("SELECT COALESCE(SUM(b.seats),0) FROM trips t JOIN buses b ON b.id = t.bus_id WHERE $whereSql", $params);
        $sold = (int)Database::scalar("SELECT COALESCE(SUM((SELECT COUNT(*) FROM tickets WHERE trip_id = t.id AND status NOT IN ('annule','rembourse'))),0) FROM trips t WHERE $whereSql", $params);
        $loadFactor = $cap > 0 ? round($sold / $cap * 100, 2) : 0;

        $totalTrips = (int)Database::scalar("SELECT COUNT(*) FROM trips t WHERE $whereSql", $params);
        $onTime = (int)Database::scalar(
            "SELECT COUNT(*) FROM trips t WHERE $whereSql AND ABS(COALESCE(t.delay_minutes,0)) <= 15
             AND t.status IN ('arrive','cloture','termine','terminé')",
            $params
        );
        $closedTrips = (int)Database::scalar(
            "SELECT COUNT(*) FROM trips t WHERE $whereSql AND t.status IN ('arrive','cloture','termine','terminé')",
            $params
        );
        $otp = $closedTrips > 0 ? round($onTime / $closedTrips * 100, 2) : 0;

        $cancelled = (int)Database::scalar(
            "SELECT COUNT(*) FROM trips t WHERE $whereSql AND t.status IN ('annule','annulé')",
            $params
        );
        $cancelRate = $totalTrips > 0 ? round($cancelled / $totalTrips * 100, 2) : 0;

        $revenue = (int)Database::scalar(
            "SELECT COALESCE(SUM(p.revenue_total),0) FROM trip_pnl p JOIN trips t ON t.id = p.trip_id WHERE $whereSql",
            $params
        );
        $cost = (int)Database::scalar(
            "SELECT COALESCE(SUM(p.cost_total),0) FROM trip_pnl p JOIN trips t ON t.id = p.trip_id WHERE $whereSql",
            $params
        );
        $marginPct = $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 2) : 0;

        $avgYield = $sold > 0 ? (int)round($revenue / $sold) : 0;

        // RASK / CASK : Revenue/Cost per Available Seat-Km
        $askRow = Database::selectOne(
            "SELECT COALESCE(SUM(b.seats * l.distance_km),0) AS ask FROM trips t
             JOIN buses b ON b.id = t.bus_id JOIN bus_lines l ON l.id = t.line_id WHERE $whereSql", $params
        );
        $ask = (int)($askRow['ask'] ?? 0);
        $rask = $ask > 0 ? round($revenue / $ask, 2) : 0;
        $cask = $ask > 0 ? round($cost / $ask, 2) : 0;

        return [
            'load_factor_pct'    => $loadFactor,
            'otp_pct'            => $otp,
            'cancellation_rate'  => $cancelRate,
            'no_show_rate'       => 0, // TODO: needs no_show flag
            'avg_yield_per_seat' => $avgYield,
            'revenue_total'      => $revenue,
            'cost_total'         => $cost,
            'margin_pct'         => $marginPct,
            'rask'               => $rask,
            'cask'               => $cask,
        ];
    }

    private function upsert(string $date, string $type, ?int $id, array $data): void
    {
        $payload = array_merge([
            'snapshot_date' => $date,
            'scope_type'    => $type,
            'scope_id'      => $id,
        ], $data);
        $cols = array_keys($payload);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $updateClause = implode(',', array_map(fn($c) => "$c=VALUES($c)", $cols));
        Database::execute(
            "INSERT INTO kpi_snapshots (" . implode(',', $cols) . ", computed_at)
             VALUES ($placeholders, NOW())
             ON DUPLICATE KEY UPDATE $updateClause, computed_at=NOW()",
            array_values($payload)
        );
    }

    public function timeline(string $type, ?int $id, string $from, string $to): array
    {
        $where = ['scope_type = ?', 'snapshot_date BETWEEN ? AND ?'];
        $params = [$type, $from, $to];
        if ($id !== null) { $where[] = 'scope_id = ?'; $params[] = $id; }
        return Database::select(
            "SELECT * FROM kpi_snapshots WHERE " . implode(' AND ', $where) . " ORDER BY snapshot_date",
            $params
        );
    }

    /**
     * Latest KPI snapshot per bus line (for per-line table in dashboard).
     */
    public function perLineLatest(): array
    {
        return Database::select(
            "SELECT k.*, l.code AS line_code, l.name AS line_name
             FROM kpi_snapshots k
             JOIN bus_lines l ON l.id = k.scope_id
             WHERE k.scope_type = 'line'
               AND k.snapshot_date = (
                 SELECT MAX(snapshot_date) FROM kpi_snapshots k2
                 WHERE k2.scope_type = 'line' AND k2.scope_id = k.scope_id
               )
             ORDER BY k.revenue_total DESC
             LIMIT 20"
        );
    }
}
