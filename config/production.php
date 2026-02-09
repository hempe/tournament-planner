<?php

declare(strict_types=1);

return [
    'app' => [
        'debug' => false,
    ],
    'logging' => [
        'level' => 'WARNING',
        'file' => '/var/log/golf-el-faro/app.log',
    ],
    'security' => [
        'session_lifetime' => 3600, // 1 hour for production
        'password_min_length' => 12, // Stronger passwords in production
    ],
];