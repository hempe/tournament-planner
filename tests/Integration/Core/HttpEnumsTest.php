<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use ReflectionEnum;
use TP\Core\HttpMethod;
use TP\Core\HttpStatus;

/**
 * HttpStatus/HttpMethod used to be declared twice (inside Response.php/Request.php and in
 * their own files, with different cases); which copy PHP loaded depended on load order.
 */
class HttpEnumsTest extends TestCase
{
    public function testEnumsAreDeclaredOnlyInTheirOwnFiles(): void
    {
        $this->assertStringEndsWith('Core/HttpStatus.php', (new ReflectionEnum(HttpStatus::class))->getFileName());
        $this->assertStringEndsWith('Core/HttpMethod.php', (new ReflectionEnum(HttpMethod::class))->getFileName());

        foreach (glob(__DIR__ . '/../../../src/Core/*.php') as $file) {
            $count = preg_match_all('/^enum (HttpStatus|HttpMethod)\b/m', (string) file_get_contents($file));
            $expected = in_array(basename($file), ['HttpStatus.php', 'HttpMethod.php'], true) ? 1 : 0;
            $this->assertSame($expected, $count, basename($file));
        }
    }

    public function testStatusContainsAllUsedCodes(): void
    {
        $this->assertSame(303, HttpStatus::SEE_OTHER->value);
        $this->assertSame(409, HttpStatus::CONFLICT->value);
        $this->assertSame(429, HttpStatus::TOO_MANY_REQUESTS->value);
    }
}
