<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class SocialEventAdminApiTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    private function admin(): void
    {
        $this->apiLoginAs('admin', 'Admin123!');
    }

    private function body(array $override = []): array
    {
        return $override + [
            'name' => 'Dinner',
            'date' => '2099-06-01',
            'tournamentId' => null,
            'description' => 'Nice',
            'registrationClose' => '2099-05-30T12:00',
            'menus' => 'Fisch,Fleisch',
            'tables' => '4,6',
        ];
    }

    private function socialEvent(string $menus = 'Fisch,Fleisch', string $tables = '4,6'): int
    {
        return DB::$socialEvents->add('Dinner', '2099-06-01', null, null, null, $menus, $tables);
    }

    public function testAccessMatrix(): void
    {
        $id = $this->socialEvent();

        $this->assertSame(401, $this->json('GET', "/api/social-events/{$id}/admin")->statusCode);
        $this->assertSame(401, $this->json('POST', '/api/social-events', $this->body())->statusCode);
        $this->assertSame(401, $this->json('DELETE', "/api/social-events/{$id}")->statusCode);

        DB::$users->create('jane', 'Pass123!', firstName: 'Jane', lastName: 'Doe');
        $this->apiLoginAs('jane', 'Pass123!');
        $this->assertSame(403, $this->json('GET', "/api/social-events/{$id}/admin")->statusCode);
        $this->assertSame(403, $this->json('POST', '/api/social-events', $this->body())->statusCode);
        $this->assertSame(403, $this->json('PUT', "/api/social-events/{$id}", $this->body())->statusCode);
        $this->assertSame(403, $this->json('DELETE', "/api/social-events/{$id}")->statusCode);
        $this->assertSame(403, $this->json('PUT', "/api/social-events/{$id}/lock")->statusCode);
        $this->assertSame(403, $this->json('DELETE', "/api/social-events/{$id}/lock")->statusCode);
        $this->assertSame(403, $this->json('DELETE', "/api/social-events/{$id}/registrations/1")->statusCode);
    }

    public function testCreateLinkedToTournamentAndStandalone(): void
    {
        $this->admin();
        $tournamentId = DB::$events->add('Cup', '2099-06-01', 10);

        $linked = $this->json('POST', '/api/social-events', $this->body(['tournamentId' => $tournamentId]));
        $this->assertSame(200, $linked->statusCode);
        $data = $linked->json();
        $this->assertSame($tournamentId, $data['event']['tournamentId']);
        $this->assertSame('Dinner', $data['event']['name']);
        $this->assertSame('2099-06-01', $data['event']['date']);
        $this->assertSame('2099-05-30T12:00:00', $data['registrationClose']);
        $this->assertSame(['Fisch', 'Fleisch'], array_column($data['menuOptions'], 'name'));
        $this->assertSame([4, 6], array_column($data['tableOptions'], 'capacity'));

        $standalone = $this->json('POST', '/api/social-events', $this->body());
        $this->assertSame(200, $standalone->statusCode);
        $this->assertNull($standalone->json()['event']['tournamentId']);
    }

    public function testCreateValidation(): void
    {
        $this->admin();

        $response = $this->json('POST', '/api/social-events', ['menus' => '', 'tables' => '']);

        $this->assertSame(422, $response->statusCode);
        $json = $response->json();
        $this->assertSame('validation.failed', $json['error']);
        $this->assertArrayHasKey('name', $json['fields']);
        $this->assertArrayHasKey('date', $json['fields']);
    }

    public function testAdminDetailShowsStringsAndRegistrations(): void
    {
        $this->admin();
        $id = $this->socialEvent();
        $userId = DB::$users->create('jane', 'Pass123!', firstName: 'Jane', lastName: 'Doe');
        $menus = DB::$socialEvents->menus($id);
        $tables = DB::$socialEvents->tables($id);
        DB::$socialEvents->register($id, $userId, $menus[0]->id, $tables[0]->id);
        DB::$socialEvents->registerGuest($id, 'Gus', 'Guest', 'gus@example.com', $menus[1]->id, null);

        $response = $this->json('GET', "/api/social-events/{$id}/admin");

        $this->assertSame(200, $response->statusCode);
        $data = $response->json();
        $this->assertSame('Fisch, Fleisch', $data['menus']);
        $this->assertSame('4, 6', $data['tables']);
        $this->assertCount(2, $data['registrations']);
        $byName = array_column($data['registrations'], null, 'name');
        $this->assertFalse($byName['Jane Doe']['isGuest']);
        $this->assertSame('Fisch', $byName['Jane Doe']['menuName']);
        $this->assertSame(1, $byName['Jane Doe']['tableNumber']);
        $this->assertTrue($byName['Gus Guest']['isGuest']);
        $this->assertSame('gus@example.com', $byName['Gus Guest']['email']);
        $this->assertNull($byName['Gus Guest']['tableNumber']);
    }

    public function testDetailNotFound(): void
    {
        $this->admin();

        $response = $this->json('GET', '/api/social-events/99999/admin');

        $this->assertSame(404, $response->statusCode);
        $this->assertSame('social_events.not_found', $response->json()['error']);
    }

    public function testUpdateMenusAndFields(): void
    {
        $this->admin();
        $id = $this->socialEvent();

        $response = $this->json('PUT', "/api/social-events/{$id}", $this->body([
            'name' => 'Gala',
            'menus' => 'Fisch,Veggie',
            'tables' => '4,6',
            'tournamentId' => 12345,
        ]));

        $this->assertSame(200, $response->statusCode);
        $data = $response->json();
        $this->assertSame('Gala', $data['event']['name']);
        $this->assertNull($data['event']['tournamentId']);
        $this->assertSame('Fisch, Veggie', $data['menus']);
    }

    public function testRemovingMenuInUseConflicts(): void
    {
        $this->admin();
        $id = $this->socialEvent();
        $userId = DB::$users->create('jane', 'Pass123!', firstName: 'Jane', lastName: 'Doe');
        DB::$socialEvents->register($id, $userId, DB::$socialEvents->menus($id)[1]->id, null);

        $response = $this->json('PUT', "/api/social-events/{$id}", $this->body(['menus' => 'Fisch']));

        $this->assertSame(409, $response->statusCode);
        $json = $response->json();
        $this->assertSame('social_events.menu_in_use', $json['error']);
        $this->assertSame('Fleisch', $json['params']['menu']);
        $this->assertSame(1, $json['params']['count']);
    }

    public function testLoweringTableCapacityBelowRegistrationsConflicts(): void
    {
        $this->admin();
        $id = $this->socialEvent('', '4');
        $tableId = DB::$socialEvents->tables($id)[0]->id;
        DB::$socialEvents->registerGuest($id, 'A', 'One', 'a@example.com', null, $tableId);
        DB::$socialEvents->registerGuest($id, 'B', 'Two', 'b@example.com', null, $tableId);

        $response = $this->json('PUT', "/api/social-events/{$id}", $this->body(['menus' => '', 'tables' => '1']));

        $this->assertSame(409, $response->statusCode);
        $this->assertSame('social_events.table_capacity_too_low', $response->json()['error']);
        $this->assertArrayHasKey('params', $response->json());
    }

    public function testDelete(): void
    {
        $this->admin();
        $id = $this->socialEvent();

        $this->assertSame(204, $this->json('DELETE', "/api/social-events/{$id}")->statusCode);
        $this->assertSame(404, $this->json('GET', "/api/social-events/{$id}/admin")->statusCode);
        $this->assertSame(404, $this->json('DELETE', "/api/social-events/{$id}")->statusCode);
    }

    public function testLockAndUnlock(): void
    {
        $this->admin();
        $id = $this->socialEvent();

        $locked = $this->json('PUT', "/api/social-events/{$id}/lock");
        $this->assertSame(200, $locked->statusCode);
        $this->assertTrue($locked->json()['event']['isLocked']);

        $unlocked = $this->json('DELETE', "/api/social-events/{$id}/lock");
        $this->assertSame(200, $unlocked->statusCode);
        $this->assertFalse($unlocked->json()['event']['isLocked']);
    }

    public function testRemoveRegistration(): void
    {
        $this->admin();
        $id = $this->socialEvent();
        $other = $this->socialEvent();
        DB::$socialEvents->registerGuest($id, 'Gus', 'Guest', 'gus@example.com', null, null);
        DB::$socialEvents->registerGuest($other, 'Ann', 'Other', 'ann@example.com', null, null);
        $registrationId = DB::$socialEvents->registrations($id)[0]->id;
        $foreignId = DB::$socialEvents->registrations($other)[0]->id;

        $foreign = $this->json('DELETE', "/api/social-events/{$id}/registrations/{$foreignId}");
        $this->assertSame(404, $foreign->statusCode);
        $this->assertCount(1, DB::$socialEvents->registrations($other));

        $response = $this->json('DELETE', "/api/social-events/{$id}/registrations/{$registrationId}");
        $this->assertSame(200, $response->statusCode);
        $this->assertSame([], $response->json()['registrations']);
    }
}
