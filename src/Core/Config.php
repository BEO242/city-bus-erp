<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * Conteneur de configuration global.
 * Charge tous les fichiers PHP d'un dossier et permet l'accès via dot-notation.
 */
final class Config
{
    private static array $items = [];

    public static function load(string $directory): void
    {
        foreach (glob($directory . '/*.php') as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            self::$items[$key] = require $file;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref =& self::$items;
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref =& $ref[$segment];
        }
        $ref = $value;
    }

    public static function all(): array
    {
        return self::$items;
    }
}
