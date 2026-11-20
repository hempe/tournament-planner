<?php

declare(strict_types=1);

namespace TP\Core;

/**
 * Release version written to version.txt by build.sh ("YYYY-MM-DD-<hash>").
 * Falls back to "dev" when running from the repository.
 */
final class AppVersion
{
    private static ?string $current = null;

    public static function current(): string
    {
        return self::$current ??= self::fromFile(__DIR__ . '/../../version.txt');
    }

    public static function fromFile(string $file): string
    {
        if (!is_file($file)) {
            return 'dev';
        }
        $version = trim((string) file_get_contents($file));
        return $version === '' ? 'dev' : $version;
    }
}
