<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

final class Permission extends BaseModel
{
    protected static string $table = 'permissions';

    /** Toutes les permissions groupées par module. */
    public static function allByModule(): array
    {
        $rows = Database::select(
            "SELECT * FROM permissions ORDER BY module, sort_order, slug"
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['module']][] = $r;
        }
        return $out;
    }

    /** Slugs → IDs. */
    public static function slugToIdMap(): array
    {
        $rows = Database::select("SELECT id, slug FROM permissions");
        return array_column($rows, 'id', 'slug');
    }

    /** Permissions effectives d'un user (rôle + overrides). Retourne des slugs. */
    public static function effectiveForUser(int $userId): array
    {
        // Permissions de base via rôle
        $base = Database::select(
            "SELECT DISTINCT p.slug FROM permissions p
              INNER JOIN role_permissions rp ON rp.permission_id = p.id
              INNER JOIN users u ON u.role_id = rp.role_id
              WHERE u.id = ?",
            [$userId]
        );
        $set = array_column($base, 'slug');

        // Overrides
        $overrides = Database::select(
            "SELECT p.slug, up.granted FROM user_permissions up
              INNER JOIN permissions p ON p.id = up.permission_id
              WHERE up.user_id = ?",
            [$userId]
        );
        foreach ($overrides as $o) {
            if ((int)$o['granted'] === 1) {
                if (!in_array($o['slug'], $set, true)) $set[] = $o['slug'];
            } else {
                $set = array_values(array_filter($set, fn($s) => $s !== $o['slug']));
            }
        }
        return $set;
    }
}
