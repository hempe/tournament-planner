<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Tests\Integration\IntegrationTestCase;

class HarnessTest extends IntegrationTestCase
{
    public function testJsonHelperExposesStatusHeadersAndDecodedBody(): void
    {
        $response = $this->json('GET', '/health');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('application/json', $response->headers['Content-Type']);
        $this->assertSame('ok', $response->json()['status']);
    }

    public function testRequestDoesNotLeakJsonStateIntoFormRequests(): void
    {
        $this->json('POST', '/health', ['a' => 1]);
        $this->request('GET', '/health');

        $this->assertArrayNotHasKey('CONTENT_TYPE', $_SERVER);
        $this->assertArrayNotHasKey('HTTP_X_XSRF_TOKEN', $_SERVER);
    }
}
