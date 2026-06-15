<?php

declare(strict_types=1);

namespace CityBus\Core;

/**
 * Routeur HTTP léger avec support de groupes, middleware et paramètres.
 *
 * Usage :
 *   $router->get('/users/{id}', [UserController::class, 'show']);
 *   $router->group(['middleware' => ['auth']], function ($r) { ... });
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,handler:mixed,middleware:array,name:?string}> */
    private array $routes = [];
    private array $groupStack = [];
    private array $namedRoutes = [];

    public function get(string $path, mixed $handler): self    { return $this->add('GET', $path, $handler); }
    public function post(string $path, mixed $handler): self   { return $this->add('POST', $path, $handler); }
    public function put(string $path, mixed $handler): self    { return $this->add('PUT', $path, $handler); }
    public function patch(string $path, mixed $handler): self  { return $this->add('PATCH', $path, $handler); }
    public function delete(string $path, mixed $handler): self { return $this->add('DELETE', $path, $handler); }
    public function any(string $path, mixed $handler): self    { return $this->add('ANY', $path, $handler); }

    public function add(string $method, string $path, mixed $handler): self
    {
        $prefix = '';
        $middleware = [];
        foreach ($this->groupStack as $group) {
            $prefix     .= $group['prefix'] ?? '';
            $middleware  = array_merge($middleware, $group['middleware'] ?? []);
        }

        $pattern = '/' . trim($prefix . '/' . trim($path, '/'), '/');
        if ($pattern === '') $pattern = '/';

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => $middleware,
            'name'       => null,
        ];
        return $this;
    }

    public function name(string $name): self
    {
        $idx = array_key_last($this->routes);
        $this->routes[$idx]['name'] = $name;
        $this->namedRoutes[$name] = $this->routes[$idx]['pattern'];
        return $this;
    }

    /**
     * Ajoute un (ou plusieurs) middlewares à la dernière route déclarée.
     * Ex: $r->post('/buses', [BusController::class,'store'])->middleware('permission:referentiel.create');
     */
    public function middleware(string|array $middleware): self
    {
        $idx = array_key_last($this->routes);
        $this->routes[$idx]['middleware'] = array_merge(
            $this->routes[$idx]['middleware'],
            (array)$middleware
        );
        return $this;
    }

    public function group(array $attributes, \Closure $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function dispatch(Request $request): mixed
    {
        $path   = $request->path;
        $method = $request->method;

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }
            $params = $this->match($route['pattern'], $path);
            if ($params === null) {
                continue;
            }

            // Pipeline middleware
            $pipeline = array_reverse($route['middleware']);
            $core = function () use ($route, $request, $params) {
                return $this->callHandler($route['handler'], $request, $params);
            };
            foreach ($pipeline as $middleware) {
                $next = $core;
                $core = function () use ($middleware, $request, $next) {
                    return $this->callMiddleware($middleware, $request, $next);
                };
            }
            return $core();
        }

        http_response_code(404);
        $view = new View();
        echo $view->render('errors/404');
        return null;
    }

    private function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        if (preg_match($regex, $path, $m)) {
            return array_filter($m, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
        }
        return null;
    }

    private function callHandler(mixed $handler, Request $request, array $params): mixed
    {
        if (is_callable($handler)) {
            return $handler($request, ...array_values($params));
        }
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = new $class();
            return $instance->$method($request, ...array_values($params));
        }
        throw new \RuntimeException('Handler de route invalide');
    }

    private function callMiddleware(string|callable $middleware, Request $request, callable $next): mixed
    {
        if (is_string($middleware)) {
            // Format: "name" ou "name:arg1,arg2"
            $args = [];
            if (str_contains($middleware, ':')) {
                [$middleware, $argStr] = explode(':', $middleware, 2);
                $args = explode(',', $argStr);
            }
            $class = '\\CityBus\\Middleware\\' . str_replace(' ', '', ucwords(str_replace(['-','_'], ' ', $middleware))) . 'Middleware';
            if (!class_exists($class)) {
                throw new \RuntimeException("Middleware introuvable : $class");
            }
            $instance = new $class();
            return $instance->handle($request, $next, ...$args);
        }
        return $middleware($request, $next);
    }

    public function url(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new \RuntimeException("Route nommée introuvable : $name");
        }
        $url = $this->namedRoutes[$name];
        foreach ($params as $k => $v) {
            $url = str_replace('{' . $k . '}', (string)$v, $url);
        }
        return $url;
    }

    public function getRoutes(): array { return $this->routes; }
}
