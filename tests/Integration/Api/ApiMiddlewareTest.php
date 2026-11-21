<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Core\Application;
use TP\Core\Request;
use TP\Core\Response;
use TP\Middleware\AdminMiddleware;
use TP\Middleware\AuthMiddleware;
use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class ApiMiddlewareTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $router = Application::getInstance()->getRouter();
        $router->get('/api/test/auth-only', fn(Request $r): Response => Response::json(['ok' => true]), [AuthMiddleware::class]);
        $router->get('/api/test/admin-only', fn(Request $r): Response => Response::json(['ok' => true]), [AdminMiddleware::class]);
    }

    public function testAuthMiddlewareReturns401JsonForAnonymous(): void
    {
        $response = $this->json('GET', '/api/test/auth-only');

        $this->assertSame(401, $response->statusCode);
        $this->assertSame(['error' => 'auth.login_required'], $response->json());
    }

    public function testAdminMiddlewareReturns401JsonForAnonymous(): void
    {
        $response = $this->json('GET', '/api/test/admin-only');

        $this->assertSame(401, $response->statusCode);
        $this->assertSame(['error' => 'auth.login_required'], $response->json());
    }

    public function testAdminMiddlewareReturns403JsonForRegularUser(): void
    {
        DB::$users->create('regularuser', 'Pass123!');
        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->json('GET', '/api/test/admin-only');

        $this->assertSame(403, $response->statusCode);
        $this->assertSame(['error' => 'errors.forbidden'], $response->json());
    }

    public function testAdminPasses(): void
    {
        $this->loginAsAdmin();

        $this->assertSame(['ok' => true], $this->json('GET', '/api/test/admin-only')->json());
    }
}
