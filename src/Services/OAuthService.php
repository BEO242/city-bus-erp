<?php

declare(strict_types=1);

namespace CityBus\Services;

use CityBus\Core\Database;
use CityBus\Core\Setting;

/**
 * OAuth2 client_credentials minimaliste (GAP-34).
 * Permet à des intégrateurs externes (partenaires, GDS) d'obtenir un access_token
 * en présentant client_id + client_secret.
 */
final class OAuthService
{
    public function issueToken(string $clientId, string $clientSecret, string $requestedScopes = 'read'): ?array
    {
        $client = Database::selectOne(
            "SELECT * FROM oauth_clients WHERE client_id = ? AND is_active = 1", [$clientId]
        );
        if (!$client) return null;
        if (!password_verify($clientSecret, $client['client_secret_hash'])) return null;

        $allowed = array_filter(array_map('trim', explode(' ', (string)$client['scopes'])));
        $requested = array_filter(array_map('trim', explode(' ', $requestedScopes)));
        $granted = array_intersect($requested, $allowed) ?: $allowed;

        $token = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $token);
        $ttlH  = max(1, Setting::getInt('api.token_ttl_hours', 24));
        $expires = date('Y-m-d H:i:s', time() + $ttlH * 3600);

        Database::insert(
            "INSERT INTO oauth_access_tokens (client_id, token_hash, scopes, issued_at, expires_at)
             VALUES (?, ?, ?, NOW(), ?)",
            [(int)$client['id'], $hash, implode(' ', $granted), $expires]
        );
        Database::execute("UPDATE oauth_clients SET last_used_at = NOW() WHERE id = ?", [(int)$client['id']]);

        return [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => $ttlH * 3600,
            'scope'        => implode(' ', $granted),
        ];
    }

    /** Vérifie un token et retourne les infos client si valide. */
    public function authenticate(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $row = Database::selectOne(
            "SELECT oat.id AS token_id, oat.scopes AS token_scopes, oat.expires_at,
                    c.id AS client_id, c.name, c.rate_limit_per_min, c.partner_id
             FROM oauth_access_tokens oat
             JOIN oauth_clients c ON c.id = oat.client_id
             WHERE oat.token_hash = ? AND oat.revoked = 0
               AND oat.expires_at > NOW()
               AND c.is_active = 1",
            [$hash]
        );
        return $row ?: null;
    }

    /** Crée un nouveau client OAuth. Retourne client_id + client_secret en clair (à stocker). */
    public function createClient(string $name, string $scopes = 'read', ?string $description = null, ?int $rateLimit = null): array
    {
        $clientId = 'cb_' . bin2hex(random_bytes(8));
        $secret   = bin2hex(random_bytes(24));
        $hash     = password_hash($secret, PASSWORD_BCRYPT);

        Database::insert(
            "INSERT INTO oauth_clients (client_id, client_secret_hash, name, description, scopes, rate_limit_per_min, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)",
            [$clientId, $hash, $name, $description, $scopes, $rateLimit ?? Setting::getInt('api.rate_limit_per_min', 60)]
        );
        return ['client_id' => $clientId, 'client_secret' => $secret];
    }

    public function checkRateLimit(int $clientId, int $perMin): bool
    {
        $count = (int)(Database::selectOne(
            "SELECT COUNT(*) AS c FROM api_request_log
              WHERE client_id = ? AND request_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            [$clientId]
        )['c'] ?? 0);
        return $count < $perMin;
    }

    public function logRequest(?int $clientId, string $method, string $path, int $statusCode, int $durationMs, string $ip): void
    {
        Database::insert(
            "INSERT INTO api_request_log (client_id, method, path, status_code, duration_ms, ip_address)
             VALUES (?,?,?,?,?,?)",
            [$clientId, $method, $path, $statusCode, $durationMs, $ip]
        );
    }
}
