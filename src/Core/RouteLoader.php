<?php

declare(strict_types=1);

namespace TP\Core;

final class RouteLoader
{
    private RouteScanner $scanner;
    private ControllerDiscovery $discovery;
    private ?RouteCache $cache = null;
    private bool $cacheEnabled;

    public function __construct()
    {
        $config = Config::getInstance();
        $this->scanner = new RouteScanner();
        $this->discovery = new ControllerDiscovery();
        $this->cacheEnabled = $config->get('routing.cache_enabled', false);

        if ($this->cacheEnabled) {
            $cacheFile = $config->get('routing.cache_file', __DIR__ . '/../../storage/cache/routes.php');
            $this->cache = new RouteCache($cacheFile);
        }
    }

    /**
     * Load routes into the router.
     */
    public function load(Router $router): void
    {
        $routes = $this->getRoutes();

        foreach ($routes as $route) {
            $router->loadRoutes([$route]);
        }
    }

    /**
     * Get routes from cache or by scanning controllers.
     *
     * @return array<array{method: string, pattern: string, handler: array, middleware: array, name: string}>
     */
    private function getRoutes(): array
    {
        // Try cache first if enabled
        if ($this->cacheEnabled && $this->cache) {
            $controllerFiles = $this->getControllerFiles();

            if ($this->cache->isValid($controllerFiles)) {
                $cached = $this->cache->read();
                if ($cached !== null) {
                    return $cached['routes'];
                }
            }

            // Cache miss or invalid - scan and rebuild cache
            $routes = $this->scanControllers();
            $this->cache->write($routes, $controllerFiles);
            return $routes;
        }

        // Cache disabled - scan directly
        return $this->scanControllers();
    }

    /**
     * Scan all controllers for routes.
     *
     * @return array<array{method: string, pattern: string, handler: array, middleware: array, name: string}>
     */
    private function scanControllers(): array
    {
        $controllersDir = __DIR__ . '/../Controllers';
        $controllers = $this->discovery->discover($controllersDir);

        $routes = [];
        foreach ($controllers as $controller) {
            $controllerRoutes = $this->scanner->scan($controller);
            $routes = array_merge($routes, $controllerRoutes);
        }

        return $routes;
    }

    /**
     * Get list of controller file paths for cache invalidation.
     *
     * @return array<string>
     */
    private function getControllerFiles(): array
    {
        $controllersDir = __DIR__ . '/../Controllers';

        if (!is_dir($controllersDir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($controllersDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $phpFiles = new \RegexIterator($iterator, '/^.+\.php$/i');

        foreach ($phpFiles as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }
}
