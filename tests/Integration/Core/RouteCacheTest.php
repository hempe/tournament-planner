<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\RouteCache;

class RouteCacheTest extends TestCase
{
    private string $tmpFile;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/tp_route_cache_test_' . uniqid() . '.php';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testGetCacheFile(): void
    {
        $cache = new RouteCache($this->tmpFile);
        $this->assertEquals($this->tmpFile, $cache->getCacheFile());
    }

    public function testIsValidReturnsFalseWhenFileDoesNotExist(): void
    {
        $cache = new RouteCache($this->tmpFile);
        $this->assertFalse($cache->isValid());
    }

    public function testIsValidReturnsTrueInProductionModeWhenFileExists(): void
    {
        file_put_contents($this->tmpFile, "<?php return ['routes' => [], 'timestamps' => []];");
        $cache = new RouteCache($this->tmpFile);
        $this->assertTrue($cache->isValid([]));
    }

    public function testReadReturnsNullWhenFileDoesNotExist(): void
    {
        $cache = new RouteCache($this->tmpFile);
        $this->assertNull($cache->read());
    }

    public function testWriteCreatesFile(): void
    {
        $cache = new RouteCache($this->tmpFile);
        $routes = [
            ['method' => 'GET', 'pattern' => '/test', 'handler' => ['TestClass', 'method'], 'middleware' => [], 'name' => '']
        ];
        $cache->write($routes, []);

        $this->assertTrue(file_exists($this->tmpFile));
    }

    public function testWriteThenReadReturnsRoutes(): void
    {
        $cache = new RouteCache($this->tmpFile);
        $routes = [
            ['method' => 'GET', 'pattern' => '/test', 'handler' => ['TestClass', 'method'], 'middleware' => [], 'name' => '']
        ];
        $cache->write($routes, []);

        $data = $cache->read();
        $this->assertNotNull($data);
        $this->assertArrayHasKey('routes', $data);
        $this->assertEquals($routes, $data['routes']);
    }

    public function testWriteThenReadHasTimestamps(): void
    {
        $controllerFile = sys_get_temp_dir() . '/tp_ctrl_' . uniqid() . '.php';
        file_put_contents($controllerFile, '<?php // controller');

        try {
            $cache = new RouteCache($this->tmpFile);
            $cache->write([], [$controllerFile]);

            $data = $cache->read();
            $this->assertNotNull($data);
            $this->assertArrayHasKey('timestamps', $data);
            $this->assertArrayHasKey($controllerFile, $data['timestamps']);
        } finally {
            unlink($controllerFile);
        }
    }

    public function testIsValidWithCurrentTimestampIsTrue(): void
    {
        $controllerFile = sys_get_temp_dir() . '/tp_ctrl_' . uniqid() . '.php';
        file_put_contents($controllerFile, '<?php // controller');

        try {
            $cache = new RouteCache($this->tmpFile);
            $cache->write([], [$controllerFile]);

            $this->assertTrue($cache->isValid([$controllerFile]));
        } finally {
            unlink($controllerFile);
        }
    }

    public function testIsValidReturnsFalseAfterControllerModified(): void
    {
        $controllerFile = sys_get_temp_dir() . '/tp_ctrl_' . uniqid() . '.php';
        file_put_contents($controllerFile, '<?php // controller');

        try {
            $cache = new RouteCache($this->tmpFile);
            $cache->write([], [$controllerFile]);

            // Simulate file being newer than cache
            touch($controllerFile, time() + 10);

            $this->assertFalse($cache->isValid([$controllerFile]));
        } finally {
            unlink($controllerFile);
        }
    }

    public function testIsValidReturnsFalseWhenControllerFileMissing(): void
    {
        $cache = new RouteCache($this->tmpFile);
        $cache->write([], []);

        // Ask with a non-existent controller file
        $this->assertFalse($cache->isValid(['/tmp/nonexistent_controller_xyz_12345.php']));
    }

    public function testIsValidReturnsFalseWhenCacheMissingTimestamps(): void
    {
        // Write a cache without timestamps key
        file_put_contents($this->tmpFile, "<?php return ['routes' => []];");
        $cache = new RouteCache($this->tmpFile);
        $controllerFile = sys_get_temp_dir() . '/tp_ctrl_' . uniqid() . '.php';
        file_put_contents($controllerFile, '<?php');
        try {
            $this->assertFalse($cache->isValid([$controllerFile]));
        } finally {
            unlink($controllerFile);
        }
    }

    public function testClearDeletesFile(): void
    {
        file_put_contents($this->tmpFile, "<?php return [];");
        $cache = new RouteCache($this->tmpFile);

        $cache->clear();

        $this->assertFalse(file_exists($this->tmpFile));
    }

    public function testClearDoesNothingWhenFileDoesNotExist(): void
    {
        $cache = new RouteCache($this->tmpFile);
        // Should not throw
        $cache->clear();
        $this->assertFalse(file_exists($this->tmpFile));
    }

    public function testWriteCreatesDirectoryIfNeeded(): void
    {
        $dir = sys_get_temp_dir() . '/tp_cache_dir_' . uniqid();
        $file = $dir . '/routes.php';
        $cache = new RouteCache($file);

        try {
            $cache->write([], []);
            $this->assertTrue(file_exists($file));
        } finally {
            if (file_exists($file)) {
                unlink($file);
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function testReadReturnsNullForInvalidPhp(): void
    {
        file_put_contents($this->tmpFile, "<?php return 'not an array';");
        $cache = new RouteCache($this->tmpFile);
        $this->assertNull($cache->read());
    }
}
