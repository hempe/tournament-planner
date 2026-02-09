<?php

declare(strict_types=1);

// Bootstrap the application
require_once __DIR__ . '/bootstrap.php';

use TP\Core\Application;
use TP\Core\Router;
use TP\Core\Request;
use TP\Core\Response;
use TP\Core\RouteGroup;
use TP\Middleware\AuthMiddleware;
use TP\Middleware\AdminMiddleware;
use TP\Controllers\HomeController;
use TP\Controllers\AuthController;
use TP\Controllers\EventController;
use TP\Controllers\UserController;

// Get application instance
$app = Application::getInstance();
$router = $app->getRouter();

// Home and authentication routes
$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'loginForm']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], [new AuthMiddleware()]);

// Event routes (authenticated users)
$router->group(
    new RouteGroup('/events', [new AuthMiddleware()]),
    function (Router $router) {
        // Regular user event routes
        $router->get('/', [EventController::class, 'index']);
        $router->get('/{id}', [EventController::class, 'show']);
        $router->post('/{id}/register', [EventController::class, 'register']);
        $router->post('/{id}/unregister', [EventController::class, 'unregister']);
        $router->post('/{id}/comment', [EventController::class, 'updateComment']);
        
        // Admin-only event routes
        $router->group(
            new RouteGroup('', [new AdminMiddleware()]),
            function (Router $router) {
                $router->get('/new', [EventController::class, 'create']);
                $router->post('/', [EventController::class, 'store']);
                $router->get('/{id}/admin', [EventController::class, 'admin']);
                $router->post('/{id}/update', [EventController::class, 'update']);
                $router->post('/{id}/delete', [EventController::class, 'delete']);
                $router->post('/{id}/lock', [EventController::class, 'lock']);
                $router->post('/{id}/unlock', [EventController::class, 'unlock']);
            }
        );
    }
);

// User management routes (admin only)
$router->group(
    new RouteGroup('/users', [new AuthMiddleware(), new AdminMiddleware()]),
    function (Router $router) {
        $router->get('/', [UserController::class, 'index']);
        $router->get('/new', [UserController::class, 'create']);
        $router->post('/', [UserController::class, 'store']);
        $router->post('/{id}/delete', [UserController::class, 'delete']);
        $router->post('/{id}/admin', [UserController::class, 'toggleAdmin']);
        $router->post('/{id}/password', [UserController::class, 'changePassword']);
    }
);

// Health check endpoint
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
            (string)$config->get('database.host'),
            (string)$config->get('database.username'),
            (string)$config->get('database.password'),
            (string)$config->get('database.name'),
            (int)$config->get('database.port')
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
