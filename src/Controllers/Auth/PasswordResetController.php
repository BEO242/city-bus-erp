<?php

declare(strict_types=1);

namespace CityBus\Controllers\Auth;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\PasswordPolicy;
use CityBus\Core\RateLimiter;
use CityBus\Core\Request;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;
use CityBus\Services\MailService;

/**
 * Workflow "mot de passe oublié" self-service.
 *
 * 1) /forgot-password (GET/POST) : saisie de l'e-mail → envoi du lien
 * 2) /reset-password?token=... (GET/POST) : nouveau mot de passe
 *
 * Les jetons sont stockés hashés (sha256) en base pour limiter la portée
 * d'une fuite de la table password_resets. Validité paramétrable via
 * security.password_reset_ttl_minutes (défaut 60 min).
 */
final class PasswordResetController extends Controller
{
    private const TOKEN_TTL_MIN_DEFAULT = 60;

    public function showRequest(Request $request): void
    {
        $this->view('auth/forgot_password', [
            'title' => 'Mot de passe oublié',
        ]);
    }

    public function sendLink(Request $request): void
    {
        // Rate limit (IP) — réutilise le compteur global
        $max = Setting::getInt('security.rate_limit_per_minute', 10);
        if (RateLimiter::ipAttemptsLastMinute() >= $max) {
            $this->flash('danger', 'Trop de tentatives. Réessayez dans une minute.');
            back();
        }

        $email = strtolower(trim((string)$request->input('email', '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('danger', 'Adresse e-mail invalide.');
            back();
        }

        // Réponse uniforme pour ne pas divulguer l'existence d'un compte
        $genericMessage = 'Si cette adresse correspond à un compte actif, un e-mail de réinitialisation a été envoyé.';

        $user = Database::selectOne(
            "SELECT id, email, first_name, last_name FROM users
              WHERE email = ? AND is_active = 1 AND deleted_at IS NULL",
            [$email]
        );

        if ($user) {
            // Purge anciens jetons non utilisés pour cet email (anti-spam)
            Database::execute(
                "DELETE FROM password_resets WHERE email = ? AND used_at IS NULL AND expires_at IS NOT NULL AND expires_at < NOW()",
                [$email]
            );

            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $ttlMin    = max(15, Setting::getInt('security.password_reset_ttl_minutes', self::TOKEN_TTL_MIN_DEFAULT));
            $expiresAt = (new \DateTime())->modify("+{$ttlMin} minutes")->format('Y-m-d H:i:s');

            Database::insert(
                "INSERT INTO password_resets (email, token, created_at, expires_at, ip_address)
                 VALUES (?, ?, NOW(), ?, ?)",
                [$email, $tokenHash, $expiresAt, RateLimiter::clientIp()]
            );

            $resetUrl = url('reset-password?token=' . $token);
            $body     = "Bonjour " . trim($user['first_name'] . ' ' . $user['last_name']) . ",\r\n\r\n"
                      . "Vous avez demandé la réinitialisation de votre mot de passe sur CITY BUS.\r\n"
                      . "Cliquez sur le lien suivant (valide {$ttlMin} minutes) :\r\n\r\n"
                      . $resetUrl . "\r\n\r\n"
                      . "Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.\r\n\r\n"
                      . "— L'équipe CITY BUS";

            try {
                (new MailService())->send($email, '[CITY BUS] Réinitialisation de votre mot de passe', $body);
            } catch (\Throwable $e) {
                \CityBus\Core\Logger::warning('password_reset.mail_failed: ' . $e->getMessage());
            }

            AuditLog::record('password.reset_request', 'user', (int)$user['id'], ['email' => $email]);
        } else {
            // Trace tentative sans révéler l'absence du compte
            AuditLog::record('password.reset_request_unknown', null, null, ['email' => $email]);
        }

        $this->flash('success', $genericMessage);
        redirect('login');
    }

    public function showReset(Request $request): void
    {
        $token = trim((string)$request->input('token', ''));
        if ($token === '') {
            $this->flash('danger', 'Lien de réinitialisation invalide.');
            redirect('login');
        }
        // Vérifier la validité avant d'afficher le formulaire
        $row = $this->findValidToken($token);
        if (!$row) {
            $this->flash('danger', 'Lien expiré ou invalide. Demandez un nouvel envoi.');
            redirect('forgot-password');
        }

        $this->view('auth/reset_password', [
            'title' => 'Choisir un nouveau mot de passe',
            'token' => $token,
        ]);
    }

    public function reset(Request $request): void
    {
        $token    = trim((string)$request->input('token', ''));
        $password = (string)$request->input('password', '');
        $confirm  = (string)$request->input('password_confirmation', '');

        $row = $this->findValidToken($token);
        if (!$row) {
            $this->flash('danger', 'Lien expiré ou invalide.');
            redirect('forgot-password');
        }

        if ($password !== $confirm) {
            $this->flash('danger', 'La confirmation ne correspond pas.');
            back();
        }

        $user = Database::selectOne(
            "SELECT id FROM users WHERE email = ? AND is_active = 1 AND deleted_at IS NULL",
            [$row['email']]
        );
        if (!$user) {
            $this->flash('danger', 'Compte introuvable ou désactivé.');
            redirect('login');
        }

        $errs = PasswordPolicy::validate($password, (int)$user['id']);
        if (!empty($errs)) {
            $this->flash('danger', implode(' ', $errs));
            back();
        }

        $hash = PasswordPolicy::hashAndStore((int)$user['id'], $password);
        Database::execute(
            "UPDATE users
                SET password_hash = ?,
                    password_changed_at = NOW(),
                    password_expires_at = ?,
                    must_change_password = 0,
                    failed_login_count = 0,
                    locked_until = NULL
              WHERE id = ?",
            [$hash, PasswordPolicy::expirationDate(), (int)$user['id']]
        );

        // Marquer le jeton consommé + invalider tous les autres jetons en attente
        Database::execute("UPDATE password_resets SET used_at = NOW() WHERE id = ?", [(int)$row['id']]);
        Database::execute(
            "UPDATE password_resets SET used_at = NOW()
              WHERE email = ? AND used_at IS NULL AND id <> ?",
            [$row['email'], (int)$row['id']]
        );

        AuditLog::record('password.reset_complete', 'user', (int)$user['id'], ['email' => $row['email']]);

        $this->flash('success', 'Mot de passe mis à jour. Vous pouvez vous connecter.');
        redirect('login');
    }

    /**
     * Retourne la ligne password_resets si le jeton (en clair) est valide.
     */
    private function findValidToken(string $token): ?array
    {
        if ($token === '') return null;
        $hash = hash('sha256', $token);
        $row  = Database::selectOne(
            "SELECT * FROM password_resets
              WHERE token = ?
                AND used_at IS NULL
                AND (expires_at IS NULL OR expires_at > NOW())
              ORDER BY id DESC LIMIT 1",
            [$hash]
        );
        return $row ?: null;
    }
}
