<?php

declare(strict_types=1);

namespace CityBus\Middleware;

use CityBus\Core\Auth;
use CityBus\Core\Request;
use CityBus\Core\View;

/**
 * Vérifie une permission spécifique. Usage : 'permission:billetterie.create'
 */
final class PermissionMiddleware
{
    public function handle(Request $request, callable $next, string $permission = ''): mixed
    {
        if (!Auth::check() || !Auth::can($permission)) {
            http_response_code(403);
            echo (new View())->render('errors/403', ['permission' => $permission]);
            exit;
        }
        return $next($request);
    }
}
