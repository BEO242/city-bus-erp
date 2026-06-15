<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * Service centralisé de paramètres applicatifs (DB-backed, cached in-memory).
 * - Lecture typée (int/bool/json/text/string/secret)
 * - Cache mémoire pour la requête courante
 * - Fallback sur valeur par défaut si clé absente
 */
final class Setting
{
    private static array $cache = [];
    private static bool  $loaded = false;

    private static function load(): void
    {
        if (self::$loaded) return;
        try {
            $rows = Database::select(
                "SELECT setting_key, setting_value, setting_type FROM app_settings"
            );
            foreach ($rows as $r) {
                self::$cache[$r['setting_key']] = self::cast($r['setting_value'], $r['setting_type']);
            }
        } catch (\Throwable) {
            // Si la table n'existe pas encore (ex: install initial), on ignore
        }
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int)self::get($key, $default);
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        return (bool)self::get($key, $default);
    }

    public static function getString(string $key, string $default = ''): string
    {
        return (string)self::get($key, $default);
    }

    /** Met à jour une valeur (typée). Crée si absente. */
    public static function set(string $key, mixed $value, ?int $userId = null): void
    {
        $type = self::detectType($value);
        $stored = self::stringify($value, $type);
        Database::execute(
            "INSERT INTO app_settings (setting_key, category, setting_type, setting_value, label, updated_by)
             VALUES (?, 'misc', ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)",
            [$key, $type, $stored, $key, $userId]
        );
        self::$cache[$key] = self::cast($stored, $type);
    }

    /** Mise à jour bulk depuis un tableau [clé => valeur brute string]. Conserve le type d'origine. */
    public static function bulkUpdate(array $values, ?int $userId = null): void
    {
        if (empty($values)) return;

        // Charger les types existants
        $rows = Database::select(
            "SELECT setting_key, setting_type FROM app_settings WHERE setting_key IN ("
            . implode(',', array_fill(0, count($values), '?')) . ")",
            array_keys($values)
        );
        $types = array_column($rows, 'setting_type', 'setting_key');
        foreach ($values as $k => $raw) {
            $type   = $types[$k] ?? 'string';
            $stored = self::normalizeFormInput($raw, $type);
            Database::execute(
                "UPDATE app_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?",
                [$stored, $userId, $k]
            );
            self::$cache[$k] = self::cast($stored, $type);
        }
    }

    public static function flushCache(): void
    {
        self::$cache  = [];
        self::$loaded = false;
    }

    /** Récupère tous les paramètres groupés par catégorie. */
    public static function allByCategory(): array
    {
        $rows = Database::select(
            "SELECT * FROM app_settings ORDER BY category, sort_order, setting_key"
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['category']][] = $r;
        }
        return $out;
    }

    private static function cast(?string $raw, string $type): mixed
    {
        if ($raw === null) return null;
        return match ($type) {
            'int'    => (int)$raw,
            'bool'   => $raw === '1' || strtolower($raw) === 'true',
            'json'   => json_decode($raw, true) ?? [],
            default  => $raw,
        };
    }

    private static function detectType(mixed $value): string
    {
        return match (true) {
            is_bool($value)  => 'bool',
            is_int($value)   => 'int',
            is_array($value) => 'json',
            default          => 'string',
        };
    }

    private static function stringify(mixed $value, string $type): string
    {
        return match ($type) {
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string)$value,
        };
    }

    private static function normalizeFormInput(mixed $raw, string $type): string
    {
        return match ($type) {
            'bool' => ($raw === '1' || $raw === 1 || $raw === true || $raw === 'on' || $raw === 'true') ? '1' : '0',
            'int'  => (string)(int)$raw,
            'json' => is_string($raw) ? $raw : json_encode($raw, JSON_UNESCAPED_UNICODE),
            default => (string)$raw,
        };
    }
}
