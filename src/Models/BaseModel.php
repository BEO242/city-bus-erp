<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

/**
 * Modèle de base : Active Record léger sur PDO.
 */
abstract class BaseModel
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static bool $softDeletes = false;
    protected static bool $timestamps = true;

    public static function table(): string { return static::$table; }

    public static function find(int|string $id): ?array
    {
        $sql = "SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?";
        if (static::$softDeletes) $sql .= " AND deleted_at IS NULL";
        return Database::selectOne($sql . ' LIMIT 1', [$id]);
    }

    public static function findOrFail(int|string $id): array
    {
        $row = static::find($id);
        if (!$row) throw new \RuntimeException(static::class . " #$id introuvable");
        return $row;
    }

    public static function all(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        $sql = "SELECT * FROM " . static::$table;
        if (static::$softDeletes) $sql .= " WHERE deleted_at IS NULL";
        $sql .= " ORDER BY $orderBy $direction";
        return Database::select($sql);
    }

    public static function where(string $column, mixed $value): array
    {
        $sql = "SELECT * FROM " . static::$table . " WHERE $column = ?";
        if (static::$softDeletes) $sql .= " AND deleted_at IS NULL";
        return Database::select($sql, [$value]);
    }

    public static function firstWhere(string $column, mixed $value): ?array
    {
        $sql = "SELECT * FROM " . static::$table . " WHERE $column = ?";
        if (static::$softDeletes) $sql .= " AND deleted_at IS NULL";
        return Database::selectOne($sql . ' LIMIT 1', [$value]);
    }

    public static function create(array $data): int
    {
        if (static::$timestamps) {
            $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
            $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');
        }
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => '?', $cols);
        $sql = sprintf("INSERT INTO %s (%s) VALUES (%s)",
            static::$table,
            implode(',', array_map(fn($c) => "`$c`", $cols)),
            implode(',', $placeholders)
        );
        return Database::insert($sql, array_values($data));
    }

    public static function update(int|string $id, array $data): int
    {
        if (static::$timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        $sql = sprintf("UPDATE %s SET $set WHERE %s = ?", static::$table, static::$primaryKey);
        return Database::execute($sql, [...array_values($data), $id]);
    }

    public static function delete(int|string $id): int
    {
        if (static::$softDeletes) {
            return Database::execute(
                sprintf("UPDATE %s SET deleted_at = NOW() WHERE %s = ?", static::$table, static::$primaryKey),
                [$id]
            );
        }
        return Database::execute(
            sprintf("DELETE FROM %s WHERE %s = ?", static::$table, static::$primaryKey),
            [$id]
        );
    }

    public static function count(string $where = '', array $params = []): int
    {
        $sql = "SELECT COUNT(*) AS c FROM " . static::$table;
        $clauses = [];
        if (static::$softDeletes) $clauses[] = 'deleted_at IS NULL';
        if ($where) $clauses[] = $where;
        if ($clauses) $sql .= ' WHERE ' . implode(' AND ', $clauses);
        $row = Database::selectOne($sql, $params);
        return (int)($row['c'] ?? 0);
    }

    public static function paginate(int $perPage = 25, int $page = 1, string $where = '', array $params = [], string $orderBy = 'id DESC'): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT * FROM " . static::$table;
        $clauses = [];
        if (static::$softDeletes) $clauses[] = 'deleted_at IS NULL';
        if ($where) $clauses[] = $where;
        if ($clauses) $sql .= ' WHERE ' . implode(' AND ', $clauses);
        $sql .= " ORDER BY $orderBy LIMIT $perPage OFFSET $offset";

        $items = Database::select($sql, $params);
        $total = self::count($where, $params);

        return [
            'items'    => $items,
            'total'    => $total,
            'per_page' => $perPage,
            'page'     => $page,
            'pages'    => max(1, (int)ceil($total / $perPage)),
        ];
    }
}
