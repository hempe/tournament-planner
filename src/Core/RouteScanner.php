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
use TP\Core\Attributes\Patch;
use TP\Core\Attributes\RoutePrefix;
use TP\Core\Attributes\Middleware;
use TP\Core\Attributes\Feature;

final class RouteScanner
{
    /**
     * Scan a controller class for route attributes.
     *
     * @param class-string $controllerClass
     * @return array<array{method: string, pattern: string, handler: array, middleware: array, name: string, features: list<string>}>
     */
    public function scan(string $controllerClass): array
    {
        $reflection = new ReflectionClass($controllerClass);
        $routes = [];

        // Get class-level prefix and middleware
        $classPrefix = $this->getClassPrefix($reflection);
        $classMiddleware = $this->getClassMiddleware($reflection);
        $classFeatures = $this->featureNames($reflection->getAttributes(Feature::class));

        // Scan all public methods
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip constructor and magic methods
            if ($method->isConstructor() || str_starts_with($method->getName(), '__')) {
                continue;
            }

            $methodRoutes = $this->scanMethod($method, $controllerClass, $classPrefix, $classMiddleware, $classFeatures);
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
     * @return array<array{method: string, pattern: string, handler: array, middleware: array, name: string, features: list<string>}>
     */
    private function scanMethod(
        ReflectionMethod $method,
        string $controllerClass,
        string $classPrefix,
        array $classMiddleware,
        array $classFeatures = []
    ): array {
        $routes = [];
        $features = array_values(array_unique([
            ...$classFeatures,
            ...$this->featureNames($method->getAttributes(Feature::class)),
        ]));
        $methodMiddleware = $this->getMethodMiddleware($method);

        // Combine class and method middleware
        $allMiddleware = array_merge($classMiddleware, $methodMiddleware);

        // Check for HTTP method attributes
        $routeAttributes = [
            ...$method->getAttributes(Get::class),
            ...$method->getAttributes(Post::class),
            ...$method->getAttributes(Put::class),
            ...$method->getAttributes(Delete::class),
            ...$method->getAttributes(Patch::class),
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
                'features' => $features,
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
            Patch::class => 'PATCH',
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

    /**
     * @param array<\ReflectionAttribute<Feature>> $attributes
     * @return list<string>
     */
    private function featureNames(array $attributes): array
    {
        return array_map(fn(\ReflectionAttribute $a) => $a->newInstance()->name, $attributes);
    }
}
