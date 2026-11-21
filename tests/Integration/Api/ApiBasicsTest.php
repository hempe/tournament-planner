<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Core\Application;
use TP\Core\Request;
use TP\Core\Response;
use TP\Tests\Integration\IntegrationTestCase;

class ApiBasicsTest extends IntegrationTestCase
{
    public function testUnknownApiPathReturnsJson404(): void
    {
        $response = $this->json('GET', '/api/does-not-exist');

        $this->assertSame(404, $response->statusCode);
        $this->assertSame(['error' => 'errors.not_found'], $response->json());
    }

    public function testUnknownNonApiPathReturnsPlainText404(): void
    {
        $response = $this->json('GET', '/does-not-exist');

        $this->assertSame(404, $response->statusCode);
        $this->assertSame('Page not found', $response->body);
        $this->assertSame('text/plain; charset=UTF-8', $response->headers['Content-Type']);
    }

    public function testApiResponsesCarryVersionAndNoStoreHeaders(): void
    {
        $response = $this->json('GET', '/api/does-not-exist');

        $this->assertSame('dev', $response->headers['X-App-Version']);
        $this->assertSame('no-store', $response->headers['Cache-Control']);
    }

    public function testNonApiResponsesHaveNoVersionHeader(): void
    {
        $response = $this->json('GET', '/does-not-exist');

        $this->assertArrayNotHasKey('X-App-Version', $response->headers);
    }

    public function testInvalidJsonBodyReturns400(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/does-not-exist';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        Request::setRawBodyForTesting('{broken');
        Response::resetLastSent();

        ob_start();
        require __DIR__ . '/../../../index.php';
        $body = ob_get_clean();
        Request::setRawBodyForTesting(null);
        unset($_SERVER['CONTENT_TYPE']);

        $this->assertSame(400, http_response_code());
        $this->assertSame(['error' => 'errors.invalid_json'], json_decode($body, true));
    }

    public function testUncaughtExceptionInApiReturnsJson500(): void
    {
        Application::getInstance()->getRouter()->get(
            '/api/test/boom',
            fn(Request $request): Response => throw new \RuntimeException('boom')
        );

        $response = $this->json('GET', '/api/test/boom');

        $this->assertSame(500, $response->statusCode);
        $this->assertSame('errors.server_error', $response->json()['error']);
        $this->assertSame('dev', $response->headers['X-App-Version']);
    }
}
