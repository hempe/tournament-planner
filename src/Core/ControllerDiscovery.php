<?php

declare(strict_types=1);

namespace TP\Core;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

final class ControllerDiscovery
{
    /**
     * Discover all controller classes in the specified directory.
     *
     * @param string $directory Base directory to scan
     * @param string $namespace Base namespace for the directory
     * @return array<class-string> Array of fully qualified controller class names
     */
    public function discover(string $directory, string $namespace = 'TP\\Controllers'): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $controllers = [];

        // Recursively iterate through directory
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        // Filter for PHP files
        $phpFiles = new RegexIterator($iterator, '/^.+Controller\.php$/i');

        foreach ($phpFiles as $file) {
            $className = $this->filePathToClassName($file->getPathname(), $directory, $namespace);

            if ($className && class_exists($className)) {
                $controllers[] = $className;
            }
        }

        return $controllers;
    }

    /**
     * Convert file path to fully qualified class name.
     */
    private function filePathToClassName(string $filePath, string $baseDir, string $baseNamespace): ?string
    {
        // Get relative path from base directory
        $relativePath = str_replace($baseDir, '', $filePath);
        $relativePath = ltrim($relativePath, '/\\');

        // Remove .php extension
        $relativePath = preg_replace('/\.php$/', '', $relativePath);

        // Convert path separators to namespace separators
        $relativePath = str_replace(['/', '\\'], '\\', $relativePath);

        // Build full class name
        $className = rtrim($baseNamespace, '\\') . '\\' . $relativePath;

        return $className;
    }
}
