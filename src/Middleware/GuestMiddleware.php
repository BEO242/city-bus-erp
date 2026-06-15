<?php

declare(strict_types=1);

namespace CityBus\Middleware;

use CityBus\Core\Auth;
use CityBus\Core\Request;
use CityBus\Core\View;

final class GuestMiddleware
{
    public function handle(Request $request, callable $next): mixed
    {
        if (Auth::check()) {
            redirect('dashboard');
        }
        return $next($request);
    }
}
