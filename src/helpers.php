<?php

declare(strict_types=1);

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

if (!function_exists('logger')) {
    /**
     * Get logger instance
     */
    function logger(): \TP\Core\LoggerInterface
    {
        return \TP\Core\Application::getInstance()->getLogger();
    }
}
