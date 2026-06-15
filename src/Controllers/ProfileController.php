<?php

declare(strict_types=1);

namespace CityBus\Controllers;

use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\PasswordPolicy;
use CityBus\Core\Request;
use CityBus\Core\Session;
use CityBus\Core\TwoFactor;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

final class ProfileController extends Controller
{
    public function index(Request $request): void
    {
        $user = Auth::user();
        $tfa  = Database::selectOne("SELECT enabled FROM two_factor_secrets WHERE user_id = ?", [$user['id']]);
        $history = Database::select(
            "SELECT * FROM login_history WHERE user_id = ? ORDER BY logged_in_at DESC LIMIT 10",
            [$user['id']]
        );
        $this->view('profile/index', [
            'title'   => 'Mon profil',
            'user'    => $user,
            'has2fa'  => $tfa && (int)$tfa['enabled'] === 1,
            'history' => $history,
        ]);
    }

    public function showPassword(Request $request): void
    {
        $this->view('profile/password', ['title' => 'Changer mot de passe']);
    }

    public function updatePassword(Request $request): void
    {
        $user = Auth::user();
        $current = (string)$request->input('current_password', '');
        $new     = (string)$request->input('password', '');
        $confirm = (string)$request->input('password_confirmation', '');

        if (!password_verify($current, $user['password_hash'])) {
            $this->flash('danger', 'Mot de passe actuel incorrect.');
            back();
        }
        if ($new !== $confirm) {
            $this->flash('danger', 'La confirmation ne correspond pas.');
            back();
        }
        $errs = PasswordPolicy::validate($new, (int)$user['id']);
        if (!empty($errs)) {
            $this->flash('danger', implode(' ', $errs));
            back();
        }
        $hash = PasswordPolicy::hashAndStore((int)$user['id'], $new);
        Database::execute(
            "UPDATE users SET password_hash=?, password_changed_at=NOW(), password_expires_at=?, must_change_password=0 WHERE id=?",
            [$hash, PasswordPolicy::expirationDate(), (int)$user['id']]
        );
        Session::forget('_force_password_change');
        $this->flash('success', 'Mot de passe mis à jour.');
        redirect('profile');
    }

    public function show2faSetup(Request $request): void
    {
        $user = Auth::user();
        $row  = Database::selectOne("SELECT * FROM two_factor_secrets WHERE user_id = ?", [$user['id']]);

        if (!$row || (int)$row['enabled'] === 0) {
            // Générer un nouveau secret (mais ne pas activer encore)
            $secret = $row['secret'] ?? TwoFactor::generateSecret();
            if (!$row) {
                Database::execute(
                    "INSERT INTO two_factor_secrets (user_id, secret, enabled) VALUES (?, ?, 0)",
                    [$user['id'], $secret]
                );
            } else {
                $secret = $row['secret'];
            }
            $uri = TwoFactor::otpauthUri($secret, $user['email']);
            $qr  = $this->qrDataUri($uri);
            $this->view('profile/2fa_setup', [
                'title'   => 'Activer 2FA',
                'secret'  => $secret,
                'qr'      => $qr,
                'enabled' => false,
            ]);
            return;
        }

        $codes = json_decode($row['recovery_codes'] ?? '[]', true) ?: [];
        $this->view('profile/2fa_setup', [
            'title'    => 'Authentification 2FA',
            'enabled'  => true,
            'codesLeft'=> count($codes),
        ]);
    }

    public function enable2fa(Request $request): void
    {
        $user = Auth::user();
        $code = (string)$request->input('code', '');
        $row  = Database::selectOne("SELECT secret FROM two_factor_secrets WHERE user_id = ?", [$user['id']]);
        if (!$row) { $this->flash('danger', 'Aucun secret généré.'); redirect('profile/2fa'); }

        if (!TwoFactor::verify($row['secret'], $code)) {
            $this->flash('danger', 'Code invalide.');
            back();
        }
        // Générer 8 codes de récupération
        $plain  = TwoFactor::generateRecoveryCodes(8);
        $hashes = array_map([TwoFactor::class, 'hashRecoveryCode'], $plain);
        Database::execute(
            "UPDATE two_factor_secrets SET enabled=1, confirmed_at=NOW(), recovery_codes=? WHERE user_id=?",
            [json_encode($hashes), $user['id']]
        );
        Database::execute("UPDATE users SET two_factor_enabled=1 WHERE id=?", [$user['id']]);

        Session::forget('_force_2fa_setup');
        Session::set('_2fa_recovery_codes', $plain);
        $this->flash('success', '2FA activée. Notez vos codes de récupération.');
        redirect('profile/2fa/codes');
    }

    public function showRecoveryCodes(Request $request): void
    {
        $codes = Session::get('_2fa_recovery_codes', []);
        Session::forget('_2fa_recovery_codes');
        $this->view('profile/2fa_codes', [
            'title' => 'Codes de récupération',
            'codes' => $codes,
        ]);
    }

    public function disable2fa(Request $request): void
    {
        $user = Auth::user();
        $current = (string)$request->input('current_password', '');
        if (!password_verify($current, $user['password_hash'])) {
            $this->flash('danger', 'Mot de passe incorrect.');
            back();
        }
        Database::execute("DELETE FROM two_factor_secrets WHERE user_id=?", [$user['id']]);
        Database::execute("UPDATE users SET two_factor_enabled=0 WHERE id=?", [$user['id']]);
        $this->flash('success', '2FA désactivée.');
        redirect('profile');
    }

    private function qrDataUri(string $content): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($content)
            ->size(220)
            ->margin(8)
            ->build();
        return $result->getDataUri();
    }
}
