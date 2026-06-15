<?php

declare(strict_types=1);

namespace CityBus\Middleware;

use CityBus\Core\Auth;
use CityBus\Core\Request;
use CityBus\Core\View;

/**
 * Vérifie qu'au moins un des rôles passés est porté par l'utilisateur.
 * Usage : 'role:admin,chef_agence'
 */
final class RoleMiddleware
{
    public function handle(Request $request, callable $next, string ...$roles): mixed
    {
        if (!Auth::check() || !Auth::hasRole($roles)) {
            http_response_code(403);
            echo (new View())->render('errors/403');
            exit;
        }
        return $next($request);
    }
}
