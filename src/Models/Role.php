<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

final class Role extends BaseModel
{
    protected static string $table = 'roles';

    /** Tous les rôles, indexés par slug. */
    public static function allBySlug(): array
    {
        $rows = Database::select("SELECT * FROM roles ORDER BY sort_order, label");
        return array_column($rows, null, 'slug');
    }

    /** Récupère les permissions (slugs) attribuées à un rôle. */
    public static function permissionSlugs(int $roleId): array
    {
        $rows = Database::select(
            "SELECT p.slug FROM permissions p
              INNER JOIN role_permissions rp ON rp.permission_id = p.id
              WHERE rp.role_id = ?",
            [$roleId]
        );
        return array_column($rows, 'slug');
    }

    /** Remplace toutes les permissions d'un rôle. */
    public static function syncPermissions(int $roleId, array $permissionIds): void
    {
        Database::execute("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);
        $permissionIds = array_unique(array_map('intval', $permissionIds));
        foreach ($permissionIds as $pid) {
            if ($pid <= 0) continue;
            Database::execute(
                "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                [$roleId, $pid]
            );
        }
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::selectOne("SELECT * FROM roles WHERE slug = ?", [$slug]);
    }
}
