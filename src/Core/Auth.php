<?php

declare(strict_types=1);

namespace CityBus\Core;

use CityBus\Models\Permission;

/**
 * Authentification + autorisation.
 *
 * - Permissions chargées depuis la DB (table permissions + role_permissions + user_permissions)
 * - Cache mémoire par requête
 * - Lockout temporaire intégré
 * - 2FA pris en charge (étape intermédiaire avant validation complète)
 */
final class Auth
{
    private const KEY            = '_user_id';
    private const PENDING_2FA    = '_pending_2fa_user_id';
    private static ?array $cachedUser  = null;
    private static ?array $cachedPerms = null;

    /**
     * Tente une authentification. Retourne :
     *   true       : connecté complètement
     *   '2fa'      : credentials OK mais 2FA requis (user_id stocké en pending)
     *   'locked'   : compte verrouillé (locked_until)
     *   false      : credentials invalides ou inactif
     */
    public static function attempt(string $email, string $password): bool|string
    {
        $email = strtolower(trim($email));
        $user  = Database::selectOne(
            'SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1',
            [$email]
        );
        // Toujours faire un password_verify factice pour éviter timing attacks
        if (!$user) {
            password_verify($password, '$2y$12$............................................................');
            return false;
        }

        // Lockout
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return 'locked';
        }
        if ((int)$user['is_active'] !== 1) {
            return false;
        }
        if (!password_verify($password, $user['password_hash'])) {
            self::handleFailedLogin($user);
            return false;
        }

        // Re-hash si l'algo a changé
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
            $newHash = password_hash($password, PASSWORD_ARGON2ID);
            Database::execute('UPDATE users SET password_hash = ? WHERE id = ?', [$newHash, $user['id']]);
        }

        // Reset compteurs d'échec
        Database::execute(
            'UPDATE users SET failed_login_count = 0, locked_until = NULL WHERE id = ?',
            [$user['id']]
        );

        // 2FA requis ?
        if ((int)$user['two_factor_enabled'] === 1) {
            Session::set(self::PENDING_2FA, (int)$user['id']);
            return '2fa';
        }

        self::completeLogin((int)$user['id']);
        return true;
    }

    /** Marque l'utilisateur comme entièrement connecté (post-2FA). */
    public static function completeLogin(int $userId): void
    {
        session_regenerate_id(true);
        Session::set(self::KEY, $userId);
        Session::set('_login_at', time());
        Session::forget(self::PENDING_2FA);
        Csrf::regenerate();

        Database::insert(
            'INSERT INTO login_history (user_id, ip_address, user_agent, logged_in_at) VALUES (?, ?, ?, NOW())',
            [$userId, RateLimiter::clientIp(), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]
        );
        Database::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$userId]);

        self::$cachedUser  = null;
        self::$cachedPerms = null;
    }

    private static function handleFailedLogin(array $user): void
    {
        $maxAttempts = Setting::getInt('security.login_max_attempts', 5);
        $lockoutMin  = Setting::getInt('security.login_lockout_minutes', 15);
        $newCount    = (int)$user['failed_login_count'] + 1;
        $lockUntil   = null;
        if ($newCount >= $maxAttempts) {
            $lockUntil = (new \DateTime())->modify("+{$lockoutMin} minutes")->format('Y-m-d H:i:s');
        }
        Database::execute(
            'UPDATE users SET failed_login_count = ?, locked_until = ? WHERE id = ?',
            [$newCount, $lockUntil, $user['id']]
        );
    }

    public static function pendingTwoFactorUserId(): ?int
    {
        $id = Session::get(self::PENDING_2FA);
        return $id ? (int)$id : null;
    }

    public static function logout(): void
    {
        Session::flush();
        Session::start();
        Csrf::regenerate();
        self::$cachedUser  = null;
        self::$cachedPerms = null;
    }

    public static function check(): bool
    {
        return Session::has(self::KEY);
    }

    public static function id(): ?int
    {
        $id = Session::get(self::KEY);
        return $id ? (int)$id : null;
    }

    public static function user(): ?array
    {
        if (!self::check()) return null;
        if (self::$cachedUser !== null) return self::$cachedUser;

        $user = Database::selectOne(
            'SELECT u.*, a.name AS agency_name, c.slug AS agency_city, c.name AS agency_city_name,
                    u.role AS role_slug, u.role AS role_label
             FROM users u
             LEFT JOIN agencies a ON a.id = u.agency_id
             LEFT JOIN cities c ON c.id = a.city_id
             WHERE u.id = ? AND u.is_active = 1 AND u.deleted_at IS NULL',
            [self::id()]
        );
        if (!$user) {
            self::logout();
            return null;
        }
        self::$cachedUser = $user;
        return $user;
    }

    public static function role(): ?string
    {
        $u = self::user();
        return $u['role_slug'] ?? $u['role'] ?? null;
    }

    public static function hasRole(string|array $roles): bool
    {
        $role = self::role();
        if ($role === null) return false;
        return in_array($role, (array)$roles, true);
    }

    /**
     * Vérifie une permission (lookup DB cached).
     * - rôle admin → toujours OK
     */
    public static function can(string $permission): bool
    {
        if (!self::check()) return false;
        if (self::role() === 'admin') return true;
        return in_array($permission, self::permissions(), true);
    }

    /** Liste cached des permissions du user courant. */
    public static function permissions(): array
    {
        if (self::$cachedPerms !== null) return self::$cachedPerms;
        $userId = self::id();
        if ($userId === null) return [];
        self::$cachedPerms = Permission::effectiveForUser($userId);
        return self::$cachedPerms;
    }

    public static function authorize(string $permission): void
    {
        if (!self::can($permission)) {
            http_response_code(403);
            $view = new View();
            echo $view->render('errors/403', ['permission' => $permission]);
            exit;
        }
    }

    /** Hash mot de passe (Argon2id). */
    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2,
        ]);
    }

    /** Invalide le cache (après modif des perms en DB). */
    public static function flushCache(): void
    {
        self::$cachedUser  = null;
        self::$cachedPerms = null;
    }
}
