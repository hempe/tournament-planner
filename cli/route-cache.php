#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use TP\Core\Config;
use TP\Core\RouteCache;

$config = Config::getInstance();
$cacheFile = $config->get('routing.cache_file', __DIR__ . '/../storage/cache/routes.php');
$cache = new RouteCache($cacheFile);

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'clear':
        $cache->clear();
        echo "Route cache cleared successfully.\n";
        echo "Cache file: {$cacheFile}\n";
        break;

    case 'status':
        if (file_exists($cacheFile)) {
            $size = filesize($cacheFile);
            $mtime = date('Y-m-d H:i:s', filemtime($cacheFile));
            echo "Route cache exists.\n";
            echo "File: {$cacheFile}\n";
            echo "Size: " . number_format($size) . " bytes\n";
            echo "Last modified: {$mtime}\n";

            $cached = $cache->read();
            if ($cached) {
                $routeCount = count($cached['routes']);
                $fileCount = count($cached['timestamps']);
                echo "Routes cached: {$routeCount}\n";
                echo "Controller files tracked: {$fileCount}\n";
            }
        } else {
            echo "Route cache does not exist.\n";
            echo "Expected location: {$cacheFile}\n";
        }
        break;

    case 'warm':
        require_once __DIR__ . '/../index.php';
        echo "Route cache warmed successfully.\n";
        break;

    case 'help':
    default:
        echo "Route Cache Management\n";
        echo "======================\n\n";
        echo "Usage: php cli/route-cache.php <command>\n\n";
        echo "Commands:\n";
        echo "  clear   - Clear the route cache\n";
        echo "  status  - Show cache status and statistics\n";
        echo "  warm    - Warm the cache by loading the application\n";
        echo "  help    - Show this help message\n";
        break;
}
