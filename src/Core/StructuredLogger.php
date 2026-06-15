<?php

declare(strict_types=1);

namespace CityBus\Core;

final class StructuredLogger
{
    public static function log(string $level, string $message, array $context = [], string $channel = 'app'): void
    {
        try {
            Database::insert('structured_logs', [
                'level'      => $level,
                'channel'    => $channel,
                'message'    => mb_substr($message, 0, 250),
                'context'    => $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
                'actor_id'   => Auth::id(),
                'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8)),
            ]);
        } catch (\Throwable $e) {
            // Fallback file
            error_log("[{$level}][{$channel}] {$message} " . json_encode($context));
        }
    }

    public static function info(string $msg, array $ctx = [], string $ch = 'app'): void { self::log('info', $msg, $ctx, $ch); }
    public static function warn(string $msg, array $ctx = [], string $ch = 'app'): void { self::log('warning', $msg, $ctx, $ch); }
    public static function error(string $msg, array $ctx = [], string $ch = 'app'): void { self::log('error', $msg, $ctx, $ch); }
    public static function critical(string $msg, array $ctx = [], string $ch = 'app'): void { self::log('critical', $msg, $ctx, $ch); }
}
