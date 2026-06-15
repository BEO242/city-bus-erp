<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * Encapsule la requête HTTP entrante.
 */
final class Request
{
    public readonly string $method;
    public readonly string $path;
    public readonly string $uri;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri    = $_SERVER['REQUEST_URI'] ?? '/';

        // Strip query string + base path
        $path = parse_url($this->uri, PHP_URL_PATH) ?? '/';
        $path = self::stripBasePath($path);
        $this->path = '/' . trim($path, '/');
    }

    private static function stripBasePath(string $path): string
    {
        // Décoder avant comparaison (gère les espaces %20 dans le nom du dossier)
        $path   = rawurldecode($path);
        $script = rawurldecode($_SERVER['SCRIPT_NAME'] ?? '');
        $base   = rtrim(str_replace('\\', '/', dirname($script)), '/');

        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        } elseif ($base !== '') {
            // Fallback : le .htaccess racine masque /public/ dans l'URL
            // → REQUEST_URI = /City bus/dashboard, SCRIPT_NAME = /City bus/public/index.php
            // → on essaie le dossier parent de public/
            $parentBase = rtrim(str_replace('\\', '/', dirname($base)), '/');
            if ($parentBase !== '' && str_starts_with($path, $parentBase)) {
                $path = substr($path, strlen($parentBase));
            }
        }

        return $path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function isPost(): bool   { return $this->method === 'POST'; }
    public function isGet(): bool    { return $this->method === 'GET'; }
    public function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public function json(): array
    {
        $body = file_get_contents('php://input') ?: '';
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }

    public function ip(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_CLIENT_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }
}
