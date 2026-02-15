<?php

declare(strict_types=1);

// Bootstrap the application
require_once __DIR__ . '/bootstrap.php';

use TP\Core\Application;
use TP\Core\Request;
use TP\Core\Response;
use TP\Core\RouteLoader;

// Get application instance
$app = Application::getInstance();
$router = $app->getRouter();

// Load attribute-based routes
$routeLoader = new RouteLoader();
$routeLoader->load($router);

// Health check endpoint (closure route - can't use attributes)
$router->get('/health', function (Request $request): Response {
    $config = \TP\Core\Config::getInstance();

    $status = [
        'status' => 'ok',
        'timestamp' => date('c'),
        'php_version' => PHP_VERSION,
        'memory_usage' => memory_get_usage(true),
        'environment' => $config->getEnvironment()->value,
        'app_name' => $config->get('app.name'),
        'locale' => $config->get('app.locale'),
    ];

    // Test database connection separately
    try {
        $testConn = @new mysqli(
            (string) $config->get('database.host'),
            (string) $config->get('database.username'),
            (string) $config->get('database.password'),
            (string) $config->get('database.name'),
            (int) $config->get('database.port')
        );

        if ($testConn->connect_error) {
            $status['database'] = 'connection_failed: ' . $testConn->connect_error;
        } else {
            $status['database'] = 'connected';
            $testConn->close();
        }
    } catch (Exception $e) {
        $status['database'] = 'error: ' . $e->getMessage();
    }

    return Response::json($status);
});

// Run the application
$app->run();
