<?php

declare(strict_types=1);

return [
    'app' => [
        'debug' => true,
    ],
    'database' => [
        'host' => 'localhost',
        'name' => 'golfelfaro_test',
    ],
    'logging' => [
        'level' => 'DEBUG',
        'file' => 'php://stderr',
    ],
    'security' => [
        'session_lifetime' => 1800, // 30 minutes for testing
        'password_min_length' => 6, // Shorter for testing
    ],
];