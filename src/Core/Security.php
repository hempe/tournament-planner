<?php

declare(strict_types=1);

namespace TP\Core;

final class Security
{
    private static ?Security $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): Security
    {
        if (self::$instance === null) {
            self::$instance = new Security();
        }
        return self::$instance;
    }

    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}