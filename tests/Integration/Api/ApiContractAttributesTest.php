<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TP\Api\Dto\LoginRequest;
use TP\Api\Dto\SessionDto;
use TP\Controllers\Api\AuthController;
use TP\Core\Attributes\Body;
use TP\Core\Attributes\Returns;

class ApiContractAttributesTest extends TestCase
{
    private function attribute(string $method, string $attribute): ?object
    {
        $found = (new ReflectionMethod(AuthController::class, $method))->getAttributes($attribute);
        return $found === [] ? null : $found[0]->newInstance();
    }

    public function testMeDeclaresSessionDto(): void
    {
        $returns = $this->attribute('me', Returns::class);

        $this->assertSame(SessionDto::class, $returns->type);
        $this->assertFalse($returns->list);
        $this->assertFalse($returns->nullable);
    }

    public function testLoginDeclaresBodyAndResult(): void
    {
        $this->assertSame(LoginRequest::class, $this->attribute('login', Body::class)->type);
        $this->assertSame(SessionDto::class, $this->attribute('login', Returns::class)->type);
    }

    public function testLogoutReturnsNothing(): void
    {
        $this->assertNull($this->attribute('logout', Returns::class));
    }

    public function testLoginRequestShape(): void
    {
        $request = new LoginRequest('jane', 'secret');

        $this->assertSame(['username' => 'jane', 'password' => 'secret'], get_object_vars($request));
    }
}
