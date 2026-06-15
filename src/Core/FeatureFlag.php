<?php

declare(strict_types=1);

namespace CityBus\Core;

final class FeatureFlag
{
    private static array $cache = [];

    public static function enabled(string $key, ?int $userId = null): bool
    {
        $flag = self::load($key);
        if (!$flag) return false;
        if (!$flag['enabled']) return false;

        // Roll-out par hash userId
        $pct = (int)$flag['rollout_pct'];
        if ($pct < 100) {
            $hash = $userId ? (crc32((string)$userId) % 100) : 50;
            if ($hash >= $pct) return false;
        }

        // Whitelist users
        if (!empty($flag['target_users'])) {
            $list = json_decode($flag['target_users'], true) ?: [];
            return $userId && in_array($userId, $list, true);
        }
        return true;
    }

    public static function set(string $key, bool $enabled, int $rolloutPct = 100): void
    {
        Database::execute(
            "INSERT INTO feature_flags (flag_key, enabled, rollout_pct)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), rollout_pct = VALUES(rollout_pct)",
            [$key, $enabled ? 1 : 0, $rolloutPct]
        );
        unset(self::$cache[$key]);
    }

    private static function load(string $key): ?array
    {
        if (array_key_exists($key, self::$cache)) return self::$cache[$key];
        $row = Database::selectOne("SELECT * FROM feature_flags WHERE flag_key = ?", [$key]);
        return self::$cache[$key] = $row ?: null;
    }

    public static function all(): array
    {
        return Database::select("SELECT * FROM feature_flags ORDER BY flag_key");
    }
}
