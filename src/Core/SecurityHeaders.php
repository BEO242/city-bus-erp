<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * En-têtes de sécurité HTTP appliqués sur toutes les réponses authentifiées.
 *
 * - X-Frame-Options SAMEORIGIN (autorise iframe interne pour impression)
 * - X-Content-Type-Options nosniff
 * - Referrer-Policy strict-origin-when-cross-origin
 * - Permissions-Policy : désactive caméra/micro/géoloc/USB
 * - Strict-Transport-Security : 1 an + subdomains (uniquement HTTPS)
 * - Content-Security-Policy : limite les sources (inline accepté pour Tailwind/Alpine
 *   tant que les CDN restent les mêmes — sans nonce car le code utilise inline-style)
 * - X-XSS-Protection : ancien IE/Safari
 */
final class SecurityHeaders
{
    public static function send(): void
    {
        if (headers_sent()) return;

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        header('X-XSS-Protection: 0'); // Modern browsers : désactivé (CSP couvre)

        // HSTS : seulement si HTTPS
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // CSP : permissif sur les inline (le projet utilise Tailwind CDN + Alpine inline)
        // Mais on liste explicitement les hosts autorisés.
        $cdns = [
            'https://cdn.tailwindcss.com',
            'https://cdn.jsdelivr.net',
            'https://cdnjs.cloudflare.com',
            'https://unpkg.com',
            'https://fonts.googleapis.com',
            'https://fonts.gstatic.com',
        ];
        $cdnList = implode(' ', $cdns);
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' {$cdnList}",
            "style-src 'self' 'unsafe-inline' {$cdnList}",
            "font-src 'self' data: {$cdnList}",
            "img-src 'self' data: blob: {$cdnList}",
            "connect-src 'self' {$cdnList}",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}
