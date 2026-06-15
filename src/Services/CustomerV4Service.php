<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;

/**
 * Extension V4 du CRM : segmentation RFM, fusion doublons, history, tier.
 * Coexiste avec CustomerService (legacy) qui gère find/create + counters.
 */
final class CustomerV4Service
{
    public function detail(int $id): ?array
    {
        $c = Database::selectOne("SELECT * FROM customers WHERE id = ?", [$id]);
        if (!$c) return null;
        $c['history'] = Database::select(
            "SELECT chs.*, t.trip_code, l.code AS line_code,
                    cd.name AS departure_city, ca.name AS arrival_city
             FROM customer_history_segments chs
             LEFT JOIN trips t ON t.id = chs.trip_id
             LEFT JOIN bus_lines l ON l.id = t.line_id
             LEFT JOIN cities cd ON cd.id = l.departure_city_id
             LEFT JOIN cities ca ON ca.id = l.arrival_city_id
             WHERE chs.customer_id = ? ORDER BY chs.flown_at DESC LIMIT 50", [$id]
        );
        $c['complaints'] = Database::select(
            "SELECT * FROM customer_complaints WHERE customer_id = ? ORDER BY opened_at DESC LIMIT 20", [$id]
        );
        $c['loyalty'] = Database::select(
            "SELECT * FROM loyalty_transactions WHERE customer_id = ? ORDER BY created_at DESC LIMIT 30", [$id]
        );
        return $c;
    }

    public function recordSegmentFlown(int $customerId, array $data): void
    {
        Database::insert('customer_history_segments', [
            'customer_id'   => $customerId,
            'pnr_id'        => $data['pnr_id'] ?? null,
            'segment_id'    => $data['segment_id'] ?? null,
            'ticket_id'     => $data['ticket_id'] ?? null,
            'trip_id'       => $data['trip_id'] ?? null,
            'flown_at'      => $data['flown_at'] ?? date('Y-m-d H:i:s'),
            'distance_km'   => $data['distance_km'] ?? 0,
            'revenue_fcfa'  => $data['revenue_fcfa'] ?? 0,
            'booking_class' => $data['booking_class'] ?? null,
            'points_earned' => $data['points_earned'] ?? 0,
        ]);
        Database::execute(
            "UPDATE customers
             SET total_segments = total_segments + 1,
                 total_distance_km = total_distance_km + ?
             WHERE id = ?",
            [(int)($data['distance_km'] ?? 0), $customerId]
        );
    }

    public function merge(int $keepId, int $mergeFromId): void
    {
        if ($keepId === $mergeFromId) return;
        Database::transaction(function() use ($keepId, $mergeFromId) {
            foreach ([
                'customer_history_segments','customer_complaints',
                'loyalty_transactions','reservations','tickets','pnr_passengers'
            ] as $tbl) {
                $col = $tbl === 'tickets' ? 'customer_id' : 'customer_id';
                Database::execute("UPDATE $tbl SET $col=? WHERE $col=?", [$keepId, $mergeFromId]);
            }
            Database::update('customers', ['deleted_at' => date('Y-m-d H:i:s')], 'id = ?', [$mergeFromId]);
            // Recompute totals
            $totals = Database::selectOne(
                "SELECT COUNT(*) AS segs, COALESCE(SUM(distance_km),0) AS dist, COALESCE(SUM(revenue_fcfa),0) AS rev
                 FROM customer_history_segments WHERE customer_id = ?", [$keepId]
            );
            Database::update('customers', [
                'total_segments' => (int)$totals['segs'],
                'total_distance_km' => (int)$totals['dist'],
            ], 'id = ?', [$keepId]);
        });
        AuditLog::record('customer.merge', 'customer', $keepId, ['from' => $mergeFromId]);
    }

    public function findDuplicates(int $limit = 50): array
    {
        return Database::select(
            "SELECT phone_norm, COUNT(*) AS n, GROUP_CONCAT(id) AS ids
             FROM customers WHERE deleted_at IS NULL
             GROUP BY phone_norm HAVING n > 1
             ORDER BY n DESC LIMIT $limit"
        );
    }

    public function rfmSegmentation(): int
    {
        $custs = Database::select(
            "SELECT id, last_trip_at, total_segments, total_spent
             FROM customers WHERE deleted_at IS NULL AND total_segments > 0"
        );
        if (empty($custs)) return 0;

        $n = count($custs);
        $quintile = max(1, (int)ceil($n / 5));

        usort($custs, fn($a,$b) => strtotime($b['last_trip_at'] ?? '1970') <=> strtotime($a['last_trip_at'] ?? '1970'));
        foreach ($custs as $i => $c) {
            $cid = (int)$c['id'];
            $r = 5 - min(4, (int)floor($i / $quintile));
            Database::execute("UPDATE customers SET rfm_recency = ? WHERE id = ?", [$r, $cid]);
        }
        usort($custs, fn($a,$b) => (int)$b['total_segments'] <=> (int)$a['total_segments']);
        foreach ($custs as $i => $c) {
            $f = 5 - min(4, (int)floor($i / $quintile));
            Database::execute("UPDATE customers SET rfm_frequency = ? WHERE id = ?", [$f, $c['id']]);
        }
        usort($custs, fn($a,$b) => (int)$b['total_spent'] <=> (int)$a['total_spent']);
        foreach ($custs as $i => $c) {
            $m = 5 - min(4, (int)floor($i / $quintile));
            Database::execute("UPDATE customers SET rfm_monetary = ? WHERE id = ?", [$m, $c['id']]);
        }
        return $n;
    }

    public function rfmDistribution(): array
    {
        return Database::select(
            "SELECT rfm_recency, rfm_frequency, rfm_monetary, COUNT(*) AS n
             FROM customers WHERE rfm_recency IS NOT NULL
             GROUP BY rfm_recency, rfm_frequency, rfm_monetary ORDER BY n DESC LIMIT 30"
        );
    }
}
