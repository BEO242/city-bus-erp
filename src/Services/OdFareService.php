<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Core\Cache;

/**
 * Origin-Destination fares.
 * Lookup priorisé : OD exact -> OD class fallback -> ligne fare standard.
 */
final class OdFareService
{
    public function lookup(int $lineId, int $fromStopId, int $toStopId, string $class = 'M', string $paxType = 'ADT', string $fareBasis = 'STD'): ?array
    {
        $key = "od:{$lineId}:{$fromStopId}:{$toStopId}:{$class}:{$paxType}:{$fareBasis}";
        return Cache::remember($key, 600, function() use ($lineId, $fromStopId, $toStopId, $class, $paxType, $fareBasis) {
            $row = Database::selectOne(
                "SELECT * FROM od_fares
                 WHERE line_id = ? AND from_stop_id = ? AND to_stop_id = ?
                   AND booking_class = ? AND pax_type = ? AND fare_basis = ?
                   AND active = 1
                   AND (valid_from IS NULL OR valid_from <= CURDATE())
                   AND (valid_until IS NULL OR valid_until >= CURDATE())
                 LIMIT 1",
                [$lineId, $fromStopId, $toStopId, $class, $paxType, $fareBasis]
            );
            if ($row) return $row;

            // Fallback : même OD, classe M (économie standard)
            $row = Database::selectOne(
                "SELECT * FROM od_fares
                 WHERE line_id = ? AND from_stop_id = ? AND to_stop_id = ?
                   AND booking_class = 'M' AND pax_type = 'ADT' AND active = 1
                 LIMIT 1",
                [$lineId, $fromStopId, $toStopId]
            );
            return $row;
        });
    }

    public function priceFor(int $lineId, int $fromStopId, int $toStopId, string $class = 'M', string $paxType = 'ADT'): int
    {
        $od = $this->lookup($lineId, $fromStopId, $toStopId, $class, $paxType);
        if ($od) return (int)$od['base_price_fcfa'];

        if (Setting::getBool('odfares.fallback_to_line_price', true)) {
            // Fallback : prix base de la ligne (si défini quelque part)
            $line = Database::selectOne("SELECT base_price_fcfa FROM bus_lines WHERE id = ?", [$lineId]);
            return (int)($line['base_price_fcfa'] ?? 0);
        }
        return 0;
    }

    public function listForLine(int $lineId): array
    {
        return Database::select(
            "SELECT od.*, f.name AS from_name, t.name AS to_name
             FROM od_fares od
             JOIN stops f ON f.id = od.from_stop_id
             JOIN stops t ON t.id = od.to_stop_id
             WHERE od.line_id = ?
             ORDER BY od.from_stop_id, od.to_stop_id, od.booking_class, od.pax_type",
            [$lineId]
        );
    }

    public function upsert(array $data): int
    {
        $required = ['line_id','from_stop_id','to_stop_id','booking_class','base_price_fcfa'];
        foreach ($required as $r) {
            if (!isset($data[$r])) throw new \InvalidArgumentException("Champ manquant: $r");
        }
        $data['fare_basis'] = $data['fare_basis'] ?? 'STD';
        $data['pax_type'] = $data['pax_type'] ?? 'ADT';

        $existing = Database::selectOne(
            "SELECT id FROM od_fares WHERE line_id=? AND from_stop_id=? AND to_stop_id=?
             AND booking_class=? AND fare_basis=? AND pax_type=?",
            [$data['line_id'], $data['from_stop_id'], $data['to_stop_id'],
             $data['booking_class'], $data['fare_basis'], $data['pax_type']]
        );

        Cache::flush('od:');

        if ($existing) {
            Database::update('od_fares', $data, 'id = ?', [$existing['id']]);
            return (int)$existing['id'];
        }
        return Database::insert('od_fares', $data);
    }

    public function bulkGenerate(int $lineId, array $stops, array $prices): int
    {
        $count = 0;
        foreach ($stops as $i => $from) {
            for ($j = $i + 1; $j < count($stops); $j++) {
                $to = $stops[$j];
                $segments = $j - $i;
                foreach (['Y'=>1.5,'B'=>1.25,'M'=>1.0,'H'=>0.8,'L'=>0.6] as $cls => $mult) {
                    $price = (int)round(($prices['base'] ?? 5000) * $segments * $mult);
                    $this->upsert([
                        'line_id' => $lineId,
                        'from_stop_id' => $from['id'],
                        'to_stop_id' => $to['id'],
                        'booking_class' => $cls,
                        'pax_type' => 'ADT',
                        'fare_basis' => 'STD',
                        'base_price_fcfa' => $price,
                    ]);
                    $count++;
                }
            }
        }
        return $count;
    }
}
