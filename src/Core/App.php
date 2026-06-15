<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * Application principale. Container minimaliste + dispatcher.
 */
final class App
{
    private static ?App $instance = null;
    public Router $router;

    public function __construct()
    {
        self::$instance = $this;
        $this->router = new Router();
    }

    public static function instance(): self
    {
        return self::$instance ?? throw new \RuntimeException('App non initialisée');
    }

    public function run(): void
    {
        $request = new Request();
        $this->router->dispatch($request);
    }
}
