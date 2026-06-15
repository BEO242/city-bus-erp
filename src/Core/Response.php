<?php

declare(strict_types=1);

namespace CityBus\Core;

final class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $url, int $status = 302): never
    {
        header('Location: ' . $url, true, $status);
        exit;
    }

    public static function back(): never
    {
        $url = $_SERVER['HTTP_REFERER'] ?? url('/');
        self::redirect($url);
    }

    public static function html(string $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $body;
    }

    public static function download(string $path, ?string $name = null, string $mime = 'application/octet-stream'): never
    {
        if (!is_file($path)) {
            http_response_code(404);
            echo 'Fichier introuvable';
            exit;
        }
        $name = $name ?? basename($path);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public static function stream(string $content, string $mime, ?string $filename = null): never
    {
        header('Content-Type: ' . $mime);
        if ($filename) {
            header('Content-Disposition: inline; filename="' . $filename . '"');
        }
        echo $content;
        exit;
    }
}
