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

if (!function_exists('trans')) {
    /**
     * Translate a message with optional parameters
     */
    function trans(string $key, array $parameters = []): string
    {
        return Translator::getInstance()->translate($key, $parameters);
    }
}

if (!function_exists('trans_choice')) {
    /**
     * Translate a message with pluralization
     */
    function trans_choice(string $key, int $count, array $parameters = []): string
    {
        return Translator::getInstance()->choice($key, $count, $parameters);
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

if (!function_exists('e')) {
    /**
     * Escape HTML entities
     */
    function e(string $value): string
    {
        return Security::getInstance()->escapeHtml($value);
    }
}

if (!function_exists('attr')) {
    /**
     * Escape HTML attributes
     */
    function attr(string $value): string
    {
        return Security::getInstance()->escapeAttr($value);
    }
}

if (!function_exists('url')) {
    /**
     * Escape URL parameters
     */
    function url(string $value): string
    {
        return Security::getInstance()->escapeUrl($value);
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     */
    function config(string $key, mixed $default = null): mixed
    {
        return \TP\Core\Config::getInstance()->get($key, $default);
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable
     */
    function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }
}

if (!function_exists('app')) {
    /**
     * Get application instance
     */
    function app(): \TP\Core\Application
    {
        return \TP\Core\Application::getInstance();
    }
}

if (!function_exists('logger')) {
    /**
     * Get logger instance
     */
    function logger(): \TP\Core\LoggerInterface
    {
        return app()->getLogger();
    }
}

if (!function_exists('old')) {
    /**
     * Get old input value from session
     */
    function old(string $key, mixed $default = null): mixed
    {
        if (!isset($_SESSION)) {
            return $default;
        }
        
        $oldInput = $_SESSION['old_input'] ?? [];
        return $oldInput[$key] ?? $default;
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

if (!function_exists('session')) {
    /**
     * Get session value
     */
    function session(string $key, mixed $default = null): mixed
    {
        if (!isset($_SESSION)) {
            return $default;
        }
        
        return $_SESSION[$key] ?? $default;
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and die (for debugging)
     */
    function dd(mixed ...$vars): never
    {
        foreach ($vars as $var) {
            echo '<pre>';
            var_dump($var);
            echo '</pre>';
        }
        die();
    }
}

if (!function_exists('dump')) {
    /**
     * Dump variable (for debugging)
     */
    function dump(mixed ...$vars): void
    {
        foreach ($vars as $var) {
            echo '<pre>';
            var_dump($var);
            echo '</pre>';
        }
    }
}

if (!function_exists('str_starts_with')) {
    /**
     * Polyfill for PHP < 8.0
     */
    function str_starts_with(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    /**
     * Polyfill for PHP < 8.0
     */
    function str_ends_with(string $haystack, string $needle): bool
    {
        return substr($haystack, -strlen($needle)) === $needle;
    }
}

if (!function_exists('str_contains')) {
    /**
     * Polyfill for PHP < 8.0
     */
    function str_contains(string $haystack, string $needle): bool
    {
        return strpos($haystack, $needle) !== false;
    }
}