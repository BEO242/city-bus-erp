<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Logger;

/**
 * Service de notifications — abstraction email + SMS.
 *
 * Les canaux concrets sont injectables. Par défaut, tout est loggué
 * dans `storage/logs/notifications.log` et persisté dans la table
 * `notifications` pour audit. Les drivers réels (SMTP, SMS gateway)
 * peuvent être branchés ultérieurement sans toucher aux appelants.
 */
final class NotificationService
{
    /** @var callable|null fonction(email, subject, body) */
    private $mailDriver = null;
    /** @var callable|null fonction(phone, message) */
    private $smsDriver  = null;

    public function setMailDriver(callable $fn): void { $this->mailDriver = $fn; }
    public function setSmsDriver(callable $fn): void  { $this->smsDriver  = $fn; }

    public function sendEmail(string $to, string $subject, string $body, array $meta = []): bool
    {
        $ok = true;
        try {
            if ($this->mailDriver !== null) {
                $ok = (bool)($this->mailDriver)($to, $subject, $body);
            } else {
                Logger::info("[email/stub] to={$to} subject={$subject}");
            }
        } catch (\Throwable $e) {
            Logger::error('Email send failed: ' . $e->getMessage());
            $ok = false;
        }
        $this->persist('email', $to, $subject, $body, $ok, $meta);
        return $ok;
    }

    public function sendSms(string $phone, string $message, array $meta = []): bool
    {
        $ok = true;
        try {
            if ($this->smsDriver !== null) {
                $ok = (bool)($this->smsDriver)($phone, $message);
            } else {
                Logger::info("[sms/stub] to={$phone} msg=" . mb_substr($message, 0, 80));
            }
        } catch (\Throwable $e) {
            Logger::error('SMS send failed: ' . $e->getMessage());
            $ok = false;
        }
        $this->persist('sms', $phone, null, $message, $ok, $meta);
        return $ok;
    }

    /**
     * Envoie une notification basée sur un template (GAP-19).
     * Le body et le subject sont chargés depuis la table notification_templates et
     * les variables {{xxx}} substituées avec $vars.
     */
    public function sendFromTemplate(string $key, string $channel, string $recipient, array $vars = [], array $opts = []): bool
    {
        $tpl = $this->resolveTemplate($key, $channel);
        if (!$tpl || !$tpl['is_active']) {
            Logger::info("Template inactif: $key/$channel");
            return false;
        }
        $body    = $this->substitute($tpl['body'], $vars);
        $subject = $tpl['subject'] ? $this->substitute($tpl['subject'], $vars) : null;

        $logId = (int)Database::insert(
            "INSERT INTO notification_log
                (template_id, template_key, channel, recipient, customer_id,
                 subject, body, status, related_table, related_id)
             VALUES (?,?,?,?,?,?,?, 'queued', ?, ?)",
            [
                (int)$tpl['id'], $key, $channel, $recipient,
                $opts['customer_id'] ?? null,
                $subject, $body,
                $opts['related_table'] ?? null, $opts['related_id'] ?? null,
            ]
        );

        $ok = match ($channel) {
            'sms', 'whatsapp' => $this->sendSms($recipient, $body, $opts),
            'email'           => $this->sendEmail($recipient, $subject ?? '(sans objet)', $body, $opts),
            default           => false,
        };

        Database::execute(
            "UPDATE notification_log SET status=?, sent_at=" . ($ok ? 'NOW()' : 'NULL') . " WHERE id=?",
            [$ok ? 'sent' : 'failed', $logId]
        );
        return $ok;
    }

    public function resolveTemplate(string $key, string $channel): ?array
    {
        return Database::selectOne(
            "SELECT * FROM notification_templates WHERE template_key = ? AND channel = ? LIMIT 1",
            [$key, $channel]
        );
    }

    public function substitute(string $body, array $vars): string
    {
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function ($m) use ($vars) {
            return isset($vars[$m[1]]) ? (string)$vars[$m[1]] : $m[0];
        }, $body);
    }

    /** Notifie tous les destinataires d'un événement (ex: maintenance échue, voyage incident). */
    public function notifyEvent(string $event, array $context = []): void
    {
        // Hook simple : à câbler ultérieurement avec table notification_subscriptions
        Logger::info("[event] {$event} " . json_encode($context, JSON_UNESCAPED_UNICODE));
    }

    /** Vérifie les expirations à venir (permis, visites techniques, assurances). */
    public function scanExpirations(int $daysAhead = 30): array
    {
        $alerts = [];
        $rows = Database::select(
            "SELECT id, license_number, license_expires_at, first_name, last_name
               FROM drivers
              WHERE license_expires_at IS NOT NULL
                AND license_expires_at <= DATE_ADD(CURDATE(), INTERVAL ? DAY)",
            [$daysAhead]
        );
        foreach ($rows as $r) {
            $alerts[] = [
                'type'    => 'driver_license',
                'id'      => (int)$r['id'],
                'expires' => $r['license_expires_at'],
                'label'   => "Permis {$r['first_name']} {$r['last_name']} expire le {$r['license_expires_at']}",
            ];
        }
        return $alerts;
    }

    private function persist(string $channel, string $recipient, ?string $subject, string $body, bool $ok, array $meta): void
    {
        try {
            Database::insert(
                "INSERT INTO notifications (channel, recipient, subject, body, status, meta, created_at)
                 VALUES (?,?,?,?,?,?, NOW())",
                [
                    $channel,
                    $recipient,
                    $subject,
                    mb_substr($body, 0, 4000),
                    $ok ? 'sent' : 'failed',
                    json_encode($meta, JSON_UNESCAPED_UNICODE),
                ]
            );
        } catch (\Throwable $e) {
            // Table peut ne pas exister : ne pas bloquer
            Logger::warning('Notification persist failed: ' . $e->getMessage());
        }
    }
}
