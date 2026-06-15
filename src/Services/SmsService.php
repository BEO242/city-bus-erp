<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Logger;
use CityBus\Core\Setting;

/**
 * Façade SMS minimale supportant deux modes :
 * - "log"      : enregistre le SMS dans les logs (utile en dev/test, pas d'envoi réel).
 * - "http_get" : effectue un GET sur sms.api_key (qui doit contenir une URL templatée
 *                avec les placeholders {phone}, {message}, {sender}).
 *
 * Les paramètres lus :
 *   sms.enabled (bool), sms.provider (log|http_get), sms.api_key (URL ou clé),
 *   sms.sender_id (string).
 *
 * Désactivée → no-op. Toute erreur est loggée et ne propage pas.
 */
final class SmsService
{
    public static function send(string $phone, string $message): bool
    {
        if (!Setting::getBool('sms.enabled', false)) return false;

        $phone = self::normalizePhone($phone);
        if ($phone === '') {
            Logger::warning('SmsService: numéro invalide', ['phone' => $phone]);
            return false;
        }

        $provider = strtolower(Setting::getString('sms.provider', 'log'));
        $sender   = Setting::getString('sms.sender_id', 'CityBus');
        $apiKey   = Setting::getString('sms.api_key', '');

        switch ($provider) {
            case 'log':
            case '':
                Logger::info('SMS (mode log)', [
                    'to' => $phone, 'sender' => $sender, 'message' => $message,
                ]);
                return true;

            case 'http_get':
                if ($apiKey === '' || !filter_var($apiKey, FILTER_VALIDATE_URL)) {
                    Logger::warning('SmsService: URL provider invalide', ['provider' => $provider]);
                    return false;
                }
                $url = strtr($apiKey, [
                    '{phone}'   => rawurlencode($phone),
                    '{message}' => rawurlencode($message),
                    '{sender}'  => rawurlencode($sender),
                ]);
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 8,
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $resp = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                $err  = curl_error($ch);
                curl_close($ch);
                if ($resp === false || $code >= 400) {
                    Logger::warning('SmsService: envoi http_get échoué', [
                        'to' => $phone, 'status' => $code, 'error' => $err,
                    ]);
                    return false;
                }
                return true;

            default:
                Logger::warning('SmsService: provider inconnu', ['provider' => $provider]);
                return false;
        }
    }

    private static function normalizePhone(string $raw): string
    {
        $raw = preg_replace('/[^\d+]/', '', $raw) ?? '';
        if ($raw === '') return '';
        // Ne fait pas de transformation locale : on suppose le numéro déjà au format international
        return $raw;
    }
}
