<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\RateLimiter;

class RateLimiterTest extends TestCase
{
    private string $dir;
    private int $now = 1_000_000;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/tp-ratelimit-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->dir)) {
            chmod($this->dir, 0700);
            rmdir($this->dir);
        }
    }

    private function limiter(): RateLimiter
    {
        return new RateLimiter($this->dir, fn(): int => $this->now);
    }

    public function testAllowsUpToMaxAndBlocksNext(): void
    {
        $limiter = $this->limiter();

        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($limiter->hit('k', 3, 60));
        }
        $this->assertTrue($limiter->tooMany('k', 3, 60));
        $this->assertFalse($limiter->hit('k', 3, 60));
    }

    public function testBlockedAttemptIsNotRecorded(): void
    {
        $limiter = $this->limiter();
        $limiter->hit('k', 1, 60);
        $this->now += 30;
        $limiter->hit('k', 1, 60); // blocked, must not extend the window

        $this->now += 31;
        $this->assertTrue($limiter->hit('k', 1, 60));
    }

    public function testTooManyIsReadOnly(): void
    {
        $limiter = $this->limiter();
        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($limiter->tooMany('k', 1, 60));
        }
        $this->assertTrue($limiter->hit('k', 1, 60));
    }

    public function testWindowExpiry(): void
    {
        $limiter = $this->limiter();
        $limiter->hit('k', 2, 60);
        $limiter->hit('k', 2, 60);
        $this->assertTrue($limiter->tooMany('k', 2, 60));

        $this->now += 60;
        $this->assertFalse($limiter->tooMany('k', 2, 60));
        $this->assertTrue($limiter->hit('k', 2, 60));
    }

    public function testRetryAfter(): void
    {
        $limiter = $this->limiter();
        $this->assertSame(0, $limiter->retryAfter('k', 60));

        $limiter->hit('k', 1, 60);
        $this->now += 20;
        $limiter->hit('k', 2, 60);

        $this->assertSame(40, $limiter->retryAfter('k', 60));
        $this->now += 100;
        $this->assertSame(0, $limiter->retryAfter('k', 60));
    }

    public function testClear(): void
    {
        $limiter = $this->limiter();
        $limiter->hit('k', 1, 60);
        $this->assertTrue($limiter->tooMany('k', 1, 60));

        $limiter->clear('k');

        $this->assertFalse($limiter->tooMany('k', 1, 60));
        $limiter->clear('never-used');
    }

    public function testKeysAreIndependent(): void
    {
        $limiter = $this->limiter();
        $limiter->hit('a', 1, 60);

        $this->assertTrue($limiter->tooMany('a', 1, 60));
        $this->assertFalse($limiter->tooMany('b', 1, 60));
        $this->assertTrue($limiter->hit('b', 1, 60));
    }

    public function testPersistsAcrossInstances(): void
    {
        $this->limiter()->hit('k', 1, 60);

        $this->assertTrue($this->limiter()->tooMany('k', 1, 60));
    }

    public function testUnwritableStorageAllowsRequests(): void
    {
        // A file where the directory should be makes mkdir/writes fail
        $blocker = sys_get_temp_dir() . '/tp-ratelimit-blocker-' . bin2hex(random_bytes(4));
        file_put_contents($blocker, 'x');
        try {
            $limiter = new RateLimiter($blocker . '/sub', fn(): int => $this->now);

            for ($i = 0; $i < 5; $i++) {
                $this->assertTrue(@$limiter->hit('k', 1, 60));
            }
            $this->assertFalse($limiter->tooMany('k', 1, 60));
            $this->assertSame(0, $limiter->retryAfter('k', 60));
            $limiter->clear('k');
        } finally {
            unlink($blocker);
        }
    }
}
