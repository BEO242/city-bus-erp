<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Core\Queue;
use CityBus\Core\StructuredLogger;
use CityBus\Jobs\SendSmsJob;
use CityBus\Jobs\SendEmailJob;

/**
 * V4 Notification orchestrator avec queue + templates rendus + tracking.
 */
final class NotificationV4Service
{
    public function queueByCode(string $templateCode, array $payload): int
    {
        $tpl = Database::selectOne("SELECT * FROM notification_templates WHERE template_key = ? AND is_active = 1", [$templateCode]);
        if (!$tpl) {
            StructuredLogger::warn('notif.template_missing', ['key' => $templateCode], 'notifications');
            return 0;
        }
        $jobClass = $tpl['channel'] === 'email' ? SendEmailJob::class : SendSmsJob::class;
        $payload['template_code'] = $templateCode;

        $rendered = $this->render($templateCode, $payload);

        $dispatchId = Database::insert('notification_dispatches', [
            'template_code'   => $templateCode,
            'channel'         => $tpl['channel'],
            'recipient_phone' => $payload['phone'] ?? null,
            'recipient_email' => $payload['email'] ?? null,
            'customer_id'     => $payload['customer_id'] ?? null,
            'pnr_id'          => $payload['pnr_id'] ?? null,
            'payload'         => json_encode($payload),
            'rendered_subject'=> $rendered['subject'] ?? null,
            'rendered_body'   => $rendered['body'] ?? null,
            'status'          => 'queued',
        ]);
        $payload['dispatch_id'] = $dispatchId;
        $payload['message'] = $rendered['body'];
        $payload['subject'] = $rendered['subject'] ?? '';
        $payload['body_html'] = $rendered['html'] ?? $rendered['body'];

        Queue::dispatch($jobClass, $payload, 'notifications');
        return $dispatchId;
    }

    public function render(string $templateCode, array $vars): array
    {
        $tpl = Database::selectOne("SELECT * FROM notification_templates WHERE template_key = ?", [$templateCode]);
        if (!$tpl) return ['subject' => '', 'body' => ''];
        $body = $tpl['body'] ?? '';
        $subject = $tpl['subject'] ?? '';
        $html = null;
        foreach ($vars as $k => $v) {
            $body = str_replace('{' . $k . '}', (string)$v, $body);
            $subject = str_replace('{' . $k . '}', (string)$v, $subject);
            if ($html) $html = str_replace('{' . $k . '}', (string)$v, $html);
        }
        return ['subject' => $subject, 'body' => $body, 'html' => $html];
    }

    public function listDispatches(int $limit = 100): array
    {
        return Database::select("SELECT * FROM notification_dispatches ORDER BY id DESC LIMIT $limit");
    }

    public function statsForChannel(string $channel, int $days = 30): array
    {
        return Database::select(
            "SELECT DATE(COALESCE(sent_at,created_at)) AS date, status, COUNT(*) AS n
             FROM notification_dispatches
             WHERE channel = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(COALESCE(sent_at,created_at)), status ORDER BY date DESC",
            [$channel, $days]
        );
    }

    public function sendReminderJ1(): int
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $rows = Database::select(
            "SELECT t.id AS trip_id, t.trip_code, t.trip_date, t.departure_time,
                    cd.name AS departure_city, ti.passenger_phone, ti.passenger_name, ti.id AS ticket_id
             FROM tickets ti
             JOIN trips t ON t.id = ti.trip_id
             JOIN bus_lines l ON l.id = t.line_id
             JOIN cities cd ON cd.id = l.departure_city_id
             WHERE t.trip_date = ? AND ti.status = 'valide' AND ti.passenger_phone IS NOT NULL",
            [$tomorrow]
        );
        $count = 0;
        foreach ($rows as $r) {
            $this->queueByCode('REMINDER_J1_SMS', [
                'phone' => $r['passenger_phone'],
                'name'  => $r['passenger_name'],
                'trip_code' => $r['trip_code'],
                'date' => date('d/m/Y', strtotime($r['trip_date'])),
                'time' => substr($r['departure_time'], 0, 5),
                'departure' => $r['departure_city'],
            ]);
            $count++;
        }
        return $count;
    }
}
