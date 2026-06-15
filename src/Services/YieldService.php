<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;

/**
 * Yield management : applique les règles de tarification dynamique au prix
 * de base d'un voyage en fonction de la date, du jour, du remplissage.
 */
final class YieldService
{
    /**
     * Calcule le prix ajusté selon les règles applicables à un voyage.
     *
     * @return array{base_price:int, final_price:int, total_adjustment_pct:float, applied_rules:array}
     */
    public function adjustPrice(int $basePrice, int $tripId, ?int $lineId = null): array
    {
        if (!Setting::getBool('pricing.yield_enabled', true) || $basePrice <= 0) {
            return ['base_price' => $basePrice, 'final_price' => $basePrice, 'total_adjustment_pct' => 0, 'applied_rules' => []];
        }

        $trip = Database::selectOne(
            "SELECT trip_date, departure_scheduled, line_id FROM trips WHERE id = ?", [$tripId]
        );
        if (!$trip) return ['base_price' => $basePrice, 'final_price' => $basePrice, 'total_adjustment_pct' => 0, 'applied_rules' => []];

        $lineId = $lineId ?? (int)$trip['line_id'];
        $rules = Database::select(
            "SELECT * FROM pricing_rules
             WHERE is_active = 1
               AND (line_id IS NULL OR line_id = ?)
             ORDER BY priority ASC", [$lineId]
        );

        $tripTs = strtotime($trip['trip_date'] . ' ' . $trip['departure_scheduled']);
        $now = time();
        $hoursUntil = max(0, ($tripTs - $now) / 3600);
        $daysUntil = $hoursUntil / 24;
        $tripDow = (int)date('N', $tripTs);

        $loadFactor = $this->loadFactor($tripId);
        $totalAdjPct = 0.0;
        $applied = [];

        foreach ($rules as $r) {
            $matches = match ($r['rule_type']) {
                'early_bird'  => $r['days_before_min'] !== null && $daysUntil >= (int)$r['days_before_min']
                                && ($r['days_before_max'] === null || $daysUntil <= (int)$r['days_before_max']),
                'last_minute' => $r['days_before_min'] !== null && $daysUntil >= (int)$r['days_before_min']
                                && ($r['days_before_max'] === null || $daysUntil <= (int)$r['days_before_max']),
                'peak_day'    => $r['days_of_week'] && in_array($tripDow, array_map('intval', explode(',', (string)$r['days_of_week'])), true),
                'off_peak'    => $r['days_of_week'] && in_array($tripDow, array_map('intval', explode(',', (string)$r['days_of_week'])), true),
                'load_factor' => $r['load_factor_min'] !== null && $loadFactor >= (int)$r['load_factor_min'],
                'date_range'  => $r['date_from'] && $r['date_until']
                                && $trip['trip_date'] >= $r['date_from'] && $trip['trip_date'] <= $r['date_until'],
                default       => false,
            };
            if (!$matches) continue;

            $value = (float)$r['adjustment_value'];
            $impact = match ($r['adjustment_type']) {
                'percent_discount'  => -($basePrice * $value / 100),
                'percent_surcharge' =>  ($basePrice * $value / 100),
                'fixed_discount'    => -$value,
                'fixed_surcharge'   =>  $value,
                default             => 0,
            };
            $totalAdjPct += in_array($r['adjustment_type'], ['percent_discount','percent_surcharge'], true)
                ? ($r['adjustment_type'] === 'percent_discount' ? -$value : $value)
                : 0;
            $applied[] = [
                'rule'   => $r['rule_key'],
                'label'  => $r['label'],
                'impact' => (int)round($impact),
            ];
        }

        $finalPrice = max(0, $basePrice + (int)round(array_sum(array_column($applied, 'impact'))));

        return [
            'base_price'           => $basePrice,
            'final_price'          => $finalPrice,
            'total_adjustment_pct' => round($totalAdjPct, 2),
            'applied_rules'        => $applied,
        ];
    }

    private function loadFactor(int $tripId): int
    {
        $row = Database::selectOne(
            "SELECT t.bus_id, b.seats,
                    (SELECT COUNT(*) FROM tickets WHERE trip_id = t.id AND deleted_at IS NULL
                       AND status IN ('emis','controle','utilise','embarque')) AS sold
             FROM trips t LEFT JOIN buses b ON b.id = t.bus_id
             WHERE t.id = ?", [$tripId]
        );
        if (!$row || !$row['seats']) return 0;
        return (int)round(((int)$row['sold'] / max(1, (int)$row['seats'])) * 100);
    }
}
