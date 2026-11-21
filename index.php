<?php

declare(strict_types=1);

// Bootstrap the application
require_once __DIR__ . '/bootstrap.php';

use TP\Core\Application;
use TP\Core\HealthCheck;
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
$router->get('/health', fn (Request $request): Response => HealthCheck::respond());

// Run the application
$app->run();
