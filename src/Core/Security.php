<?php

declare(strict_types=1);

namespace TP\Core;

final class Security
{
    private static ?Security $instance = null;
    private string $csrfTokenName;
    private LoggerInterface $logger;

    private function __construct()
    {
        $config = Config::getInstance();
        $this->csrfTokenName = $config->get('security.csrf_token_name', '_token');
        $this->logger = new Logger();
    }

    public static function getInstance(): Security
    {
        if (self::$instance === null) {
            self::$instance = new Security();
        }
        return self::$instance;
    }

    public function generateCsrfToken(): string
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'][$this->csrfTokenName] = $token;

        return $token;
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