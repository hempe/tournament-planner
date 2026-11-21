<?php

declare(strict_types=1);

namespace TP\Core;

use Closure;
use Throwable;
use TP\Models\User;

/**
 * Backs the /health endpoint. Anonymous callers only learn whether the app works;
 * environment details are reserved for logged-in admins. Error messages are logged, never returned.
 */
final class HealthCheck
{
    /** @var (Closure(): bool)|null Test seam: replaces the real database probe. */
    private static ?Closure $probeOverride = null;

    /** @param (Closure(): bool)|null $probe */
    public static function overrideProbe(?Closure $probe): void
    {
        self::$probeOverride = $probe;
    }

    public static function respond(): Response
    {
        $databaseOk = self::databaseReachable();
        $body = ['status' => $databaseOk ? 'ok' : 'error'];

        if (User::admin()) {
            $config = Config::getInstance();
            $body += [
                'timestamp' => date('c'),
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true),
                'environment' => $config->getEnvironment()->value,
                'app_name' => $config->get('app.name'),
                'locale' => $config->get('app.locale'),
                'database' => $databaseOk ? 'connected' : 'unreachable',
            ];
        }

        return Response::json($body, $databaseOk ? HttpStatus::OK : HttpStatus::SERVICE_UNAVAILABLE);
    }

    private static function databaseReachable(): bool
    {
        if (self::$probeOverride !== null) {
            return (self::$probeOverride)();
        }

        try {
            $config = Config::getInstance();
            $connection = new \mysqli(
                (string) $config->get('database.host'),
                (string) $config->get('database.username'),
                (string) $config->get('database.password'),
                (string) $config->get('database.name'),
                (int) $config->get('database.port')
            );
            $connection->close();
            return true;
        } catch (Throwable $e) {
            logger()->error('Health check: database unreachable', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
