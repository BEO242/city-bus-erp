<?php

declare(strict_types=1);

namespace CityBus\Jobs;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Core\StructuredLogger;

final class SendEmailJob
{
    public function handle(array $payload): void
    {
        $email = $payload['email'] ?? '';
        $subject = $payload['subject'] ?? '';
        $body = $payload['body_html'] ?? $payload['message'] ?? '';
        if (empty($email)) return;

        $from = Setting::get('notif.from_email', 'noreply@citybus.cg');
        $name = Setting::get('notif.from_name', 'City Bus');
        $headers = "From: $name <$from>\r\nReply-To: $from\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        $ok = @mail($email, $subject, $body, $headers);

        StructuredLogger::info('email.sent', ['to'=>$email,'ok'=>$ok], 'notifications');

        if (isset($payload['dispatch_id'])) {
            Database::update('notification_dispatches', [
                'status'  => $ok ? 'sent' : 'failed',
                'sent_at' => $ok ? date('Y-m-d H:i:s') : null,
                'provider'=> Setting::get('notif.email_provider', 'smtp'),
                'error_msg' => $ok ? null : 'mail() failed',
            ], 'id = ?', [$payload['dispatch_id']]);
        }
    }
}
