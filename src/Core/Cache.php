<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * Cache backed by app_cache table (DB-only, simple, no external dep).
 * Pour Redis : créer RedisCacheAdapter avec même API.
 */
final class Cache
{
    private static array $memo = [];

    public static function get(string $key): mixed
    {
        if (array_key_exists($key, self::$memo)) return self::$memo[$key];
        $row = Database::selectOne(
            "SELECT value, expires_at FROM app_cache WHERE cache_key = ?", [$key]
        );
        if (!$row) return self::$memo[$key] = null;
        if ($row['expires_at'] && strtotime($row['expires_at']) < time()) {
            self::forget($key);
            return self::$memo[$key] = null;
        }
        return self::$memo[$key] = json_decode($row['value'], true);
    }

    public static function set(string $key, mixed $value, int $ttlSeconds = 3600): void
    {
        $expires = $ttlSeconds > 0 ? date('Y-m-d H:i:s', time() + $ttlSeconds) : null;
        Database::execute(
            "INSERT INTO app_cache (cache_key, value, expires_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), expires_at = VALUES(expires_at)",
            [$key, json_encode($value), $expires]
        );
        self::$memo[$key] = $value;
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $hit = self::get($key);
        if ($hit !== null) return $hit;
        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    public static function forget(string $key): void
    {
        unset(self::$memo[$key]);
        Database::execute("DELETE FROM app_cache WHERE cache_key = ?", [$key]);
    }

    public static function flush(string $prefix = ''): void
    {
        self::$memo = [];
        if ($prefix) {
            Database::execute("DELETE FROM app_cache WHERE cache_key LIKE ?", [$prefix . '%']);
        } else {
            Database::execute("DELETE FROM app_cache");
        }
    }

    public static function gc(): int
    {
        $stmt = Database::execute("DELETE FROM app_cache WHERE expires_at IS NOT NULL AND expires_at < NOW()");
        return $stmt->rowCount();
    }
}
