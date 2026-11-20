<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\AppVersion;

class AppVersionTest extends TestCase
{
    public function testReadsTrimmedVersionFromFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ver');
        file_put_contents($file, "2026-12-04-a1b2c3d\n");

        $this->assertSame('2026-12-04-a1b2c3d', AppVersion::fromFile($file));
        unlink($file);
    }

    public function testFallsBackToDevWhenFileMissingOrEmpty(): void
    {
        $this->assertSame('dev', AppVersion::fromFile('/nonexistent/version.txt'));

        $file = tempnam(sys_get_temp_dir(), 'ver');
        file_put_contents($file, "  \n");
        $this->assertSame('dev', AppVersion::fromFile($file));
        unlink($file);
    }

    public function testCurrentIsDevInRepository(): void
    {
        $this->assertSame('dev', AppVersion::current());
    }
}
