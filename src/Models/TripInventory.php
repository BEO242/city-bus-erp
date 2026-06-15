<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

/**
 * Inventaire d'un voyage par classe (trip × class → capacité, prix, vendus).
 */
final class TripInventory extends BaseModel
{
    protected static string $table = 'trip_inventory';

    /** Toutes les classes d'un voyage, avec stats calculées. */
    public static function forTrip(int $tripId): array
    {
        return Database::select(
            "SELECT ti.*,
                    c.label, c.color_hex, c.flexibility, c.priority_boarding,
                    (ti.capacity - ti.sold_count - ti.reserved_count - ti.blocked_count) AS available
             FROM trip_inventory ti
             JOIN inventory_classes c ON c.id = ti.class_id
             WHERE ti.trip_id = ?
             ORDER BY c.sort_order, c.code",
            [$tripId]
        );
    }

    /** Disponibilité d'une classe spécifique. */
    public static function availability(int $tripId, string $classCode): ?array
    {
        return Database::selectOne(
            "SELECT *, (capacity - sold_count - reserved_count - blocked_count) AS available
             FROM trip_inventory
             WHERE trip_id = ? AND class_code = ?",
            [$tripId, strtoupper($classCode)]
        );
    }

    /** Total inventaire (somme des capacités). */
    public static function totalCapacity(int $tripId): int
    {
        $row = Database::selectOne(
            "SELECT COALESCE(SUM(capacity),0) AS total FROM trip_inventory WHERE trip_id = ?",
            [$tripId]
        );
        return (int)($row['total'] ?? 0);
    }

    /** Total vendu, toutes classes confondues. */
    public static function totalSold(int $tripId): int
    {
        $row = Database::selectOne(
            "SELECT COALESCE(SUM(sold_count),0) AS total FROM trip_inventory WHERE trip_id = ?",
            [$tripId]
        );
        return (int)($row['total'] ?? 0);
    }
}
