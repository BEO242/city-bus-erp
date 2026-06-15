<?php

declare(strict_types=1);

namespace CityBus\Models;

use CityBus\Core\Database;

final class AuditLog extends BaseModel
{
    protected static string $table = 'audit_logs';
    protected static bool $timestamps = false;

    public static function record(string $action, ?string $entity = null, ?int $entityId = null, array $details = []): void
    {
        $ip  = self::clientIp();
        $mac = self::macFromIp($ip);

        Database::insert(
            'INSERT INTO audit_logs
             (user_id, action, entity, entity_id, details_json, ip_address, mac_address, user_agent)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                \CityBus\Core\Auth::id(),
                $action,
                $entity,
                $entityId,
                $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                $ip,
                $mac,
                self::safeUserAgent(),
            ]
        );
    }

    /**
     * Retourne l'IP réelle du client.
     * Vérifie les en-têtes proxy courants (réseau interne uniquement).
     * REMOTE_ADDR est toujours préféré en dernier recours (fiable).
     */
    private static function clientIp(): string
    {
        // Sur réseau local, on peut faire confiance à X-Forwarded-For
        $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwardedFor !== '') {
            // Peut contenir plusieurs IP séparées par virgule ; prendre la première (origine)
            $first = trim(explode(',', $forwardedFor)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
        $clientIp = $_SERVER['HTTP_CLIENT_IP'] ?? '';
        if ($clientIp !== '' && filter_var($clientIp, FILTER_VALIDATE_IP)) {
            return $clientIp;
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Tente de lire l'adresse MAC depuis la table ARP locale.
     * Fonctionne uniquement sur réseau local (LAN). Retourne null si non disponible.
     */
    private static function macFromIp(string $ip): ?string
    {
        // Pas de MAC pertinente pour localhost ou IPs non-locales
        if ($ip === '127.0.0.1' || $ip === '::1' || !function_exists('exec')) {
            return null;
        }
        // Sécurité : on ne passe que des IPs validées
        if (!filter_var($ip, FILTER_VALIDATE_IP)) return null;

        $output = [];
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                @exec('arp -a ' . $ip . ' 2>NUL', $output);
            } else {
                @exec('arp -n ' . $ip . ' 2>/dev/null', $output);
            }
        } catch (\Throwable) {
            return null;
        }

        foreach ($output as $line) {
            // Windows : "192.168.1.10   00-1a-2b-3c-4d-5e  dynamique"
            // Linux   : "192.168.1.10  ether  aa:bb:cc:dd:ee:ff  C  eth0"
            if (preg_match('/([0-9a-f]{2}[:\-]){5}[0-9a-f]{2}/i', $line, $m)) {
                return strtoupper(str_replace('-', ':', $m[0]));
            }
        }
        return null;
    }

    private static function safeUserAgent(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return mb_substr($ua, 0, 512);
    }
}
