<?php

declare(strict_types=1);

namespace TP\Core;

use Closure;

final class Router
{
    private array $routes = [];
    private array $groupPrefixStack = [];
    private array $groupMiddlewareStack = [];

    public function get(string $pattern, callable|array $handler, array $middleware = [], string $name = ''): void
    {
        $this->addRoute('GET', $pattern, $handler, $middleware, $name);
    }

    public function post(string $pattern, callable|array $handler, array $middleware = [], string $name = ''): void
    {
        $this->addRoute('POST', $pattern, $handler, $middleware, $name);
    }

    public function put(string $pattern, callable|array $handler, array $middleware = [], string $name = ''): void
    {
        $this->addRoute('PUT', $pattern, $handler, $middleware, $name);
    }

    public function delete(string $pattern, callable|array $handler, array $middleware = [], string $name = ''): void
    {
        $this->addRoute('DELETE', $pattern, $handler, $middleware, $name);
    }

    /**
     * Load pre-scanned route definitions from attributes.
     *
     * @param array<array{method: string, pattern: string, handler: array, middleware: array, name: string}> $routes
     */
    public function loadRoutes(array $routes): void
    {
        foreach ($routes as $route) {
            $this->routes[] = [
                'method' => $route['method'],
                'pattern' => $this->normalizePath($route['pattern']),
                'handler' => $route['handler'],
                'middleware' => $route['middleware'],
                'name' => $route['name'] ?? '',
            ];
        }
    }

    /**
     * Get all registered routes (for testing/debugging).
     *
     * @return array<array{method: string, pattern: string, handler: callable|array, middleware: array, name: string}>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function group(RouteGroup $group, callable $callback): void
    {
        // Push group prefix and middleware to stacks
        $this->groupPrefixStack[] = $group->prefix;
        $this->groupMiddlewareStack[] = $group->middleware;

        // Execute callback (which registers nested routes)
        $callback($this);

        // Pop stacks after callback completes
        array_pop($this->groupPrefixStack);
        array_pop($this->groupMiddlewareStack);
    }

    public function handle(Request $request): Response
    {
        $method = $request->getMethod()->value;
        $path = $this->normalizePath($request->getPath());

        // Find matching route
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchRoute($route['pattern'], $path);
            if ($params === null) {
                continue;
            }

            // Build middleware pipeline - instantiate class names if needed
            $pipeline = array_map(
                fn($m) => is_string($m) ? new $m() : $m,
                $route['middleware']
            );

            // Build nested callable chain (innermost is handler)
            $next = function (Request $req) use ($route, $params) {
                return $this->callHandler($route['handler'], $req, $params);
            };

            // Wrap with middleware (reverse order so first middleware executes first)
            foreach (array_reverse($pipeline) as $middleware) {
                $currentNext = $next;
                $next = fn(Request $req) => $middleware->handle($req, $currentNext);
            }

            // Execute pipeline
            return $next($request);
        }

        // No route matched - return 404
        return Response::notFound('Page not found');
    }

    private function addRoute(string $method, string $pattern, callable|array $handler, array $middleware, string $name): void
    {
        // Combine group prefixes
        $fullPrefix = implode('', $this->groupPrefixStack);
        $fullPattern = $this->normalizePath($fullPrefix . $pattern);

        // Combine group middleware with route middleware
        $allMiddleware = [];
        foreach ($this->groupMiddlewareStack as $groupMiddleware) {
            $allMiddleware = array_merge($allMiddleware, $groupMiddleware);
        }
        $allMiddleware = array_merge($allMiddleware, $middleware);

        $this->routes[] = [
            'method' => $method,
            'pattern' => $fullPattern,
            'handler' => $handler,
            'middleware' => $allMiddleware,
            'name' => $name,
        ];
    }

    private function matchRoute(string $pattern, string $path): ?array
    {
        // Convert route pattern to regex
        // Replace {paramName} with (?P<paramName>[^/]+)
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);

        // Escape forward slashes for regex
        $regex = str_replace('/', '\/', $regex);

        // Add anchors
        $regex = '/^' . $regex . '$/';

        // Try to match
        if (preg_match($regex, $path, $matches)) {
            // Extract named parameters
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return $params;
        }

        return null;
    }

    private function callHandler(Closure|array $handler, Request $request, array $params): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class();

            // Use reflection to check method signature
            $reflection = new \ReflectionMethod($controller, $method);
            $parameters = $reflection->getParameters();

            // Call with params only if method accepts them
            if (count($parameters) >= 2) {
                return $controller->$method($request, $params);
            }

            return $controller->$method($request);
        }

        // For closures, check signature
        $reflection = new \ReflectionFunction($handler);
        $parameters = $reflection->getParameters();

        if (count($parameters) >= 2) {
            return $handler($request, $params);
        } else {
            return $handler($request);
        }
    }

    private function normalizePath(string $path): string
    {
        // Remove trailing slash (except for root)
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        // Ensure leading slash
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return $path;
    }
}
