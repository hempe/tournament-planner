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

    public function validateCsrfToken(string $token): bool
    {
        if (!isset($_SESSION)) {
            return false;
        }

        $storedToken = $_SESSION['csrf_tokens'][$this->csrfTokenName] ?? null;

        if ($storedToken === null) {
            $this->logger->warning('CSRF token validation failed: no stored token');
            return false;
        }

        $isValid = hash_equals($storedToken, $token);

        if ($isValid) {
            // Remove used token
            unset($_SESSION['csrf_tokens'][$this->csrfTokenName]);
        } else {
            $this->logger->warning('CSRF token validation failed: token mismatch', [
                'provided_token' => substr($token, 0, 8) . '...',
                'stored_token' => substr($storedToken, 0, 8) . '...',
            ]);
        }

        return $isValid;
    }

    public function getCsrfTokenName(): string
    {
        return $this->csrfTokenName;
    }

    public function escapeHtml(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function escapeAttr(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function escapeUrl(string $string): string
    {
        return rawurlencode($string);
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

    public function isPasswordSecure(string $password): bool
    {
        $config = Config::getInstance();
        $minLength = $config->get('security.password_min_length', 8);

        if (strlen($password) < $minLength) {
            return false;
        }

        // Check for at least one lowercase, uppercase, digit, and special character
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password) === 1;
    }

    public function cleanFilename(string $filename): string
    {
        // Remove directory traversal attempts
        $filename = basename($filename);

        // Remove or replace dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Limit length
        if (strlen($filename) > 255) {
            $filename = substr($filename, 0, 255);
        }

        return $filename;
    }

    public function rateLimitCheck(string $identifier, int $maxRequests = 60, int $windowSeconds = 60): bool
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        $now = time();
        $windowStart = $now - $windowSeconds;

        // Initialize rate limit data
        if (!isset($_SESSION['rate_limits'][$identifier])) {
            $_SESSION['rate_limits'][$identifier] = [];
        }

        $requests = &$_SESSION['rate_limits'][$identifier];

        // Remove old requests
        $requests = array_filter($requests, fn($timestamp) => $timestamp > $windowStart);

        // Check if limit exceeded
        if (count($requests) >= $maxRequests) {
            $this->logger->warning('Rate limit exceeded', [
                'identifier' => $identifier,
                'requests' => count($requests),
                'max_requests' => $maxRequests,
            ]);
            return false;
        }

        // Add current request
        $requests[] = $now;

        return true;
    }
}

// Global helper functions for security
function csrf_token(): string
{
    return Security::getInstance()->generateCsrfToken();
}

function e(string $value): string
{
    return Security::getInstance()->escapeHtml($value);
}

function attr(string $value): string
{
    return Security::getInstance()->escapeAttr($value);
}