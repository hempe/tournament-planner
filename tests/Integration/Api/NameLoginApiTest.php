<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class NameLoginApiTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
        DB::$users->create('mmuster', 'Pass123!', true, null, null, 'Max', 'Muster');
    }

    private function login(string $name): int
    {
        $_SESSION = [];
        return $this->apiLoginAs($name, 'Pass123!')->statusCode;
    }

    public function testUsernameStillWorks(): void
    {
        $this->assertSame(200, $this->login('mmuster'));
    }

    public function testFirstLastAndLastFirstCaseAndSpaceInsensitive(): void
    {
        $this->assertSame(200, $this->login('Max Muster'));
        $this->assertSame(200, $this->login('muster   max'));
        $this->assertSame(200, $this->login('  MAX MUSTER '));
    }

    public function testAmbiguousNameNeverLogsIn(): void
    {
        DB::$users->create('max2', 'Pass123!', true, null, null, 'Max', 'Muster');
        $this->assertSame(422, $this->login('Max Muster'));
    }

    public function testExactUsernameWinsOverANameMatch(): void
    {
        // another user's login IS "Max Muster": exact username match is tried first
        DB::$users->create('Max Muster', 'Other123!', true, null, null, 'Someone', 'Else');
        $this->assertSame(422, $this->login('Max Muster')); // password belongs to mmuster, not to the exact match
    }

    public function testWrongPasswordFails(): void
    {
        $this->assertSame(422, $this->apiLoginAs('Max Muster', 'nope')->statusCode);
    }
}
