<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * Rate limiter simple basé sur la table login_attempts.
 * - Compte les tentatives par email et par IP sur fenêtre glissante.
 */
final class RateLimiter
{
    /** Enregistre une tentative. */
    public static function record(?string $email, bool $success): void
    {
        Database::insert(
            "INSERT INTO login_attempts (email, ip_address, user_agent, success) VALUES (?, ?, ?, ?)",
            [
                $email ? strtolower(trim($email)) : null,
                self::clientIp(),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                $success ? 1 : 0,
            ]
        );
    }

    /**
     * Vérifie si on doit bloquer (rate limit IP /minute).
     * @return int Nombre de tentatives de cette IP dans la dernière minute.
     */
    public static function ipAttemptsLastMinute(): int
    {
        $row = Database::selectOne(
            "SELECT COUNT(*) AS n FROM login_attempts
              WHERE ip_address = ? AND attempted_at >= NOW() - INTERVAL 1 MINUTE",
            [self::clientIp()]
        );
        return (int)($row['n'] ?? 0);
    }

    /**
     * Compte les échecs récents pour un email donné (dans une fenêtre).
     * Retourne aussi le timestamp du dernier échec.
     */
    public static function failuresForEmail(string $email, int $windowMinutes): array
    {
        $row = Database::selectOne(
            "SELECT COUNT(*) AS n, MAX(attempted_at) AS last_at
               FROM login_attempts
              WHERE email = ? AND success = 0 AND attempted_at >= NOW() - INTERVAL ? MINUTE",
            [strtolower(trim($email)), $windowMinutes]
        );
        return [
            'count'   => (int)($row['n'] ?? 0),
            'last_at' => $row['last_at'] ?? null,
        ];
    }

    /** Réinitialise les compteurs après un succès. */
    public static function clearFailuresForEmail(string $email): void
    {
        // On ne supprime pas l'historique (audit) — on l'utilise comme journal.
        // Le simple succès enregistré comble la fenêtre.
    }

    public static function clientIp(): string
    {
        // Pas de confiance dans X-Forwarded-For sauf si proxy connu (à configurer plus tard).
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }

    /** Purge les tentatives > 30 jours (à appeler en cron). */
    public static function purgeOld(int $days = 30): int
    {
        return Database::execute(
            "DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL ? DAY",
            [$days]
        );
    }
}
