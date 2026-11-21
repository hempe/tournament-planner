<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Core\HealthCheck;
use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class HealthTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        HealthCheck::overrideProbe(null);
        parent::tearDown();
    }

    public function testAnonymousResponseContainsOnlyStatus(): void
    {
        $response = $this->json('GET', '/health');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['status' => 'ok'], $response->json());
    }

    public function testRegularUserSeesOnlyStatus(): void
    {
        DB::$users->create('jane', 'Pass123!');
        $this->apiLoginAs('jane', 'Pass123!');

        $this->assertSame(['status' => 'ok'], $this->json('GET', '/health')->json());
    }

    public function testAdminSeesDetails(): void
    {
        $this->apiLoginAs('admin', 'Admin123!');

        $body = $this->json('GET', '/health')->json();

        $this->assertSame('ok', $body['status']);
        $this->assertSame(PHP_VERSION, $body['php_version']);
        $this->assertSame('testing', $body['environment']);
        $this->assertSame('connected', $body['database']);
        foreach (['memory_usage', 'locale', 'timestamp'] as $key) {
            $this->assertArrayHasKey($key, $body);
        }
    }

    public function testDatabaseFailureReturns503WithoutMessage(): void
    {
        HealthCheck::overrideProbe(fn(): bool => false);

        $response = $this->json('GET', '/health');

        $this->assertSame(503, $response->statusCode);
        $this->assertSame(['status' => 'error'], $response->json());
    }

    public function testDatabaseFailureDetailIsAdminOnlyAndGeneric(): void
    {
        HealthCheck::overrideProbe(fn(): bool => false);
        $this->apiLoginAs('admin', 'Admin123!');

        $response = $this->json('GET', '/health');

        $this->assertSame(503, $response->statusCode);
        $this->assertSame('error', $response->json()['status']);
        $this->assertSame('unreachable', $response->json()['database']);
    }
}
