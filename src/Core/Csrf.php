<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * Génère et vérifie les tokens CSRF stockés en session.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (!Session::has(self::KEY)) {
            Session::set(self::KEY, bin2hex(random_bytes(32)));
        }
        return Session::get(self::KEY);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';
    }

    public static function check(?string $token): bool
    {
        if ($token === null || !Session::has(self::KEY)) {
            return false;
        }
        return hash_equals(Session::get(self::KEY), $token);
    }

    /** Régénère le token (après login/logout). */
    public static function regenerate(): void
    {
        Session::set(self::KEY, bin2hex(random_bytes(32)));
    }
}
