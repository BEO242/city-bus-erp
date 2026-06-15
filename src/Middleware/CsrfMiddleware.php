<?php

declare(strict_types=1);

namespace CityBus\Middleware;

use CityBus\Core\Csrf;
use CityBus\Core\Request;
use CityBus\Core\Session;

final class CsrfMiddleware
{
    public function handle(Request $request, callable $next): mixed
    {
        if (in_array($request->method, ['POST','PUT','PATCH','DELETE'], true)) {
            $token = $request->input('_csrf') ?? $request->header('X-CSRF-Token');
            if (!Csrf::check($token)) {
                http_response_code(419);
                if ($request->isAjax()) {
                    \CityBus\Core\Response::json(['error' => 'CSRF token invalide'], 419);
                }
                Session::flash('danger', 'Session expirée, veuillez réessayer.');
                back();
            }
        }
        return $next($request);
    }
}
