<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * Politique de mot de passe : longueur, mixité, historique, expiration.
 * Configurable via app_settings (security.password_*).
 */
final class PasswordPolicy
{
    /**
     * Valide un mot de passe. Retourne un tableau d'erreurs (vide = OK).
     */
    public static function validate(string $password, ?int $userId = null): array
    {
        $errors = [];
        $minLen = max(8, Setting::getInt('security.password_min_length', 12));
        if (mb_strlen($password) < $minLen) {
            $errors[] = "Le mot de passe doit contenir au moins {$minLen} caractères.";
        }
        if (Setting::getBool('security.password_require_mix', true)) {
            if (!preg_match('/[a-z]/', $password)) $errors[] = 'Au moins une minuscule requise.';
            if (!preg_match('/[A-Z]/', $password)) $errors[] = 'Au moins une majuscule requise.';
            if (!preg_match('/[0-9]/', $password)) $errors[] = 'Au moins un chiffre requis.';
            if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'Au moins un caractère spécial requis.';
        }
        // Historique
        if ($userId !== null) {
            $histN = Setting::getInt('security.password_history', 5);
            if ($histN > 0) {
                $history = Database::select(
                    "SELECT password_hash FROM password_history
                      WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
                    [$userId, $histN]
                );
                foreach ($history as $h) {
                    if (password_verify($password, $h['password_hash'])) {
                        $errors[] = "Ce mot de passe a déjà été utilisé récemment (interdit sur les {$histN} derniers).";
                        break;
                    }
                }
            }
        }
        return $errors;
    }

    /** Hash + push dans l'historique. Calcule la date d'expiration. */
    public static function hashAndStore(int $userId, string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2,
        ]);
        Database::insert(
            "INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)",
            [$userId, $hash]
        );
        // Tronquer l'historique
        $keep = max(1, Setting::getInt('security.password_history', 5));
        Database::execute(
            "DELETE FROM password_history WHERE user_id = ? AND id NOT IN (
                SELECT id FROM (SELECT id FROM password_history WHERE user_id = ? ORDER BY created_at DESC LIMIT ?) t
             )",
            [$userId, $userId, $keep]
        );
        return $hash;
    }

    public static function expirationDate(): ?string
    {
        $days = Setting::getInt('security.password_max_age', 90);
        if ($days <= 0) return null;
        return (new \DateTime())->modify("+{$days} days")->format('Y-m-d H:i:s');
    }

    public static function isExpired(?string $expiresAt): bool
    {
        if (!$expiresAt) return false;
        return strtotime($expiresAt) < time();
    }
}
