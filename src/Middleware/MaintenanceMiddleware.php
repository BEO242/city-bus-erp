<?php

declare(strict_types=1);

namespace CityBus\Middleware;

use CityBus\Core\Auth;
use CityBus\Core\Request;
use CityBus\Core\Setting;
use CityBus\Core\View;

final class MaintenanceMiddleware
{
    public function handle(Request $request, callable $next): mixed
    {
        if (!Setting::getBool('maintenance.enabled', false)) {
            return $next($request);
        }

        // Admin toujours autorisé
        if (Auth::check() && Auth::role() === 'admin') {
            return $next($request);
        }

        // IP autorisée ?
        $allowed = Setting::getString('maintenance.allowed_ips', '');
        $ip = $request->ip();
        $list = array_filter(array_map('trim', explode(',', $allowed)));
        if (in_array($ip, $list, true)) {
            return $next($request);
        }

        http_response_code(503);
        $view = new View();
        echo $view->render('errors/maintenance', [
            'message' => Setting::getString('maintenance.message', 'Application en maintenance. Merci de revenir plus tard.'),
        ]);
        exit;
    }
}
