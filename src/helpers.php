<?php

declare(strict_types=1);

use TP\Core\Security;
use TP\Core\Translator;

if (!function_exists('__')) {
    /**
     * Translate a message with optional parameters
     */
    function __(string $key, array $parameters = []): string
    {
        return Translator::getInstance()->translate($key, $parameters);
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Generate a CSRF token
     */
    function csrf_token(): string
    {
        return Security::getInstance()->generateCsrfToken();
    }
}

if (!function_exists('logger')) {
    /**
     * Get logger instance
     */
    function logger(): \TP\Core\LoggerInterface
    {
        return \TP\Core\Application::getInstance()->getLogger();
    }
}

if (!function_exists('flash')) {
    /**
     * Flash a message to the session
     */
    function flash(string $key, mixed $value): void
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        $_SESSION['flash_messages'][$key] = $value;
    }
}

if (!function_exists('get_flash')) {
    /**
     * Get and remove a flash message from session
     */
    function get_flash(string $key, mixed $default = null): mixed
    {
        if (!isset($_SESSION)) {
            return $default;
        }

        $value = $_SESSION['flash_messages'][$key] ?? $default;
        unset($_SESSION['flash_messages'][$key]);

        return $value;
    }
}

if (!function_exists('has_flash')) {
    /**
     * Check if a flash message exists
     */
    function has_flash(string $key): bool
    {
        if (!isset($_SESSION)) {
            return false;
        }

        return isset($_SESSION['flash_messages'][$key]);
    }
}
