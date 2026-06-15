<?php

declare(strict_types=1);

namespace CityBus\Middleware;

use CityBus\Core\Request;
use CityBus\Core\Setting;

/**
 * Middleware pour API REST :
 * - vérifie integration.api_enabled (sinon 503),
 * - vérifie le bearer token contre integration.api_key (sinon 401),
 * - force le Content-Type JSON sur la réponse.
 */
final class ApiTokenMiddleware
{
    public function handle(Request $request, callable $next): mixed
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Setting::getBool('integration.api_enabled', false)) {
            http_response_code(503);
            echo json_encode(['error' => 'api_disabled', 'message' => 'L’API REST est désactivée.']);
            return null;
        }

        $expected = Setting::getString('integration.api_key', '');
        if ($expected === '') {
            http_response_code(503);
            echo json_encode(['error' => 'api_not_configured', 'message' => 'Aucune clé API configurée.']);
            return null;
        }

        $token = $this->extractToken();
        if ($token === '' || !hash_equals($expected, $token)) {
            http_response_code(401);
            header('WWW-Authenticate: Bearer realm="CityBus API"');
            echo json_encode(['error' => 'unauthorized', 'message' => 'Token API invalide ou absent.']);
            return null;
        }

        return $next($request);
    }

    private function extractToken(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
        if ($header === '' && function_exists('apache_request_headers')) {
            $hdrs = apache_request_headers() ?: [];
            $header = $hdrs['Authorization'] ?? $hdrs['authorization'] ?? '';
        }
        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }
        return '';
    }
}
