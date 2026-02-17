<?php

declare(strict_types=1);

namespace TP\Core;

use ReflectionClass;
use ReflectionMethod;
use TP\Core\Attributes\Route;
use TP\Core\Attributes\Get;
use TP\Core\Attributes\Post;
use TP\Core\Attributes\Put;
use TP\Core\Attributes\Delete;
use TP\Core\Attributes\RoutePrefix;
use TP\Core\Attributes\Middleware;

final class RouteScanner
{
    /**
     * Scan a controller class for route attributes.
     *
     * @param class-string $controllerClass
     * @return array<array{method: string, pattern: string, handler: array, middleware: array, name: string}>
     */
    public function scan(string $controllerClass): array
    {
        $reflection = new ReflectionClass($controllerClass);
        $routes = [];

        // Get class-level prefix and middleware
        $classPrefix = $this->getClassPrefix($reflection);
        $classMiddleware = $this->getClassMiddleware($reflection);

        // Scan all public methods
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip constructor and magic methods
            if ($method->isConstructor() || str_starts_with($method->getName(), '__')) {
                continue;
            }

            $methodRoutes = $this->scanMethod($method, $controllerClass, $classPrefix, $classMiddleware);
            $routes = array_merge($routes, $methodRoutes);
        }

        return $routes;
    }

    /**
     * Get route prefix from class attributes.
     */
    private function getClassPrefix(ReflectionClass $reflection): string
    {
        $attributes = $reflection->getAttributes(RoutePrefix::class);

        if (empty($attributes)) {
            return '';
        }

        /** @var RoutePrefix $instance */
        $instance = $attributes[0]->newInstance();
        return $instance->prefix;
    }

    /**
     * Get middleware class names from class attributes.
     *
     * @return array<class-string>
     */
    private function getClassMiddleware(ReflectionClass $reflection): array
    {
        $attributes = $reflection->getAttributes(Middleware::class);
        $middleware = [];

        foreach ($attributes as $attribute) {
            /** @var Middleware $instance */
            $instance = $attribute->newInstance();
            $middleware[] = $instance->middlewareClass;
        }

        return $middleware;
    }

    /**
     * Scan a method for route attributes.
     *
     * @return array<array{method: string, pattern: string, handler: array, middleware: array, name: string}>
     */
    private function scanMethod(
        ReflectionMethod $method,
        string $controllerClass,
        string $classPrefix,
        array $classMiddleware
    ): array {
        $routes = [];
        $methodMiddleware = $this->getMethodMiddleware($method);

        // Combine class and method middleware
        $allMiddleware = array_merge($classMiddleware, $methodMiddleware);

        // Check for HTTP method attributes
        $routeAttributes = [
            ...$method->getAttributes(Get::class),
            ...$method->getAttributes(Post::class),
            ...$method->getAttributes(Put::class),
            ...$method->getAttributes(Delete::class),
            ...$method->getAttributes(Route::class),
        ];

        foreach ($routeAttributes as $attribute) {
            $instance = $attribute->newInstance();
            $httpMethod = $this->getHttpMethod($instance);
            $path = $this->normalizePath($classPrefix . $instance->path);

            $routes[] = [
                'method' => $httpMethod,
                'pattern' => $path,
                'handler' => [$controllerClass, $method->getName()],
                'middleware' => $allMiddleware,
                'name' => $instance->name,
            ];
        }

        return $routes;
    }

    /**
     * Get middleware from method attributes.
     *
     * @return array<object>
     */
    /**
     * Get middleware class names from method attributes.
     *
     * @return array<class-string>
     */
    private function getMethodMiddleware(ReflectionMethod $method): array
    {
        $attributes = $method->getAttributes(Middleware::class);
        $middleware = [];

        foreach ($attributes as $attribute) {
            /** @var Middleware $instance */
            $instance = $attribute->newInstance();
            $middleware[] = $instance->middlewareClass;
        }

        return $middleware;
    }

    /**
     * Get HTTP method from attribute instance.
     */
    private function getHttpMethod(object $attribute): string
    {
        return match (get_class($attribute)) {
            Get::class => 'GET',
            Post::class => 'POST',
            Put::class => 'PUT',
            Delete::class => 'DELETE',
            Route::class => $attribute->method,
            default => throw new \RuntimeException('Unknown route attribute type'),
        };
    }

    /**
     * Normalize path to ensure consistent format.
     */
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
