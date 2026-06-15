<?php

declare(strict_types=1);

namespace CityBus\Controllers\Admin;

use CityBus\Controllers\Controller;
use CityBus\Core\Auth;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Core\Setting;
use CityBus\Models\AuditLog;

final class SettingController extends Controller
{
    private const CATEGORIES = [
        'company'     => ['label' => 'Identité société',     'icon' => 'building-2',      'color' => 'text-violet-600'],
        'security'    => ['label' => 'Sécurité',              'icon' => 'shield-check',    'color' => 'text-rose-600'],
        'billetterie' => ['label' => 'Billetterie',           'icon' => 'ticket',          'color' => 'text-sky-600'],
        'caisse'      => ['label' => 'Caisse',                'icon' => 'wallet',          'color' => 'text-emerald-600'],
        'voyage'      => ['label' => 'Voyages',               'icon' => 'bus',             'color' => 'text-orange-600'],
        'impression'  => ['label' => 'Impression / PDF',      'icon' => 'printer',         'color' => 'text-slate-600'],
        'rh'          => ['label' => 'Ressources humaines',   'icon' => 'users',           'color' => 'text-teal-600'],
        'mail'        => ['label' => 'Notifications e-mail',  'icon' => 'mail',            'color' => 'text-blue-600'],
        'sms'         => ['label' => 'Notifications SMS',     'icon' => 'message-square',  'color' => 'text-green-600'],
        'integration' => ['label' => 'Intégrations / API',    'icon' => 'webhook',         'color' => 'text-indigo-600'],
        'backup'      => ['label' => 'Sauvegardes',           'icon' => 'database',        'color' => 'text-amber-600'],
        'maintenance' => ['label' => 'Mode maintenance',      'icon' => 'wrench',          'color' => 'text-red-600'],
        'audit'       => ['label' => 'Audit & journaux',      'icon' => 'file-search',     'color' => 'text-slate-500'],
    ];

    private const DEFAULTS = [
        'security.session_lifetime'          => '120',
        'security.password_min_length'       => '12',
        'security.password_require_mix'      => '1',
        'security.password_history'          => '5',
        'security.password_max_age'          => '90',
        'security.login_max_attempts'        => '5',
        'security.login_lockout_minutes'     => '15',
        'security.rate_limit_per_minute'     => '10',
        'security.two_factor_required'       => '0',
        'security.two_factor_required_admin' => '1',
        'security.password_reset_ttl_minutes' => '60',
        'billetterie.ticket_prefix'          => 'CB',
        'billetterie.ticket_expiration_h'    => '24',
        'billetterie.allow_seat_choice'      => '1',
        'billetterie.cancellation_delay_h'   => '2',
        'billetterie.refund_pct'             => '80',
        'billetterie.print_on_sale'          => '1',
        'caisse.rounding'                    => '5',
        'caisse.alert_threshold'             => '100000',
        'caisse.require_open_session'        => '1',
        'caisse.session_max_hours'           => '12',
        'caisse.auto_close'                  => '0',
        'caisse.daily_target'                => '2500000',
        'backup.enabled'                     => '0',
        'backup.schedule'                    => 'daily',
        'backup.retention_days'              => '30',
        'maintenance.enabled'                => '0',
        'audit.retention_days'               => '365',
        'voyage.checkin_open_minutes'        => '60',
        'voyage.allow_overbooking'           => '0',
        'voyage.overbooking_pct'             => '10',
        'voyage.auto_close_minutes'          => '30',
        'voyage.incident_notify_admin'       => '1',
        'voyage.min_driver_rest_hours'       => '8',
        'print.receipt_copies'               => '1',
        'print.ticket_logo_enabled'          => '1',
        'print.preprint_watermark'           => '0',
        'print.qr_size'                      => '200',
        'rh.cnss_rate_employee'              => '4',
        'rh.cnss_rate_employer'              => '16',
        'rh.irpp_enabled'                    => '0',
        'rh.overtime_rate_multiplier'        => '1.5',
        'rh.medical_cert_alert_days'         => '30',
        'rh.license_alert_days'              => '45',
        'flotte.tech_control_alert_days'     => '30',
        'flotte.insurance_alert_days'        => '30',
        'sms.enabled'                        => '0',
        'sms.notify_ticket_sold'             => '0',
        'sms.notify_trip_departure'          => '0',
        'sms.notify_trip_delay'              => '0',
        'integration.api_enabled'            => '0',
        'reporting.otp_tolerance_minutes'    => '15',
        'integration.webhook_url'            => '',
        'integration.webhook_secret'         => '',
        'integration.webhook_events'         => '',
    ];

    public function index(Request $request): void
    {
        $cat = trim((string)$request->input('cat', 'company'));
        if (!isset(self::CATEGORIES[$cat])) $cat = 'company';

        $rows = Database::select(
            "SELECT * FROM app_settings WHERE category = ? ORDER BY sort_order, setting_key",
            [$cat]
        );

        $counts = Database::select(
            "SELECT category, COUNT(*) AS n FROM app_settings GROUP BY category"
        );
        $catCounts = array_column($counts, 'n', 'category');

        $logoPath = Setting::getString('company.logo_path', '');

        $this->view('admin/settings/index', [
            'title'      => 'Paramètres',
            'categories' => self::CATEGORIES,
            'cat'        => $cat,
            'settings'   => $rows,
            'catCounts'  => $catCounts,
            'logoPath'   => $logoPath,
        ]);
    }

    public function update(Request $request): void
    {
        if (!Auth::can('admin.settings.edit')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        $cat = trim((string)$request->input('cat', 'company'));
        if (!isset(self::CATEGORIES[$cat])) {
            $this->flash('danger', 'Catégorie invalide.'); back();
        }

        $rows = Database::select(
            "SELECT setting_key, setting_type FROM app_settings WHERE category = ?",
            [$cat]
        );
        // Lire les valeurs soumises depuis le tableau 'settings[key]'
        // (la notation tableau préserve les points dans les clés, contrairement aux champs top-level
        //  où PHP remplace '.' par '_' lors du parsing de $_POST)
        $submitted = $_POST['settings'] ?? [];
        $values = [];
        foreach ($rows as $r) {
            $key = $r['setting_key'];
            if ($r['setting_type'] === 'bool') {
                // La checkbox envoie '1' si cochée, le hidden '0' si décochée
                $values[$key] = isset($submitted[$key]) ? (string)$submitted[$key] : '0';
            } else {
                $raw = $submitted[$key] ?? null;
                if ($r['setting_type'] === 'secret' && ($raw === null || $raw === '')) continue;
                if ($raw !== null) $values[$key] = (string)$raw;
            }
        }
        Setting::bulkUpdate($values, Auth::id());
        Setting::flushCache();

        $label = self::CATEGORIES[$cat]['label'];
        AuditLog::record('setting.update', 'setting', null, ['category' => $cat, 'label' => $label]);
        $this->flash('success', "Paramètres « {$label} » enregistrés.");
        redirect('admin/settings?cat=' . urlencode($cat));
    }

    /** Test webhook (AJAX) — envoie un événement factice à integration.webhook_url */
    public function testWebhook(Request $request): void
    {
        if (!Auth::can('admin.settings.edit')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Accès refusé.']);
            exit;
        }
        header('Content-Type: application/json');

        $url = trim(Setting::getString('integration.webhook_url', ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['ok' => false, 'message' => 'URL webhook non configurée ou invalide.']);
            exit;
        }

        try {
            \CityBus\Services\WebhookService::dispatch('webhook.test', [
                'message'  => 'Test depuis l\'interface CITY BUS',
                'sent_by'  => Auth::user()['email'] ?? 'admin',
                'sent_at'  => date('c'),
            ]);
            \CityBus\Models\AuditLog::record('settings.webhook.test', null, null, ['url' => $url]);
            echo json_encode(['ok' => true, 'message' => "Événement test envoyé à {$url}. Consultez les logs pour le résultat."]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
        }
        exit;
    }

    /** Test connexion SMTP (AJAX) — envoie un vrai e-mail de test */
    public function testSmtp(Request $request): void
    {
        if (!Auth::can('admin.settings.edit')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Accès refusé.']);
            exit;
        }
        header('Content-Type: application/json');
        $to = trim((string)$request->input('to', ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'message' => 'Adresse e-mail invalide.']); exit;
        }

        $mailer = new \CityBus\Services\MailService();
        if (!$mailer->isConfigured()) {
            echo json_encode(['ok' => false, 'message' => 'SMTP non configuré (host ou expéditeur manquant).']); exit;
        }

        $subject = '[CITY BUS] Test SMTP';
        $body = "Cet e-mail confirme que la configuration SMTP de votre instance CITY BUS fonctionne.\r\n\r\n"
              . "Envoyé le " . date('d/m/Y H:i:s') . " par " . (Auth::user()['email'] ?? 'admin') . ".";
        $ok = $mailer->send($to, $subject, $body, false);
        if ($ok) {
            \CityBus\Models\AuditLog::record('settings.smtp.test', null, null, ['to' => $to, 'result' => 'ok']);
            echo json_encode(['ok' => true, 'message' => "E-mail de test envoyé à {$to}."]);
        } else {
            echo json_encode(['ok' => false, 'message' => 'Envoi échoué. Consultez le log applicatif pour le détail.']);
        }
        exit;
    }

    /** Export JSON (non-secrets) */
    public function export(Request $request): void
    {
        if (!Auth::can('admin.settings.edit')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        $rows = Database::select(
            "SELECT setting_key, category, setting_type, setting_value, label, description, sort_order
             FROM app_settings WHERE is_secret = 0 ORDER BY category, sort_order"
        );
        $date = date('Y-m-d');
        header('Content-Type: application/json; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"citybus_settings_{$date}.json\"");
        echo json_encode([
            'exported_at' => date('c'),
            'exported_by' => Auth::user()['email'] ?? 'unknown',
            'version'     => '1.0',
            'settings'    => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Import JSON */
    public function import(Request $request): void
    {
        if (!Auth::can('admin.settings.edit')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        $file = $_FILES['settings_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'Fichier invalide.'); redirect('admin/settings');
        }
        if (!str_ends_with(strtolower((string)($file['name'] ?? '')), '.json')) {
            $this->flash('danger', 'Format JSON requis.'); redirect('admin/settings');
        }
        $data = json_decode((string)@file_get_contents($file['tmp_name']), true);
        if (!$data || !isset($data['settings']) || !is_array($data['settings'])) {
            $this->flash('danger', 'Fichier JSON malformé.'); redirect('admin/settings');
        }
        $count = 0;
        foreach ($data['settings'] as $s) {
            $key = $s['setting_key'] ?? null;
            $val = $s['setting_value'] ?? null;
            if (!$key || !is_string($key)) continue;
            $exists = Database::selectOne(
                "SELECT setting_type FROM app_settings WHERE setting_key = ?", [$key]
            );
            if (!$exists) continue;
            Database::execute(
                "UPDATE app_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?",
                [(string)$val, Auth::id(), $key]
            );
            $count++;
        }
        Setting::flushCache();
        $this->flash('success', "{$count} paramètre(s) importé(s).");
        redirect('admin/settings');
    }

    /** Reset catégorie aux valeurs par défaut */
    public function resetCategory(Request $request): void
    {
        if (!Auth::can('admin.settings.edit')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        $cat = trim((string)$request->input('cat', ''));
        if (!isset(self::CATEGORIES[$cat])) {
            $this->flash('danger', 'Catégorie invalide.'); back();
        }
        $count = 0;
        foreach (self::DEFAULTS as $key => $default) {
            if (!str_starts_with($key, $cat . '.')) continue;
            Database::execute(
                "UPDATE app_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?",
                [$default, Auth::id(), $key]
            );
            $count++;
        }
        Setting::flushCache();
        $label = self::CATEGORIES[$cat]['label'];
        AuditLog::record('setting.reset', 'setting', null, ['category' => $cat, 'label' => $label, 'count' => $count]);
        $this->flash('success', "Catégorie « {$label} » réinitialisée ({$count} valeur(s)).");
        redirect('admin/settings?cat=' . urlencode($cat));
    }

    /** Upload logo société */
    public function uploadLogo(Request $request): void
    {
        if (!Auth::can('admin.settings.edit')) {
            $this->flash('danger', 'Permission refusée.'); back();
        }
        $file = $_FILES['logo'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->flash('danger', 'Fichier logo manquant ou invalide.'); redirect('admin/settings?cat=company');
        }
        $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml', 'image/webp'];
        $finfo   = new \finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            $this->flash('danger', 'Format non supporté (PNG, JPEG, GIF, SVG, WEBP).');
            redirect('admin/settings?cat=company');
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            $this->flash('danger', 'Fichier trop lourd (max 2 Mo).');
            redirect('admin/settings?cat=company');
        }
        $ext = match($mime) {
            'image/jpeg'    => 'jpg',
            'image/gif'     => 'gif',
            'image/svg+xml' => 'svg',
            'image/webp'    => 'webp',
            default         => 'png',
        };
        $dir = __DIR__ . '/../../../public/assets/img/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $old = Setting::getString('company.logo_path', '');
        if ($old && str_starts_with($old, 'assets/img/logo_')) {
            @unlink(__DIR__ . '/../../../public/' . $old);
        }
        $filename = 'logo_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            $this->flash('danger', "Erreur lors de l'enregistrement.");
            redirect('admin/settings?cat=company');
        }
        Setting::set('company.logo_path', 'assets/img/' . $filename, Auth::id());
        Setting::flushCache();
        $this->flash('success', 'Logo mis à jour.');
        redirect('admin/settings?cat=company');
    }
}

