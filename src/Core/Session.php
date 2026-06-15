<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * Encapsule la session PHP avec stockage fichier dédié + helpers flash.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $cfg = Config::get('session', []);
        $storage = $cfg['storage'] ?? sys_get_temp_dir();
        if (!is_dir($storage)) {
            @mkdir($storage, 0775, true);
        }

        session_save_path($storage);
        session_name($cfg['name'] ?? 'citybus_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $cfg['path']     ?? '/',
            'domain'   => '',
            'secure'   => $cfg['secure']   ?? false,
            'httponly' => $cfg['httponly'] ?? true,
            'samesite' => $cfg['samesite'] ?? 'Lax',
        ]);
        session_start();

        // Expiration par inactivité paramétrable (security.session_lifetime en minutes, 0 = désactivé)
        try {
            $lifetimeMin = max(0, (int)Setting::getInt('security.session_lifetime', 0));
        } catch (\Throwable) {
            $lifetimeMin = 0;
        }
        if ($lifetimeMin > 0) {
            $now = time();
            $last = $_SESSION['_last_activity'] ?? $now;
            if (!empty($_SESSION['user_id']) && ($now - $last) > $lifetimeMin * 60) {
                $_SESSION = [];
                session_destroy();
                session_start();
                $_SESSION['_session_expired'] = true;
            }
            $_SESSION['_last_activity'] = $now;
        }

        // Régénération anti-fixation toutes les 30 min
        if (!isset($_SESSION['_regenerated_at']) || time() - $_SESSION['_regenerated_at'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_regenerated_at'] = time();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flush(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /** Flash message (lu une seule fois). */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type][] = $message;
    }

    public static function pullFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }
}
