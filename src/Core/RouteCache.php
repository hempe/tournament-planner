<?php

declare(strict_types=1);

namespace TP\Core;

final class RouteCache
{
    public function __construct(
        private readonly string $cacheFile
    ) {
    }

    /**
     * Check if cache exists and is valid.
     *
     * @param array<string> $controllerFiles Controller file paths to check timestamps
     */
    public function isValid(array $controllerFiles = []): bool
    {
        if (!file_exists($this->cacheFile)) {
            return false;
        }

        // In production mode (no controller files provided), trust the cache
        if (empty($controllerFiles)) {
            return true;
        }

        // In development mode, check file modification times
        $cacheData = $this->read();
        if (!isset($cacheData['timestamps'])) {
            return false;
        }

        foreach ($controllerFiles as $file) {
            if (!file_exists($file)) {
                return false;
            }

            $currentMtime = filemtime($file);
            $cachedMtime = $cacheData['timestamps'][$file] ?? null;

            if ($cachedMtime === null || $currentMtime > $cachedMtime) {
                return false;
            }
        }

        return true;
    }

    /**
     * Read routes from cache.
     *
     * @return array{routes: array, timestamps: array<string, int>}|null
     */
    public function read(): ?array
    {
        if (!file_exists($this->cacheFile)) {
            return null;
        }

        try {
            $data = require $this->cacheFile;
            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Write routes to cache with file timestamps.
     *
     * @param array $routes Route definitions
     * @param array<string> $controllerFiles Controller file paths
     */
    public function write(array $routes, array $controllerFiles): void
    {
        // Build timestamps array
        $timestamps = [];
        foreach ($controllerFiles as $file) {
            if (file_exists($file)) {
                $timestamps[$file] = filemtime($file);
            }
        }

        $data = [
            'routes' => $routes,
            'timestamps' => $timestamps,
        ];

        // Use var_export for optimal performance
        $export = var_export($data, true);
        $content = "<?php\n\nreturn {$export};\n";

        // Atomic write using temp file + rename
        $tempFile = $this->cacheFile . '.' . uniqid('tmp', true);
        $cacheDir = dirname($this->cacheFile);

        // Ensure cache directory exists
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        file_put_contents($tempFile, $content, LOCK_EX);
        rename($tempFile, $this->cacheFile);

        // Invalidate OPcache if available
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->cacheFile, true);
        }
    }

    /**
     * Clear the cache.
     */
    public function clear(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);

            // Invalidate OPcache if available
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($this->cacheFile, true);
            }
        }
    }

    /**
     * Get the cache file path.
     */
    public function getCacheFile(): string
    {
        return $this->cacheFile;
    }
}
