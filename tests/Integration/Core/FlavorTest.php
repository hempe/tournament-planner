<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TP\Core\Flavor;

class FlavorTest extends TestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        Flavor::reset();
    }

    protected function tearDown(): void
    {
        $_ENV['APP_FLAVOR'] = 'elfaro';
        Flavor::reset();
        if ($this->tmp !== '' && is_file($this->tmp)) {
            unlink($this->tmp);
        }
    }

    private function writeJson(string $json): string
    {
        $this->tmp = tempnam(sys_get_temp_dir(), 'flavor') . '.json';
        file_put_contents($this->tmp, $json);
        return $this->tmp;
    }

    public function testDefaultIsElFaroWithEverythingOn(): void
    {
        $this->assertSame('elfaro', Flavor::name());
        foreach (['socialEvents', 'guests', 'prices', 'description', 'deadline', 'genderSplit'] as $feature) {
            $this->assertTrue(Flavor::enabled($feature), $feature);
        }
        $this->assertFalse(Flavor::waitlistByPriority());
        $this->assertTrue(Flavor::hasUserField('rfeg'));
        $this->assertFalse(Flavor::hasUserField('phone'));
        $this->assertSame('Europe/Zurich', Flavor::timezone());
        $this->assertSame('GOLF EL FARO', Flavor::displayName());
        $this->assertSame('/favicon.svg', Flavor::logo());
    }

    public function testAmigosFromEnvironment(): void
    {
        $_ENV['APP_FLAVOR'] = 'amigos';
        Flavor::reset();

        $this->assertSame('amigos', Flavor::name());
        $this->assertFalse(Flavor::enabled('guests'));
        $this->assertTrue(Flavor::waitlistByPriority());
        $this->assertTrue(Flavor::hasUserField('priority'));
        $this->assertSame('lastFirst', Flavor::usernameOrder());
        $this->assertSame('Atlantic/Canary', Flavor::timezone());
    }

    public function testUnknownFlavourFailsLoudly(): void
    {
        $_ENV['APP_FLAVOR'] = 'nope';
        Flavor::reset();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown flavour "nope"');
        Flavor::name();
    }

    public function testEveryShippedFlavourIsValid(): void
    {
        foreach (glob(__DIR__ . '/../../../frontend/flavors/*/flavor.json') ?: [] as $file) {
            $this->assertIsArray(Flavor::parse($file), $file);
        }
    }

    public function testBrokenJsonFails(): void
    {
        $this->expectException(RuntimeException::class);
        Flavor::parse($this->writeJson('{ nope'));
    }

    public function testInvalidValuesFail(): void
    {
        $base = json_decode((string) file_get_contents(__DIR__ . '/../../../frontend/flavors/amigos/flavor.json'), true);
        $cases = [
            'timezone' => ['timezone' => 'Mars/Base'],
            'usernameOrder' => ['usernameOrder' => 'middle'],
            'waitlistOrder' => ['features' => ['waitlistOrder' => 'random'] + $base['features']],
            'userFields' => ['features' => ['userFields' => ['shoeSize']] + $base['features']],
            'guests' => ['features' => ['guests' => 'yes'] + $base['features']],
        ];
        foreach ($cases as $field => $change) {
            try {
                Flavor::parse($this->writeJson((string) json_encode($change + $base)));
                $this->fail("$field: expected an exception");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($field, $e->getMessage());
            }
        }
    }

    public function testOverrideAndReset(): void
    {
        Flavor::override(['guests' => false, 'waitlistOrder' => 'priority', 'userFields' => ['handicap']]);

        $this->assertFalse(Flavor::enabled('guests'));
        $this->assertTrue(Flavor::enabled('socialEvents'));
        $this->assertTrue(Flavor::waitlistByPriority());
        $this->assertTrue(Flavor::hasUserField('handicap'));
        $this->assertFalse(Flavor::hasUserField('rfeg'));

        Flavor::reset();
        $this->assertTrue(Flavor::enabled('guests'));
    }
}
