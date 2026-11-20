<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\Attributes\Patch;
use TP\Core\Attributes\RoutePrefix;
use TP\Core\Request;
use TP\Core\Response;
use TP\Core\RouteScanner;

#[RoutePrefix('/things')]
final class PatchFixtureController
{
    #[Patch('/{id}')]
    public function update(Request $request, array $params): Response
    {
        return Response::ok();
    }
}

class RouteScannerPatchTest extends TestCase
{
    public function testPatchAttributeRegistersPatchRoute(): void
    {
        $routes = (new RouteScanner())->scan(PatchFixtureController::class);

        $this->assertCount(1, $routes);
        $this->assertSame('PATCH', $routes[0]['method']);
        $this->assertSame('/things/{id}', $routes[0]['pattern']);
    }
}
