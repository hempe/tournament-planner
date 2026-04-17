<?php

declare(strict_types=1);

/**
 * Build-time route cache generator.
 *
 * Run from the dist/ directory (or app root) so that __DIR__-relative
 * paths inside the framework resolve correctly:
 *
 *   cd dist && php bin/build-routes.php
 */

$appRoot = dirname(__DIR__);

// Minimal autoloader — mirrors bootstrap.php but skips Application::boot()
spl_autoload_register(function (string $className) use ($appRoot): void {
    $namespace = 'TP\\';

    if (!str_starts_with($className, $namespace)) {
        return;
    }

    $relative = substr($className, strlen($namespace));
    $filePath = $appRoot . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (!file_exists($filePath)) {
        $parts = explode('\\', $relative);
        if (count($parts) > 1) {
            $parts[0] = strtolower($parts[0]);
            $filePath = $appRoot . '/src/' . implode('/', $parts) . '.php';
        }
    }

    if (file_exists($filePath)) {
        require_once $filePath;
    }
});

require_once $appRoot . '/src/helpers.php';
require_once $appRoot . '/src/Core/Component.php';

// Force production so RouteLoader enables caching
$_ENV['APP_ENV'] = 'production';

$router = new \TP\Core\Router();
$loader = new \TP\Core\RouteLoader();
$loader->load($router);

$cacheFile = $appRoot . '/storage/cache/routes.php';
echo "Route cache written → {$cacheFile}\n";
