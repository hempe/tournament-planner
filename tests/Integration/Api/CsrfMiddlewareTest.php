<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Core\Application;
use TP\Core\HttpMethod;
use TP\Core\Request;
use TP\Core\Response;
use TP\Middleware\CsrfMiddleware;
use TP\Tests\Integration\IntegrationTestCase;

class CsrfMiddlewareTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $router = Application::getInstance()->getRouter();
        $router->get('/api/test/csrf', fn(Request $r): Response => Response::json(['ok' => true]), [CsrfMiddleware::class]);
        $router->post('/api/test/csrf', fn(Request $r): Response => Response::json(['ok' => true]), [CsrfMiddleware::class]);
        $router->post('/api/test/csrf-clear', function (Request $r): Response {
            $_SESSION = [];
            return Response::noContent();
        }, [CsrfMiddleware::class]);
    }

    public function testGetIssuesReadableTokenCookie(): void
    {
        $response = $this->json('GET', '/api/test/csrf');

        $this->assertSame(200, $response->statusCode);
        $cookie = $response->cookies['XSRF-TOKEN'];
        $this->assertSame($_SESSION['xsrf_token'], $cookie['value']);
        $this->assertSame(64, strlen($cookie['value']));
        $this->assertFalse($cookie['httponly'], 'Angular must be able to read the cookie');
        $this->assertSame('Strict', $cookie['samesite']);
    }

    public function testTokenIsStableWithinSession(): void
    {
        $first = $this->json('GET', '/api/test/csrf')->cookies['XSRF-TOKEN']['value'];
        $second = $this->json('GET', '/api/test/csrf')->cookies['XSRF-TOKEN']['value'];

        $this->assertSame($first, $second);
    }

    public function testPostWithoutHeaderIsRejected(): void
    {
        $this->json('GET', '/api/test/csrf');

        $response = $this->json('POST', '/api/test/csrf', [], withCsrf: false);

        $this->assertSame(403, $response->statusCode);
        $this->assertSame(['error' => 'errors.csrf'], $response->json());
        $this->assertArrayHasKey('XSRF-TOKEN', $response->cookies, 'Rejection re-issues the cookie so the client can retry');
    }

    public function testPostWithValidHeaderPasses(): void
    {
        $this->json('GET', '/api/test/csrf');

        $response = $this->json('POST', '/api/test/csrf');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['ok' => true], $response->json());
    }

    public function testCookieReflectsTokenRegeneratedByHandler(): void
    {
        $this->json('GET', '/api/test/csrf');
        $old = $_SESSION['xsrf_token'];

        $response = $this->json('POST', '/api/test/csrf-clear');

        $this->assertSame(204, $response->statusCode);
        $this->assertNotSame($old, $response->cookies['XSRF-TOKEN']['value']);
        $this->assertSame($_SESSION['xsrf_token'], $response->cookies['XSRF-TOKEN']['value']);
    }

    public function testWrongTokenIsRejected(): void
    {
        $_SESSION['xsrf_token'] = str_repeat('a', 64);
        $request = new Request(HttpMethod::POST, '/api/test/csrf', [], [], [], ['x-xsrf-token' => str_repeat('b', 64)]);

        $response = (new CsrfMiddleware())->handle($request, fn() => Response::json(['ok' => true]));

        $this->assertSame(403, $response->getStatus()->value);
    }
}
