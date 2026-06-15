<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Logger;
use CityBus\Core\Setting;

/**
 * Émetteur de webhooks sortants vers une URL configurée (integration.webhook_url).
 *
 * - Signature HMAC SHA-256 du corps JSON, envoyée dans l'en-tête X-CityBus-Signature.
 * - L'événement doit être listé dans integration.webhook_events (un par ligne, ou csv).
 * - Si l'URL est vide ou l'événement n'est pas abonné → no-op silencieux.
 * - Timeout 5 s, fire-and-forget : un échec est loggué, jamais propagé.
 */
final class WebhookService
{
    public static function dispatch(string $event, array $payload): void
    {
        $url = trim(Setting::getString('integration.webhook_url', ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }
        $events = self::parseEventList(Setting::getString('integration.webhook_events', ''));
        if (!empty($events) && !in_array($event, $events, true) && !in_array('*', $events, true)) {
            return;
        }
        $secret = Setting::getString('integration.webhook_secret', '');

        $body = json_encode([
            'event'      => $event,
            'occurred_at'=> date('c'),
            'data'       => $payload,
        ], JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            Logger::warning('WebhookService: encodage JSON échoué', ['event' => $event]);
            return;
        }

        $signature = $secret !== '' ? hash_hmac('sha256', $body, $secret) : '';
        $headers = [
            'Content-Type: application/json',
            'User-Agent: CityBus-Webhook/1.0',
            'X-CityBus-Event: ' . $event,
        ];
        if ($signature !== '') {
            $headers[] = 'X-CityBus-Signature: sha256=' . $signature;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($response === false || $status >= 400) {
            Logger::warning('WebhookService: livraison échouée', [
                'event' => $event, 'url' => $url, 'status' => $status, 'error' => $err,
            ]);
        }
    }

    /** @return string[] */
    private static function parseEventList(string $raw): array
    {
        $raw = str_replace([",", ";"], "\n", $raw);
        $list = array_filter(array_map('trim', explode("\n", $raw)), fn($s) => $s !== '');
        return array_values(array_unique($list));
    }
}
