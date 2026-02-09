<?php

declare(strict_types=1);

// Check PHP version
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    die('PHP 8.1 or higher is required.');
}

// Enable strict error reporting in development
if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Simple PSR-4 autoloader (no external dependencies)
spl_autoload_register(function (string $className): void {
    $namespace = 'TP\\';
    
    // Only handle our namespace
    if (strpos($className, $namespace) !== 0) {
        return;
    }
    
    // Convert namespace to file path
    $className = substr($className, strlen($namespace));
    $filePath = __DIR__ . '/src/' . str_replace('\\', '/', $className) . '.php';
    
    // Handle lowercase directory names (only convert directory part, not filename)
    if (!file_exists($filePath)) {
        $parts = explode('\\', $className);
        if (count($parts) > 1) {
            $parts[0] = strtolower($parts[0]); // Convert directory to lowercase
            $filePath = __DIR__ . '/src/' . implode('/', $parts) . '.php';
        }
    }
    
    if (file_exists($filePath)) {
        require_once $filePath;
    }
});

// Load global helper functions
require_once __DIR__ . '/src/helpers.php';

// Load legacy Component class for backward compatibility
require_once __DIR__ . '/src/core/Component.php';

// Boot the application
$app = \TP\Core\Application::getInstance();
$app->boot();