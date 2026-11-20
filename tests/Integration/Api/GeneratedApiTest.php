<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use PHPUnit\Framework\TestCase;
use TP\Core\Api\TypeScriptGenerator;

class GeneratedApiTest extends TestCase
{
    private const FILE = __DIR__ . '/../../../frontend/src/app/core/api/generated.ts';

    public function testCommittedGeneratedFileIsUpToDate(): void
    {
        $this->assertFileExists(self::FILE);
        $this->assertSame(
            TypeScriptGenerator::forApiControllers(),
            file_get_contents(self::FILE),
            'frontend/src/app/core/api/generated.ts is out of date — run: composer api:generate'
        );
    }
}
