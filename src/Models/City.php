<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

final class City extends BaseModel
{
    protected static string $table = 'cities';

    /** Liste pour selects : [id => name] */
    public static function options(): array
    {
        $rows = Database::select(
            "SELECT id, name FROM cities WHERE is_active = 1 ORDER BY display_order, name"
        );
        $opts = [];
        foreach ($rows as $r) {
            $opts[(int)$r['id']] = $r['name'];
        }
        return $opts;
    }

    /** Toutes les villes actives, full row. */
    public static function active(): array
    {
        return Database::select(
            "SELECT * FROM cities WHERE is_active = 1 ORDER BY display_order, name"
        );
    }
}
