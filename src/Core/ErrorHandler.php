<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * Gestionnaire d'erreurs et logger fichier minimaliste.
 */
final class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);
        $debug = Config::get('app.debug', false);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        set_exception_handler([self::class, 'handle']);
        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) return false;
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }

    public static function handle(\Throwable $e): void
    {
        Logger::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL . $e->getTraceAsString());

        if (Config::get('app.debug', false)) {
            self::renderDebug($e);
        } else {
            self::renderProduction($e);
        }
    }

    private static function renderDebug(\Throwable $e): void
    {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Erreur</title>';
        echo '<style>body{font-family:Inter,system-ui,sans-serif;background:#0D1B2A;color:#fff;margin:0;padding:2rem;}';
        echo '.box{max-width:1100px;margin:0 auto;background:#1a2942;border:1px solid #1565C0;border-radius:12px;padding:2rem;box-shadow:0 20px 60px rgba(0,0,0,.5);}';
        echo 'h1{color:#42A5F5;margin-top:0;}.meta{color:#90A4AE;font-size:.9rem;}';
        echo 'pre{background:#0D1B2A;padding:1rem;border-radius:8px;overflow:auto;font-size:.85rem;border:1px solid #1565C0;}';
        echo '</style></head><body><div class="box">';
        echo '<h1>⚠ ' . htmlspecialchars(get_class($e)) . '</h1>';
        echo '<p style="font-size:1.1rem;">' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p class="meta">' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        echo '</div></body></html>';
        exit;
    }

    private static function renderProduction(\Throwable $e): void
    {
        http_response_code(500);
        try {
            $view = new View();
            echo $view->render('errors/500');
        } catch (\Throwable) {
            echo '<h1>Erreur serveur</h1><p>Une erreur est survenue. Veuillez réessayer plus tard.</p>';
        }
        exit;
    }
}
