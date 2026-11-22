<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\Translator;

class FlavorTranslationsTest extends TestCase
{
    /** @return list<string> dotted keys of string leaves */
    private static function keys(array $table, string $prefix = ''): array
    {
        $keys = [];
        foreach ($table as $k => $v) {
            $keys = is_array($v) ? [...$keys, ...self::keys($v, "$prefix$k.")] : [...$keys, "$prefix$k"];
        }
        return $keys;
    }

    public function testEveryOverrideKeyExistsInTheBaseTexts(): void
    {
        $root = __DIR__ . '/../../..';
        foreach (glob("$root/frontend/flavors/*/i18n/*.json") ?: [] as $file) {
            $base = json_decode((string) file_get_contents("$root/frontend/src/i18n/" . basename($file)), true);
            $override = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $unknown = array_diff(self::keys($override), self::keys($base));
            $this->assertSame([], array_values($unknown), "$file overrides keys that do not exist");
        }
    }

    public function testMergeReplacesOnlyOverriddenLeaves(): void
    {
        $merged = Translator::merge(
            ['app' => ['name' => 'A', 'welcome' => 'W'], 'x' => 'y'],
            ['app' => ['name' => 'B']],
        );
        $this->assertSame(['app' => ['name' => 'B', 'welcome' => 'W'], 'x' => 'y'], $merged);
    }
}
