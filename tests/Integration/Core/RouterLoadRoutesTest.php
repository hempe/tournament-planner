<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\Router;

class RouterLoadRoutesTest extends TestCase
{
    public function testLoadingTheSameRoutesTwiceDoesNotDuplicateThem(): void
    {
        $routes = [
            ['method' => 'GET', 'pattern' => '/x/{id}', 'handler' => ['A', 'show'], 'middleware' => [], 'name' => ''],
            ['method' => 'GET', 'pattern' => '/x/me', 'handler' => ['A', 'me'], 'middleware' => [], 'name' => ''],
        ];
        $router = new Router();

        $router->loadRoutes($routes);
        $router->loadRoutes($routes);

        $this->assertCount(2, $router->getRoutes());
        $this->assertSame('/x/me', $router->getRoutes()[0]['pattern'], 'literal route first');
    }
}
