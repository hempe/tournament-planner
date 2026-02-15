<?php

declare(strict_types=1);

namespace TP\Core;

enum Environment: string
{
    case DEVELOPMENT = 'development';
    case PRODUCTION = 'production';
    case TESTING = 'testing';
}

final class Config
{
    private static ?Config $instance = null;
    private array $config = [];
    private Environment $environment;

    private function __construct()
    {
        $this->environment = Environment::from($_ENV['APP_ENV'] ?? 'development');
        $this->loadConfig();
    }

    public static function getInstance(): Config
    {
        if (self::$instance === null) {
            self::$instance = new Config();
        }
        return self::$instance;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getNestedValue($this->config, $key, $default);
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    public function isDevelopment(): bool
    {
        return $this->environment === Environment::DEVELOPMENT;
    }

    public function isProduction(): bool
    {
        return $this->environment === Environment::PRODUCTION;
    }

    public function isTesting(): bool
    {
        return $this->environment === Environment::TESTING;
    }

    private function loadConfig(): void
    {
        // Load environment variables from .env file if it exists
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $this->loadEnvFile($envFile);
        }

        // Default configuration
        $this->config = [
            'app' => [
                'name' => $_ENV['APP_NAME'] ?? 'Golf El Faro',
                'url' => $_ENV['APP_URL'] ?? 'http://localhost:5000',
                'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Europe/Zurich',
                'locale' => $_ENV['APP_LOCALE'] ?? 'de',
            ],
            'database' => [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
                'name' => $_ENV['DB_NAME'] ?? 'TPDb',
                'username' => $_ENV['DB_USERNAME'] ?? 'TP',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            ],
            'logging' => [
                'level' => $_ENV['LOG_LEVEL'] ?? ($this->isDevelopment() ? 'DEBUG' : 'INFO'),
                'file' => $_ENV['LOG_FILE'] ?? __DIR__ . '/../../logs/app.log',
            ],
            'security' => [
                'session_lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 3600),
                'csrf_token_name' => $_ENV['CSRF_TOKEN_NAME'] ?? '_token',
                'password_min_length' => (int) ($_ENV['PASSWORD_MIN_LENGTH'] ?? 8),
            ],
            'routing' => [
                'cache_enabled' => filter_var(
                    $_ENV['ROUTE_CACHE_ENABLED'] ?? ($this->isProduction() ? 'true' : 'false'),
                    FILTER_VALIDATE_BOOLEAN
                ),
                'cache_file' => $_ENV['ROUTE_CACHE_FILE'] ?? __DIR__ . '/../../storage/cache/routes.php',
            ],
        ];

        // Environment-specific overrides
        $envConfigFile = __DIR__ . "/../../config/{$this->environment->value}.php";
        if (file_exists($envConfigFile)) {
            $envConfig = require $envConfigFile;
            $this->config = $this->mergeConfig($this->config, $envConfig);
        }
    }

    private function loadEnvFile(string $envFile): void
    {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");

                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $value;
                }
            }
        }
    }

    private function getNestedValue(array $array, string $key, mixed $default = null): mixed
    {
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $current = $array;

            foreach ($keys as $k) {
                if (!is_array($current) || !array_key_exists($k, $current)) {
                    return $default;
                }
                $current = $current[$k];
            }

            return $current;
        }

        return $array[$key] ?? $default;
    }

    private function mergeConfig(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->mergeConfig($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}