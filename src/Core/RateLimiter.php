<?php

declare(strict_types=1);

namespace TP\Core;

/**
 * Small file-based sliding-window rate limiter (one JSON file per key).
 *
 * Storage problems never break requests: they are logged and the request is allowed.
 */
final class RateLimiter
{
    private string $directory;
    private \Closure $clock;

    public function __construct(?string $directory = null, ?\Closure $clock = null)
    {
        $this->directory = $directory ?? self::defaultDirectory();
        $this->clock = $clock ?? static fn(): int => time();
    }

    /** Limiter using $_ENV['RATE_LIMIT_DIR'] when set (tests), else storage/cache/rate-limits. */
    public static function fromEnvironment(): self
    {
        $dir = $_ENV['RATE_LIMIT_DIR'] ?? '';
        return new self(is_string($dir) && $dir !== '' ? $dir : null);
    }

    public static function defaultDirectory(): string
    {
        return __DIR__ . '/../../storage/cache/rate-limits';
    }

    /** Record an attempt. Returns false (and records nothing) when the limit is already reached. */
    public function hit(string $key, int $max, int $windowSeconds): bool
    {
        $allowed = true;
        $ok = $this->withFile($key, function (array $times) use ($max, $windowSeconds, &$allowed): array {
            $times = $this->prune($times, $windowSeconds);
            if (count($times) >= $max) {
                $allowed = false;
                return $times;
            }
            $times[] = ($this->clock)();
            return $times;
        });

        return $ok ? $allowed : true;
    }

    /** Read-only: has the key reached the limit? */
    public function tooMany(string $key, int $max, int $windowSeconds): bool
    {
        return count($this->prune($this->read($key), $windowSeconds)) >= $max;
    }

    /** Seconds until the oldest attempt in the window expires (0 when there is none). */
    public function retryAfter(string $key, int $windowSeconds): int
    {
        $times = $this->prune($this->read($key), $windowSeconds);
        if ($times === []) {
            return 0;
        }
        return max(1, min($times) + $windowSeconds - ($this->clock)());
    }

    public function clear(string $key): void
    {
        $file = $this->path($key);
        if (is_file($file) && !@unlink($file)) {
            $this->warn('Rate limiter could not clear key', $file);
        }
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.json';
    }

    /** @return int[] */
    private function read(string $key): array
    {
        $raw = @file_get_contents($this->path($key));
        $decoded = $raw === false ? null : json_decode($raw, true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_int')) : [];
    }

    /**
     * @param int[] $times
     * @return int[]
     */
    private function prune(array $times, int $windowSeconds): array
    {
        $cutoff = ($this->clock)() - $windowSeconds;
        return array_values(array_filter($times, static fn(int $t): bool => $t > $cutoff));
    }

    /**
     * Read-modify-write under an exclusive lock. Returns false when storage failed.
     *
     * @param callable(int[]): int[] $mutate
     */
    private function withFile(string $key, callable $mutate): bool
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            $this->warn('Rate limiter directory not writable', $this->directory);
            return false;
        }

        $file = $this->path($key);
        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            $this->warn('Rate limiter could not open file', $file);
            return false;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                $this->warn('Rate limiter could not lock file', $file);
                return false;
            }
            $raw = stream_get_contents($handle);
            $decoded = $raw === false || $raw === '' ? null : json_decode($raw, true);
            $times = is_array($decoded) ? array_values(array_filter($decoded, 'is_int')) : [];

            $json = json_encode($mutate($times));
            if (!ftruncate($handle, 0) || rewind($handle) === false || fwrite($handle, (string) $json) === false) {
                $this->warn('Rate limiter could not write file', $file);
                return false;
            }
            fflush($handle);
            flock($handle, LOCK_UN);
            return true;
        } finally {
            fclose($handle);
        }
    }

    private function warn(string $message, string $path): void
    {
        try {
            logger()->warning($message, ['path' => $path]);
        } catch (\Throwable) {
            // No application/logger available (e.g. unit tests); never break the request
        }
    }
}
