<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

/**
 * Arrêts matérialisés pour UN voyage (snapshot des stops de la ligne).
 * Permet le tracking individuel : heures réelles, ETA, retards.
 */
final class TripStop extends BaseModel
{
    protected static string $table = 'trip_stops';

    /** Liste ordonnée des arrêts d'un voyage avec infos enrichies. */
    public static function forTrip(int $tripId): array
    {
        return Database::select(
            "SELECT ts.*,
                    s.name AS stop_name,
                    s.order_position AS stop_order,
                    s.km_from_origin AS stop_km,
                    -- Calcul du retard total (héritage + ajout local)
                    (COALESCE(ts.delay_inherited_min,0) + COALESCE(ts.delay_added_min,0)) AS total_delay_min,
                    -- Statut de l'arrêt
                    CASE
                      WHEN ts.is_skipped = 1 THEN 'skipped'
                      WHEN ts.actual_departure IS NOT NULL THEN 'departed'
                      WHEN ts.actual_arrival IS NOT NULL THEN 'arrived'
                      ELSE 'pending'
                    END AS status
             FROM trip_stops ts
             JOIN stops s ON s.id = ts.stop_id
             WHERE ts.trip_id = ?
             ORDER BY ts.sequence",
            [$tripId]
        );
    }

    /** Arrêt suivant à atteindre (le premier non parcouru). */
    public static function nextStop(int $tripId): ?array
    {
        return Database::selectOne(
            "SELECT ts.*, s.name AS stop_name
             FROM trip_stops ts
             JOIN stops s ON s.id = ts.stop_id
             WHERE ts.trip_id = ? AND ts.actual_arrival IS NULL AND ts.is_skipped = 0
             ORDER BY ts.sequence ASC LIMIT 1",
            [$tripId]
        );
    }

    /** Dernier arrêt atteint. */
    public static function lastReached(int $tripId): ?array
    {
        return Database::selectOne(
            "SELECT ts.*, s.name AS stop_name
             FROM trip_stops ts
             JOIN stops s ON s.id = ts.stop_id
             WHERE ts.trip_id = ? AND ts.actual_arrival IS NOT NULL
             ORDER BY ts.sequence DESC LIMIT 1",
            [$tripId]
        );
    }
}
