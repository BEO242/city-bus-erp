<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Core\Auth;

/**
 * Yield management dynamique.
 *
 * Boucle d'application :
 *   1. Récupère le prix de base par classe (TripInventory)
 *   2. Récupère les règles applicables (line, class, dates)
 *   3. Évalue chaque règle vs métrique courante (load_factor, days_to_departure...)
 *   4. Applique multiplier puis delta_fcfa, en respectant la guard-rail (max +/-%)
 *   5. Snapshot dans trip_price_history si prix change
 */
final class PricingService
{
    public function recalcTrip(int $tripId, ?int $actorId = null): array
    {
        if (!Setting::getBool('pricing.dynamic.enabled', true)) {
            return ['skipped' => true, 'reason' => 'disabled'];
        }

        $trip = Database::selectOne("SELECT id, line_id, trip_date FROM trips WHERE id = ?", [$tripId]);
        if (!$trip) return ['skipped' => true, 'reason' => 'not_found'];

        $inv = Database::select(
            "SELECT class_code, capacity, sold_count, price_fcfa, base_price_fcfa
             FROM trip_inventory WHERE trip_id = ?", [$tripId]
        );
        if (!$inv) return ['skipped' => true, 'reason' => 'no_inventory'];

        $totalCap = array_sum(array_column($inv, 'capacity'));
        $totalSold = array_sum(array_column($inv, 'sold_count'));
        $loadPct = $totalCap > 0 ? ($totalSold / $totalCap) * 100 : 0;
        $dtd = max(0, (int)((strtotime($trip['trip_date']) - time()) / 86400));

        $rules = Database::select(
            "SELECT * FROM voyage_pricing_rules
             WHERE active = 1
               AND (scope_line_id IS NULL OR scope_line_id = ?)
               AND (valid_from IS NULL OR valid_from <= CURDATE())
               AND (valid_until IS NULL OR valid_until >= CURDATE())
             ORDER BY priority ASC", [$trip['line_id']]
        );

        $maxUp = (int)Setting::get('pricing.max_increase_pct', 50);
        $maxDn = (int)Setting::get('pricing.max_decrease_pct', 40);

        $changes = [];
        foreach ($inv as $row) {
            $base = (int)($row['base_price_fcfa'] ?? $row['price_fcfa']);
            $cur  = (int)$row['price_fcfa'];
            $new  = $base;
            $appliedRules = [];

            foreach ($rules as $rule) {
                if ($rule['scope_class'] !== null && $rule['scope_class'] !== $row['class_code']) continue;
                if (!$this->matchesRule($rule, $loadPct, $dtd, $row)) continue;

                $new = (int)round($new * (float)$rule['multiplier']);
                $new += (int)$rule['delta_fcfa'];
                $appliedRules[] = (int)$rule['id'];
            }

            // Guard-rail
            $maxAllowed = (int)round($base * (1 + $maxUp / 100));
            $minAllowed = (int)round($base * (1 - $maxDn / 100));
            $new = max($minAllowed, min($maxAllowed, $new));

            if ($new !== $cur) {
                Database::update('trip_inventory',
                    ['price_fcfa' => $new],
                    'trip_id = ? AND class_code = ?',
                    [$tripId, $row['class_code']]
                );
                Database::insert('trip_price_history', [
                    'trip_id'       => $tripId,
                    'class_code'    => $row['class_code'],
                    'old_price'     => $cur,
                    'new_price'     => $new,
                    'change_reason' => 'Dynamic recalc · load=' . round($loadPct) . '% · dtd=' . $dtd . 'j',
                    'rule_id'       => $appliedRules ? $appliedRules[0] : null,
                    'actor_id'      => $actorId,
                ]);
                $changes[] = [
                    'class' => $row['class_code'],
                    'old' => $cur, 'new' => $new,
                    'rules' => $appliedRules,
                ];
            }
        }

        return [
            'skipped'   => false,
            'load_pct'  => round($loadPct, 1),
            'dtd'       => $dtd,
            'changes'   => $changes,
            'count'     => count($changes),
        ];
    }

    private function matchesRule(array $rule, float $loadPct, int $dtd, array $row): bool
    {
        $val = match($rule['rule_type']) {
            'load_factor'       => $loadPct,
            'days_to_departure' => (float)$dtd,
            'time_of_day'       => (float)(int)date('H'),
            'day_of_week'       => (float)(int)date('N'),
            default             => null,
        };
        if ($val === null) return true;

        $min = $rule['threshold_min'] !== null ? (float)$rule['threshold_min'] : null;
        $max = $rule['threshold_max'] !== null ? (float)$rule['threshold_max'] : null;
        if ($min !== null && $val < $min) return false;
        if ($max !== null && $val > $max) return false;
        return true;
    }

    public function listRules(?int $lineId = null): array
    {
        $sql = "SELECT pr.*, l.code AS line_code FROM voyage_pricing_rules pr
                LEFT JOIN bus_lines l ON l.id = pr.scope_line_id";
        $params = [];
        if ($lineId !== null) {
            $sql .= " WHERE pr.scope_line_id = ? OR pr.scope_line_id IS NULL";
            $params[] = $lineId;
        }
        $sql .= " ORDER BY pr.priority ASC, pr.id DESC";
        return Database::select($sql, $params);
    }

    public function createRule(array $data): int
    {
        $allowed = ['name','description','rule_type','scope_line_id','scope_class',
            'threshold_min','threshold_max','multiplier','delta_fcfa','active','priority',
            'valid_from','valid_until'];
        $clean = array_intersect_key($data, array_flip($allowed));
        if (empty($clean['name']) || empty($clean['rule_type'])) {
            throw new \InvalidArgumentException('name et rule_type requis');
        }
        return Database::insert('voyage_pricing_rules', $clean);
    }

    public function updateRule(int $id, array $data): void
    {
        $allowed = ['name','description','rule_type','scope_line_id','scope_class',
            'threshold_min','threshold_max','multiplier','delta_fcfa','active','priority',
            'valid_from','valid_until'];
        $clean = array_intersect_key($data, array_flip($allowed));
        if (empty($clean)) return;
        Database::update('voyage_pricing_rules', $clean, 'id = ?', [$id]);
    }

    public function deleteRule(int $id): void
    {
        Database::delete('voyage_pricing_rules', 'id = ?', [$id]);
    }
}
