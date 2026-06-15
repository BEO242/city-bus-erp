<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * TOTP RFC 6238 (Google Authenticator compatible).
 * Implémentation autonome (sans dépendance externe).
 */
final class TwoFactor
{
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const ALG    = 'sha1';

    /** Génère un secret base32 de 160 bits (20 octets, 32 caractères). */
    public static function generateSecret(): string
    {
        $bytes = random_bytes(20);
        return self::base32Encode($bytes);
    }

    /** URI otpauth:// pour QR code. */
    public static function otpauthUri(string $secret, string $accountLabel, string $issuer = 'City Bus ERP'): string
    {
        $label  = rawurlencode($issuer . ':' . $accountLabel);
        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => strtoupper(self::ALG),
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    /** Vérifie un code à 6 chiffres avec ±1 fenêtre de tolérance. */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) return false;

        $bin = self::base32Decode($secret);
        if ($bin === null) return false;

        $time = (int)floor(time() / self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::generateCode($bin, $time + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    /** Génère un code TOTP. */
    public static function currentCode(string $secret): string
    {
        $bin = self::base32Decode($secret);
        if ($bin === null) return '000000';
        return self::generateCode($bin, (int)floor(time() / self::PERIOD));
    }

    /** Génère N codes de récupération (8 caractères). */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4))); // 8 hex
        }
        return $codes;
    }

    /** Hash un recovery code pour stockage. */
    public static function hashRecoveryCode(string $code): string
    {
        return hash('sha256', strtoupper(trim($code)));
    }

    private static function generateCode(string $key, int $counter): string
    {
        $binCounter = pack('N*', 0) . pack('N*', $counter);
        $hash       = hash_hmac(self::ALG, $binCounter, $key, true);
        $offset     = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value      = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) <<  8) |
             (ord($hash[$offset + 3]) & 0xFF)
        );
        $code = (string)($value % (10 ** self::DIGITS));
        return str_pad($code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = '';
        $buf = 0; $bufLen = 0;
        foreach (str_split($data) as $byte) {
            $buf = ($buf << 8) | ord($byte);
            $bufLen += 8;
            while ($bufLen >= 5) {
                $bufLen -= 5;
                $out .= $alphabet[($buf >> $bufLen) & 0x1F];
            }
        }
        if ($bufLen > 0) {
            $out .= $alphabet[($buf << (5 - $bufLen)) & 0x1F];
        }
        return $out;
    }

    public static function base32Decode(string $b32): ?string
    {
        $b32 = strtoupper(rtrim($b32, '='));
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = '';
        $buf = 0; $bufLen = 0;
        $len = strlen($b32);
        for ($i = 0; $i < $len; $i++) {
            $idx = strpos($alphabet, $b32[$i]);
            if ($idx === false) return null;
            $buf = ($buf << 5) | $idx;
            $bufLen += 5;
            if ($bufLen >= 8) {
                $bufLen -= 8;
                $out .= chr(($buf >> $bufLen) & 0xFF);
            }
        }
        return $out;
    }
}
