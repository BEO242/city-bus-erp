<?php

declare(strict_types=1);

namespace CityBus\Middleware;

use CityBus\Core\Auth;
use CityBus\Core\Request;
use CityBus\Core\Session;

final class AuthMiddleware
{
    public function handle(Request $request, callable $next): mixed
    {
        if (!Auth::check()) {
            Session::flash('warning', 'Veuillez vous connecter pour continuer.');
            Session::set('_intended', $request->uri);
            redirect('login');
        }
        return $next($request);
    }
}
