<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\HttpMethod;
use TP\Core\Request;
use TP\Core\Response;
use TP\Core\Router;

final class OrderFixtureController
{
    public function byParam(Request $request, array $params): Response
    {
        return Response::json(['hit' => 'param', 'params' => $params]);
    }

    public function me(Request $request): Response
    {
        return Response::json(['hit' => 'me']);
    }
}

class RouterOrderTest extends TestCase
{
    private function router(): Router
    {
        $router = new Router();
        $router->loadRoutes([
            ['method' => 'GET', 'pattern' => '/x/{id}/items/{itemId}', 'handler' => [OrderFixtureController::class, 'byParam'], 'middleware' => [], 'name' => ''],
            ['method' => 'GET', 'pattern' => '/x/{id}/items/me', 'handler' => [OrderFixtureController::class, 'me'], 'middleware' => [], 'name' => ''],
        ]);
        return $router;
    }

    private function hit(string $uri): array
    {
        $response = $this->router()->handle(new Request(HttpMethod::GET, $uri, [], [], [], []));
        return json_decode($response->getContent(), true);
    }

    public function testLiteralSegmentWinsOverParameterRegardlessOfDeclarationOrder(): void
    {
        $this->assertSame('me', $this->hit('/x/5/items/me')['hit']);
    }

    public function testParameterRouteStillMatchesOtherValues(): void
    {
        $result = $this->hit('/x/5/items/7');
        $this->assertSame('param', $result['hit']);
        $this->assertSame('7', $result['params']['itemId']);
    }
}
