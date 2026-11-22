<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Core\Flavor;
use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class UserFieldsApiTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
        $this->apiLoginAs('admin', 'Admin123!');
    }

    private function body(array $override = []): array
    {
        return $override + [
            'male' => true, 'username' => 'Muster Max', 'password' => 'Secret123!', 'firstName' => 'Max', 'lastName' => 'Muster',
            'rfeg' => 'R1', 'memberNumber' => 'M1', 'phone' => '+34 600', 'handicap' => 12.4, 'priority' => 1200,
        ];
    }

    public function testAmigosStoresPhoneHandicapPriorityAndIgnoresRfeg(): void
    {
        Flavor::override(['userFields' => ['phone', 'handicap', 'priority']]);

        $data = $this->json('POST', '/api/users', $this->body())->json();

        $this->assertSame('+34 600', $data['phone']);
        $this->assertSame(12.4, $data['handicap']);
        $this->assertSame(1200, $data['priority']);
        $this->assertNull($data['rfeg']);
        $this->assertNull($data['memberNumber']);
    }

    public function testElFaroIgnoresAndHidesAmigosFields(): void
    {
        $data = $this->json('POST', '/api/users', $this->body())->json();

        $this->assertSame('R1', $data['rfeg']);
        $this->assertNull($data['phone']);
        $this->assertNull($data['handicap']);
        $this->assertNull($data['priority']);
    }

    public function testUpdateKeepsHiddenFieldsUntouched(): void
    {
        $id = DB::$users->create('jane', 'Pass123!', true, 'R1', 'M1', 'Jane', 'Doe');
        Flavor::override(['userFields' => ['phone', 'handicap', 'priority']]);

        $this->json('PUT', "/api/users/{$id}", $this->body(['username' => 'jane', 'password' => '', 'rfeg' => null, 'memberNumber' => null]));

        Flavor::reset();
        $user = DB::$users->get($id);
        $this->assertSame('R1', $user->rfeg, 'El Faro field must survive an Amigos-style save');
        $this->assertSame(1200, $user->priority);
    }

    public function testValidation(): void
    {
        Flavor::override(['userFields' => ['phone', 'handicap', 'priority']]);

        $response = $this->json('POST', '/api/users', $this->body(['handicap' => 99.9, 'priority' => -1]));

        $this->assertSame(422, $response->statusCode);
        $this->assertArrayHasKey('handicap', $response->json()['fields']);
        $this->assertArrayHasKey('priority', $response->json()['fields']);
    }

    public function testGenderIsOptionalWhenGenderSplitIsOff(): void
    {
        Flavor::override(['genderSplit' => false]);
        $body = $this->body();
        unset($body['male']);

        $this->assertSame(200, $this->json('POST', '/api/users', $body)->statusCode);
    }
}
