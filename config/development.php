<?php

declare(strict_types=1);

return [
    'app' => [
        'debug' => true,
    ],
    'logging' => [
        'level' => 'DEBUG',
    ],
    'database' => [
        'host' => 'localhost',
        'name' => 'TPDb',
    ],
    'security' => [
        'session_lifetime' => 7200, // 2 hours for development
    ],
];