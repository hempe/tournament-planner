<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\Config;
use TP\Core\Environment;

/**
 * Integration tests for Config class
 */
class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset singleton for each test
        $reflection = new \ReflectionClass(Config::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }

    // Environment Tests

    public function testGetEnvironmentReturnsTesting(): void
    {
        $_ENV['APP_ENV'] = 'testing';
        $config = Config::getInstance();

        $this->assertEquals(Environment::TESTING, $config->getEnvironment());
        $this->assertTrue($config->isTesting());
        $this->assertFalse($config->isDevelopment());
        $this->assertFalse($config->isProduction());
    }

    public function testGetEnvironmentReturnsDevelopment(): void
    {
        $_ENV['APP_ENV'] = 'development';

        // Reset singleton
        $reflection = new \ReflectionClass(Config::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $config = Config::getInstance();

        $this->assertEquals(Environment::DEVELOPMENT, $config->getEnvironment());
        $this->assertTrue($config->isDevelopment());
        $this->assertFalse($config->isTesting());
        $this->assertFalse($config->isProduction());
    }

    public function testGetEnvironmentReturnsProduction(): void
    {
        $_ENV['APP_ENV'] = 'production';

        $reflection = new \ReflectionClass(Config::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $config = Config::getInstance();

        $this->assertEquals(Environment::PRODUCTION, $config->getEnvironment());
        $this->assertTrue($config->isProduction());
        $this->assertFalse($config->isDevelopment());
        $this->assertFalse($config->isTesting());
    }

    public function testGetEnvironmentDefaultsToDevelopment(): void
    {
        unset($_ENV['APP_ENV']);

        $reflection = new \ReflectionClass(Config::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $config = Config::getInstance();

        $this->assertEquals(Environment::DEVELOPMENT, $config->getEnvironment());
        $this->assertTrue($config->isDevelopment());
    }

    // Configuration Access Tests

    public function testGetReturnsTopLevelConfig(): void
    {
        $config = Config::getInstance();

        $appConfig = $config->get('app');

        $this->assertIsArray($appConfig);
        $this->assertArrayHasKey('name', $appConfig);
    }

    public function testGetReturnsNestedConfigWithDotNotation(): void
    {
        $config = Config::getInstance();

        $appName = $config->get('app.name');

        $this->assertIsString($appName);
        $this->assertNotEmpty($appName);
    }

    public function testGetReturnsDeepNestedConfig(): void
    {
        $config = Config::getInstance();

        $dbHost = $config->get('database.host');
        $dbPort = $config->get('database.port');

        $this->assertIsString($dbHost);
        $this->assertIsInt($dbPort);
    }

    public function testGetReturnsDefaultForNonexistentKey(): void
    {
        $config = Config::getInstance();

        $value = $config->get('nonexistent.key', 'default_value');

        $this->assertEquals('default_value', $value);
    }

    public function testGetReturnsNullForNonexistentKeyWithoutDefault(): void
    {
        $config = Config::getInstance();

        $value = $config->get('nonexistent.key');

        $this->assertNull($value);
    }

    // Default Configuration Tests

    public function testDefaultAppConfiguration(): void
    {
        $config = Config::getInstance();

        $this->assertIsString($config->get('app.name'));
        $this->assertIsString($config->get('app.url'));
        $this->assertIsString($config->get('app.timezone'));
        $this->assertIsString($config->get('app.locale'));
    }

    public function testDefaultDatabaseConfiguration(): void
    {
        $config = Config::getInstance();

        $this->assertIsString($config->get('database.host'));
        $this->assertIsInt($config->get('database.port'));
        $this->assertIsString($config->get('database.name'));
        $this->assertIsString($config->get('database.username'));
        $this->assertIsString($config->get('database.charset'));
    }

    public function testDefaultSecurityConfiguration(): void
    {
        $config = Config::getInstance();

        $this->assertIsInt($config->get('security.session_lifetime'));
        $this->assertIsString($config->get('security.csrf_token_name'));
        $this->assertIsInt($config->get('security.password_min_length'));
    }

    public function testDefaultLoggingConfiguration(): void
    {
        $config = Config::getInstance();

        $this->assertIsString($config->get('logging.level'));
        $this->assertIsString($config->get('logging.file'));
    }

    public function testDefaultRoutingConfiguration(): void
    {
        $config = Config::getInstance();

        $this->assertIsBool($config->get('routing.cache_enabled'));
        $this->assertIsString($config->get('routing.cache_file'));
    }

    // Environment Variable Tests

    public function testConfigReadsFromEnvironment(): void
    {
        // In testing environment, these are already set from phpunit.xml
        $config = Config::getInstance();

        // Just verify that config can read values (actual values come from test env)
        $appName = $config->get('app.name');
        $dbHost = $config->get('database.host');

        $this->assertNotEmpty($appName);
        $this->assertNotEmpty($dbHost);
    }

    public function testConfigCastsPortToInteger(): void
    {
        $_ENV['DB_PORT'] = '3308';

        $reflection = new \ReflectionClass(Config::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $config = Config::getInstance();

        $port = $config->get('database.port');

        $this->assertIsInt($port);
        $this->assertEquals(3308, $port);
    }

    public function testConfigCastsSessionLifetimeToInteger(): void
    {
        $_ENV['SESSION_LIFETIME'] = '7200';

        $reflection = new \ReflectionClass(Config::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $config = Config::getInstance();

        $lifetime = $config->get('security.session_lifetime');

        $this->assertIsInt($lifetime);
        $this->assertEquals(7200, $lifetime);
    }

    // Routing Cache Tests

    public function testRoutingCacheEnabledInProduction(): void
    {
        $_ENV['APP_ENV'] = 'production';
        unset($_ENV['ROUTE_CACHE_ENABLED']);

        $reflection = new \ReflectionClass(Config::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $config = Config::getInstance();

        $this->assertTrue($config->get('routing.cache_enabled'));
    }

    public function testRoutingCacheDisabledInDevelopment(): void
    {
        $_ENV['APP_ENV'] = 'development';
        unset($_ENV['ROUTE_CACHE_ENABLED']);

        $reflection = new \ReflectionClass(Config::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $config = Config::getInstance();

        $this->assertFalse($config->get('routing.cache_enabled'));
    }

    // Logging Level Tests

    public function testLoggingLevelIsDebugInDevelopment(): void
    {
        $_ENV['APP_ENV'] = 'development';
        unset($_ENV['LOG_LEVEL']);

        $reflection = new \ReflectionClass(Config::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $config = Config::getInstance();

        $this->assertEquals('DEBUG', $config->get('logging.level'));
    }

    public function testLoggingLevelIsSet(): void
    {
        $config = Config::getInstance();

        $level = $config->get('logging.level');

        // In test environment, should be a valid log level
        $this->assertIsString($level);
        $this->assertContains($level, ['DEBUG', 'INFO', 'WARNING', 'ERROR']);
    }

    // Singleton Tests

    public function testGetInstanceReturnsSameInstance(): void
    {
        $config1 = Config::getInstance();
        $config2 = Config::getInstance();

        $this->assertSame($config1, $config2);
    }

    // Nested Key Tests

    public function testGetHandlesDeepNesting(): void
    {
        $config = Config::getInstance();

        // Deep nesting should return default if path doesn't exist
        $value = $config->get('level1.level2.level3.level4', 'default');

        $this->assertEquals('default', $value);
    }

    public function testGetHandlesNonArrayValue(): void
    {
        $config = Config::getInstance();

        // Trying to access sub-key of non-array value should return default
        $value = $config->get('app.name.invalid', 'default');

        $this->assertEquals('default', $value);
    }
}
