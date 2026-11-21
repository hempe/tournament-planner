<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\UserFacingException;

class UserFacingExceptionTest extends TestCase
{
    public function testMessageIsTranslatedAndKeyAndParamsAreExposed(): void
    {
        $params = ['menu' => 'Fisch', 'count' => 3];
        $e = new UserFacingException('social_events.menu_in_use', $params);

        $this->assertSame(__('social_events.menu_in_use', $params), $e->getMessage());
        $this->assertSame('social_events.menu_in_use', $e->key);
        $this->assertSame($params, $e->params);
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    public function testParamsDefaultToEmpty(): void
    {
        $this->assertSame([], (new UserFacingException('errors.general'))->params);
    }
}
