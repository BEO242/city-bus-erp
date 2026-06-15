<?php

declare(strict_types=1);

namespace CityBus\Core;

final class Logger
{
    private static function path(): string
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return $dir . '/' . date('Y-m-d') . '.log';
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $line = sprintf(
            "[%s] %s.%s: %s %s\n",
            date('Y-m-d H:i:s'),
            'citybus',
            strtoupper($level),
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );
        @file_put_contents(self::path(), $line, FILE_APPEND);
    }

    public static function info(string $msg, array $ctx = []): void    { self::log('info', $msg, $ctx); }
    public static function warning(string $msg, array $ctx = []): void { self::log('warning', $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void   { self::log('error', $msg, $ctx); }
    public static function debug(string $msg, array $ctx = []): void   { self::log('debug', $msg, $ctx); }
}
