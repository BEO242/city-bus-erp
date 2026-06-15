<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Queue;
use CityBus\Core\StructuredLogger;

final class WebhookDispatcher
{
    public function fire(string $eventType, array $payload): int
    {
        $clients = Database::select(
            "SELECT id, webhook_url, webhook_secret, webhook_events FROM oauth_clients
             WHERE webhook_url IS NOT NULL AND webhook_url <> ''"
        );
        $count = 0;
        foreach ($clients as $c) {
            $events = $c['webhook_events'] ? json_decode($c['webhook_events'], true) : [];
            if ($events && !in_array($eventType, $events, true) && !in_array('*', $events, true)) continue;

            $body = json_encode([
                'event' => $eventType,
                'timestamp' => date('c'),
                'data' => $payload,
            ]);
            $signature = hash_hmac('sha256', $body, $c['webhook_secret'] ?? '');

            Database::insert('webhooks_outgoing', [
                'client_id'       => $c['id'],
                'event_type'      => $eventType,
                'url'             => $c['webhook_url'],
                'payload'         => $body,
                'next_attempt_at' => date('Y-m-d H:i:s'),
            ]);
            $count++;
        }
        return $count;
    }

    public function processPending(int $limit = 50): int
    {
        $pending = Database::select(
            "SELECT * FROM webhooks_outgoing
             WHERE status IN ('pending','retrying') AND next_attempt_at <= NOW()
             ORDER BY id ASC LIMIT $limit"
        );
        $count = 0;
        foreach ($pending as $wh) {
            $this->deliver($wh);
            $count++;
        }
        return $count;
    }

    private function deliver(array $wh): void
    {
        $client = Database::selectOne("SELECT webhook_secret FROM oauth_clients WHERE id = ?", [$wh['client_id']]);
        $secret = $client['webhook_secret'] ?? '';
        $signature = hash_hmac('sha256', $wh['payload'], $secret);

        $ch = curl_init($wh['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $wh['payload'],
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-CityBus-Signature: ' . $signature,
                'X-CityBus-Event: ' . $wh['event_type'],
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $attempts = (int)$wh['attempts'] + 1;
        if ($status >= 200 && $status < 300) {
            Database::update('webhooks_outgoing', [
                'status' => 'sent', 'attempts' => $attempts,
                'response_status' => $status, 'response_body' => mb_substr((string)$response, 0, 1000),
                'delivered_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$wh['id']]);
        } elseif ($attempts < 5) {
            $delay = min(3600, 60 * (2 ** $attempts));
            Database::update('webhooks_outgoing', [
                'status' => 'retrying', 'attempts' => $attempts,
                'response_status' => $status, 'response_body' => mb_substr((string)$response, 0, 1000),
                'next_attempt_at' => date('Y-m-d H:i:s', time() + $delay),
            ], 'id = ?', [$wh['id']]);
        } else {
            Database::update('webhooks_outgoing', [
                'status' => 'failed', 'attempts' => $attempts,
                'response_status' => $status, 'response_body' => mb_substr((string)$response, 0, 1000),
            ], 'id = ?', [$wh['id']]);
        }
    }
}
