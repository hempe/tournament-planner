<?php

declare(strict_types=1);

// Load environment variables from phpunit.xml
require_once __DIR__ . '/../src/Core/Config.php';

// Start session for testing
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoload classes
spl_autoload_register(function ($class) {
    $prefix = 'TP\\';
    $baseDir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Helper functions
require_once __DIR__ . '/../src/helpers.php';
