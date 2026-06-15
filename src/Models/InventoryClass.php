<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

/**
 * Classes d'inventaire (booking classes) — Y, B, M, H, L, V…
 * Inspiré des standards aviation (IATA RP 1707).
 */
final class InventoryClass extends BaseModel
{
    protected static string $table = 'inventory_classes';

    public const FLEXIBILITY = [
        'full'           => 'Pleine flexibilité',
        'medium'         => 'Modifications avec frais',
        'restricted'     => 'Restrictions importantes',
        'non_refundable' => 'Non remboursable',
    ];

    /** Toutes les classes actives, triées par ordre. */
    public static function active(): array
    {
        return Database::select(
            "SELECT * FROM inventory_classes WHERE is_active = 1 ORDER BY sort_order, code"
        );
    }

    /** Classe par code (Y, B, M…). */
    public static function byCode(string $code): ?array
    {
        return Database::selectOne(
            "SELECT * FROM inventory_classes WHERE code = ? LIMIT 1",
            [strtoupper($code)]
        );
    }

    /** Map [code => row] pour lookups rapides. */
    public static function map(): array
    {
        $rows = self::active();
        $out = [];
        foreach ($rows as $r) $out[$r['code']] = $r;
        return $out;
    }
}
