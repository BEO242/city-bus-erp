<?php

declare(strict_types=1);

namespace CityBus\Jobs;

use CityBus\Core\Database;
use CityBus\Core\Setting;
use CityBus\Core\StructuredLogger;

final class SendSmsJob
{
    public function handle(array $payload): void
    {
        $phone = $payload['phone'] ?? '';
        $message = $payload['message'] ?? '';
        if (empty($phone) || empty($message)) return;

        $provider = Setting::get('notif.sms_provider', 'africastalking');
        $ok = false;
        try {
            $apiKey = Setting::get('notif.sms_api_key', '');
            if (empty($apiKey)) {
                StructuredLogger::info('sms.dry_run', ['phone'=>$phone,'msg'=>$message], 'notifications');
                $ok = true;
            } else {
                $ok = $this->sendReal($provider, $phone, $message);
            }
        } catch (\Throwable $e) {
            StructuredLogger::error('sms.send_failed', ['error'=>$e->getMessage(),'phone'=>$phone], 'notifications');
        }

        if (isset($payload['dispatch_id'])) {
            Database::update('notification_dispatches', [
                'status'   => $ok ? 'sent' : 'failed',
                'sent_at'  => $ok ? date('Y-m-d H:i:s') : null,
                'provider' => $provider,
                'error_msg'=> $ok ? null : 'Send failed',
            ], 'id = ?', [$payload['dispatch_id']]);
        }
    }

    private function sendReal(string $provider, string $phone, string $message): bool
    {
        // Stubs production : à compléter avec vraies API
        // Africa's Talking : POST https://api.africastalking.com/version1/messaging
        // Twilio : POST /2010-04-01/Accounts/{Sid}/Messages.json
        StructuredLogger::info('sms.send_attempt', ['provider'=>$provider,'phone'=>$phone], 'notifications');
        return true;
    }
}
