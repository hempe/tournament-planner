<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Models;

use PHPUnit\Framework\TestCase;
use TP\Models\UserRepository;

class UsernameSplitTest extends TestCase
{
    /** @return array<string, array{string, string, string}> */
    public static function names(): array
    {
        return [
            'plain' => ['Anna Albler', 'Anna', 'Albler'],
            'several first names' => ['Juan Carlos Aranda Cabello', 'Juan Carlos Aranda', 'Cabello'],
            'title after first name' => ['Karl-Uwe, Dr. Bäcker', 'Karl-Uwe', 'Bäcker'],
            'title at the end' => ['Martin Heinisch, Dr.', 'Martin', 'Heinisch'],
            'title without dot' => ['Hans, Dr Muster', 'Hans', 'Muster'],
            'Mag. title' => ['Christine, Mag. Strohmüller', 'Christine', 'Strohmüller'],
            'Prof. title' => ['Eva, Prof. Meier', 'Eva', 'Meier'],
            'leading title' => ['Dr. Hans Muster', 'Hans', 'Muster'],
            'name containing dr' => ['Gunther Drenkow', 'Gunther', 'Drenkow'],
            'single word' => ['bob', '', 'bob'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('names')]
    public function testSplitsUsername(string $username, string $first, string $last): void
    {
        $this->assertSame([$first, $last], UserRepository::splitUsername($username));
    }
}
