<?php

declare(strict_types=1);

namespace CityBus\Controllers\Auth;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\PasswordPolicy;
use CityBus\Core\RateLimiter;
use CityBus\Core\Request;
use CityBus\Core\Session;
use CityBus\Core\Setting;
use CityBus\Core\TwoFactor;
use CityBus\Models\AuditLog;

final class LoginController extends Controller
{
    public function showLogin(Request $request): void
    {
        $this->view('auth/login');
    }

    public function login(Request $request): void
    {
        // Rate limit IP (10 req/min par défaut, configurable)
        $ipMax = Setting::getInt('security.rate_limit_per_minute', 10);
        if (RateLimiter::ipAttemptsLastMinute() >= $ipMax) {
            $this->flash('danger', 'Trop de tentatives. Réessayez dans une minute.');
            back();
        }

        $data = $this->validate($request, [
            'email'    => 'required|email',
            'password' => 'required|min:1',
        ], [
            'email' => 'email', 'password' => 'mot de passe'
        ]);

        $email = strtolower(trim((string)$data['email']));
        $result = Auth::attempt($email, (string)$data['password']);
        RateLimiter::record($email, $result === true || $result === '2fa');

        if ($result === 'locked') {
            AuditLog::record('login.locked', null, null, ['email' => $email]);
            Session::set('_old', ['email' => $email]);
            $this->flash('danger', 'Compte temporairement verrouillé suite à plusieurs échecs. Réessayez plus tard.');
            back();
        }
        if ($result === false) {
            AuditLog::record('login.failed', null, null, ['email' => $email, 'reason' => 'invalid_credentials']);
            Session::set('_errors', ['email' => ['Identifiants invalides.']]);
            Session::set('_old', ['email' => $email]);
            $this->flash('danger', 'Identifiants incorrects.');
            back();
        }
        if ($result === '2fa') {
            redirect('login/2fa');
        }

        // Connecté complètement
        $this->postLoginRedirect();
    }

    public function show2fa(Request $request): void
    {
        $userId = Auth::pendingTwoFactorUserId();
        if (!$userId) redirect('login');
        $this->view('auth/2fa');
    }

    public function verify2fa(Request $request): void
    {
        $userId = Auth::pendingTwoFactorUserId();
        if (!$userId) redirect('login');

        $code = preg_replace('/\s+/', '', (string)$request->input('code', ''));
        $secret = Database::selectOne(
            "SELECT secret, recovery_codes FROM two_factor_secrets WHERE user_id = ? AND enabled = 1",
            [$userId]
        );
        if (!$secret) {
            $this->flash('danger', '2FA non configuré pour ce compte. Contactez l’administrateur.');
            Auth::logout();
            redirect('login');
        }

        $ok = false;
        if (strlen($code) === 6 && ctype_digit($code)) {
            $ok = TwoFactor::verify($secret['secret'], $code);
        } elseif ($code !== '') {
            // Tente comme code de récupération (8 hex)
            $codes = json_decode($secret['recovery_codes'] ?? '[]', true) ?: [];
            $hash  = TwoFactor::hashRecoveryCode($code);
            if (in_array($hash, $codes, true)) {
                $ok = true;
                // Consommer le code de récupération
                $codes = array_values(array_diff($codes, [$hash]));
                Database::execute(
                    "UPDATE two_factor_secrets SET recovery_codes = ? WHERE user_id = ?",
                    [json_encode($codes), $userId]
                );
            }
        }

        if (!$ok) {
            $this->flash('danger', 'Code 2FA invalide.');
            back();
        }

        Auth::completeLogin($userId);
        $this->postLoginRedirect();
    }

    private function postLoginRedirect(): void
    {
        $user = Auth::user();
        // Mot de passe expiré ?
        if (!empty($user['password_expires_at']) && PasswordPolicy::isExpired($user['password_expires_at'])) {
            Session::set('_force_password_change', true);
            $this->flash('warning', 'Votre mot de passe a expiré. Veuillez le changer.');
            redirect('profile/password');
        }
        if ((int)($user['must_change_password'] ?? 0) === 1) {
            Session::set('_force_password_change', true);
            $this->flash('warning', 'Vous devez changer votre mot de passe avant de continuer.');
            redirect('profile/password');
        }

        // 2FA obligatoire (politique globale ou ciblée admin) mais pas encore activée
        if ((int)($user['two_factor_enabled'] ?? 0) !== 1) {
            $forceAll   = Setting::getBool('security.two_factor_required', false);
            $forceAdmin = Setting::getBool('security.two_factor_required_admin', false);
            $isAdmin    = ($user['role'] ?? '') === 'admin';
            if ($forceAll || ($forceAdmin && $isAdmin)) {
                Session::set('_force_2fa_setup', true);
                $this->flash('warning', 'L’authentification à deux facteurs est obligatoire pour votre compte. Veuillez la configurer.');
                redirect('profile/2fa');
            }
        }

        $intended = Session::get('_intended', url('dashboard'));
        Session::forget('_intended');
        $this->flash('success', 'Bienvenue !');
        AuditLog::record('login.success', 'user', (int)Auth::id(), ['email' => Auth::user()['email'] ?? '']);
        redirect($intended);
    }

    public function logout(Request $request): void
    {
        $userId = Auth::id();
        $email  = Auth::user()['email'] ?? '';
        AuditLog::record('logout', 'user', $userId, ['email' => $email]);
        Auth::logout();
        $this->flash('info', 'Vous avez été déconnecté.');
        redirect('login');
    }
}
