<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

/**
 * Behaviours that used to be covered only by the legacy HTML controller tests.
 */
class ParityApiTest extends IntegrationTestCase
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

    private function eventBody(array $override = []): array
    {
        return $override + [
            'name' => 'Cup', 'date' => '2099-06-01', 'capacity' => 2, 'mixed' => false,
            'description' => null, 'priceMembers' => null, 'priceGuests' => null, 'registrationClose' => null,
        ];
    }

    private function socialBody(array $override = []): array
    {
        return $override + [
            'name' => 'Dinner', 'date' => '2099-06-01', 'tournamentId' => null,
            'description' => null, 'registrationClose' => null, 'menus' => 'Fisch,Fleisch', 'tables' => '4,6',
        ];
    }

    public function testRaisingCapacityPromotesWaitlist(): void
    {
        $a = DB::$users->create('a', 'Pass123!');
        $b = DB::$users->create('b', 'Pass123!');
        $eventId = DB::$events->add('Cup', '2099-06-01', 1);
        DB::$events->register($eventId, $a, '');
        DB::$events->register($eventId, $b, '');
        $this->admin();

        $this->assertSame(1, $this->json('GET', "/api/events/{$eventId}/admin")->json()['event']['waitlist']);

        $this->assertSame(200, $this->json('PUT', "/api/events/{$eventId}", $this->eventBody(['capacity' => 2]))->statusCode);

        $data = $this->json('GET', "/api/events/{$eventId}/admin")->json();
        $this->assertSame(0, $data['event']['waitlist']);
        $this->assertSame(['registered', 'registered'], array_column($data['registrations'], 'state'));
    }

    public function testLoweringCapacityMovesNewestToWaitlist(): void
    {
        $a = DB::$users->create('a', 'Pass123!');
        $b = DB::$users->create('b', 'Pass123!');
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        DB::$events->register($eventId, $a, '');
        DB::$events->register($eventId, $b, '');
        $this->admin();

        $this->json('PUT', "/api/events/{$eventId}", $this->eventBody(['capacity' => 1]));

        $data = $this->json('GET', "/api/events/{$eventId}/admin")->json();
        $this->assertSame(1, $data['event']['waitlist']);
    }

    public function testUpdateClearsOptionalEventFields(): void
    {
        $this->admin();
        $created = $this->json('POST', '/api/events', $this->eventBody([
            'mixed' => true, 'description' => 'D', 'priceMembers' => 'CHF 1', 'priceGuests' => 'CHF 2',
            'registrationClose' => '2099-05-30T12:00',
        ]))->json();
        $id = $created['event']['id'];

        $data = $this->json('PUT', "/api/events/{$id}", $this->eventBody())->json();

        $this->assertFalse($data['mixed']);
        $this->assertNull($data['description']);
        $this->assertNull($data['priceMembers']);
        $this->assertNull($data['priceGuests']);
        $this->assertNull($data['registrationClose']);
    }

    public function testPricesAreStoredAsFreeText(): void
    {
        $this->admin();

        $data = $this->json('POST', '/api/events', $this->eventBody(['priceMembers' => '<b>ab CHF 40.-</b>']))->json();

        $this->assertSame('<b>ab CHF 40.-</b>', $data['priceMembers']);
    }

    public function testExportUnknownEventIs404AndRequiresAdmin(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        $this->assertSame(401, $this->json('GET', "/api/events/{$eventId}/export")->statusCode);

        DB::$users->create('jane', 'Pass123!');
        $this->apiLoginAs('jane', 'Pass123!');
        $this->assertSame(403, $this->json('GET', "/api/events/{$eventId}/export")->statusCode);

        $this->json('POST', '/api/auth/logout');
        $this->admin();
        $this->assertSame(404, $this->json('GET', '/api/events/99999/export')->statusCode);
    }

    public function testSocialEventMenusAndTablesAreOptional(): void
    {
        $this->admin();

        $response = $this->json('POST', '/api/social-events', $this->socialBody(['menus' => '', 'tables' => '']));

        $this->assertSame(200, $response->statusCode);
        $this->assertSame([], $response->json()['menuOptions']);
        $this->assertSame([], $response->json()['tableOptions']);
    }

    public function testSocialTablesCanBeExpandedWhileRegistrationsExist(): void
    {
        $id = DB::$socialEvents->add('Dinner', '2099-06-01', null, null, null, 'Fisch', '2');
        DB::$socialEvents->registerGuest($id, 'A', 'One', 'a@example.com', null, DB::$socialEvents->tables($id)[0]->id);
        $this->admin();

        $response = $this->json('PUT', "/api/social-events/{$id}", $this->socialBody(['menus' => 'Fisch', 'tables' => '2,8']));

        $this->assertSame(200, $response->statusCode);
        $this->assertSame([2, 8], array_column($response->json()['tableOptions'], 'capacity'));
        $this->assertCount(1, DB::$socialEvents->registrations($id));
    }

    public function testSocialEventWithoutMenusOrTablesAcceptsRegistration(): void
    {
        $userId = DB::$users->create('jane', 'Pass123!');
        $id = DB::$socialEvents->add('Dinner', '2099-06-01', null, null, null, '', '');
        $this->apiLoginAs('jane', 'Pass123!');

        $response = $this->json('POST', "/api/social-events/{$id}/registrations", ['menuId' => null, 'tableId' => null]);

        $this->assertSame(200, $response->statusCode);
        $this->assertNotNull(DB::$socialEvents->getUserRegistration($id, $userId));
    }

    public function testRegisterWithoutTableLeavesTableEmpty(): void
    {
        $userId = DB::$users->create('jane', 'Pass123!');
        $id = DB::$socialEvents->add('Dinner', '2099-06-01', null, null, null, 'Fisch', '4');
        $this->apiLoginAs('jane', 'Pass123!');

        $this->json('POST', "/api/social-events/{$id}/registrations", ['menuId' => DB::$socialEvents->menus($id)[0]->id, 'tableId' => null]);

        $reg = DB::$socialEvents->getUserRegistration($id, $userId);
        $this->assertNotNull($reg);
        $this->assertNull($reg->tableId);
    }

    public function testUpdateUserClearsOptionalFields(): void
    {
        $id = DB::$users->create('jane', 'Pass123!', true, 'RF1', 'M1', 'Jane', 'Doe');
        $this->admin();

        $data = $this->json('PUT', "/api/users/{$id}", [
            'male' => true, 'username' => 'jane', 'password' => '',
            'firstName' => null, 'lastName' => null, 'rfeg' => null, 'memberNumber' => null,
        ])->json();

        $this->assertNull($data['rfeg']);
        $this->assertNull($data['memberNumber']);
        $this->assertNull($data['firstName']);
    }

    public function testDeletingEventRemovesItsGuests(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 4);
        DB::$guests->add($eventId, true, 'Hans', 'Müller', 'hans@example.com', 18.0, null);
        $this->admin();

        $this->assertSame(204, $this->json('DELETE', "/api/events/{$eventId}")->statusCode);

        $this->assertCount(0, DB::$guests->allForEvent($eventId));
    }
}
