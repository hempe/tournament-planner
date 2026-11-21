<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class EventAdminApiTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    private function member(string $name = 'jane', string $first = 'Jane', string $last = 'Doe'): int
    {
        return DB::$users->create($name, 'Pass123!', firstName: $first, lastName: $last);
    }

    private function admin(): void
    {
        $this->apiLoginAs('admin', 'Admin123!');
    }

    private function eventBody(array $override = []): array
    {
        return $override + [
            'name' => 'Spring Cup',
            'date' => '2099-06-01',
            'capacity' => 10,
            'mixed' => true,
            'description' => 'Nice',
            'priceMembers' => 'CHF 50',
            'priceGuests' => 'CHF 80',
            'registrationClose' => '2099-05-30T12:00',
        ];
    }

    public function testAccessMatrix(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);

        $this->assertSame(401, $this->json('GET', "/api/events/{$eventId}/admin")->statusCode);
        $this->assertSame(401, $this->json('PUT', "/api/events/{$eventId}/lock")->statusCode);

        $this->member();
        $this->apiLoginAs('jane', 'Pass123!');
        $this->assertSame(403, $this->json('GET', "/api/events/{$eventId}/admin")->statusCode);
        $this->assertSame(403, $this->json('GET', '/api/events')->statusCode);
        $this->assertSame(403, $this->json('PUT', "/api/events/{$eventId}/lock")->statusCode);
        $this->assertSame(403, $this->json('POST', '/api/events/bulk/preview', [])->statusCode);
    }

    public function testCreateAppearsInListAndAdminDetail(): void
    {
        $this->admin();

        $created = $this->json('POST', '/api/events', $this->eventBody());

        $this->assertSame(200, $created->statusCode);
        $data = $created->json();
        $this->assertSame('Spring Cup', $data['event']['name']);
        $this->assertSame('2099-06-01', $data['event']['date']);
        $this->assertSame(10, $data['event']['capacity']);
        $this->assertTrue($data['mixed']);
        $this->assertSame('CHF 50', $data['priceMembers']);
        $this->assertSame('2099-05-30T12:00:00', $data['registrationClose']);
        $this->assertSame([], $data['registrations']);
        $this->assertNull($data['socialEvent']);

        $id = $data['event']['id'];
        $list = $this->json('GET', '/api/events')->json();
        $this->assertContains($id, array_column($list, 'id'));

        $detail = $this->json('GET', "/api/events/{$id}/admin");
        $this->assertSame(200, $detail->statusCode);
        $this->assertSame('Spring Cup', $detail->json()['event']['name']);
        $this->assertContains('admin', array_map(fn($u) => $u['displayName'], $detail->json()['availableUsers']));
    }

    public function testListIsSortedByDate(): void
    {
        DB::$events->add('Late', '2099-08-01', 2);
        DB::$events->add('Early', '2099-06-01', 2);
        DB::$events->add('Past', '2001-01-01', 2);
        DB::$events->add('Also early', '2099-06-01', 2);
        $this->admin();

        $list = $this->json('GET', '/api/events')->json();

        $this->assertSame(['Past', 'Also early', 'Early', 'Late'], array_column($list, 'name'));
    }

    public function testCreateWithMixedFalseAndNoOptionalFields(): void
    {
        $this->admin();

        $data = $this->json('POST', '/api/events', ['name' => 'X', 'date' => '2099-06-02', 'capacity' => 4, 'mixed' => false])->json();

        $this->assertFalse($data['mixed']);
        $this->assertNull($data['description']);
        $this->assertNull($data['registrationClose']);
    }

    public function testCreateValidation(): void
    {
        $this->admin();

        $response = $this->json('POST', '/api/events', ['name' => '', 'date' => 'nope', 'capacity' => 0, 'registrationClose' => 'soon']);

        $this->assertSame(422, $response->statusCode);
        $body = $response->json();
        $this->assertSame('validation.failed', $body['error']);
        $this->assertSame('validation.required', $body['fields']['name']['key']);
        $this->assertArrayHasKey('date', $body['fields']);
        $this->assertArrayHasKey('capacity', $body['fields']);
        $this->assertArrayHasKey('registrationClose', $body['fields']);
    }

    public function testUpdateChangesFields(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        $this->admin();

        $response = $this->json('PUT', "/api/events/{$eventId}", [
            'name' => 'Renamed',
            'capacity' => 8,
            'mixed' => false,
            'description' => 'New',
            'priceMembers' => 'CHF 10',
            'priceGuests' => 'CHF 20',
            'registrationClose' => '2099-05-31T09:15',
        ]);

        $this->assertSame(200, $response->statusCode);
        $data = $response->json();
        $this->assertSame('Renamed', $data['event']['name']);
        $this->assertSame(8, $data['event']['capacity']);
        $this->assertSame('2099-06-01', $data['event']['date']);
        $this->assertFalse($data['mixed']);
        $this->assertSame('New', $data['description']);
        $this->assertSame('CHF 10', $data['priceMembers']);
        $this->assertSame('CHF 20', $data['priceGuests']);
        $this->assertSame('2099-05-31T09:15:00', $data['registrationClose']);
    }

    public function testUpdateValidationAndNotFound(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        $this->admin();

        $this->assertSame(422, $this->json('PUT', "/api/events/{$eventId}", ['name' => 'A', 'capacity' => 0])->statusCode);

        $missing = $this->json('PUT', '/api/events/99999', ['name' => 'A', 'capacity' => 1]);
        $this->assertSame(404, $missing->statusCode);
        $this->assertSame('events.not_found', $missing->json()['error']);
    }

    public function testDeleteRemovesEvent(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        $this->admin();

        $this->assertSame(204, $this->json('DELETE', "/api/events/{$eventId}")->statusCode);
        $this->assertSame(404, $this->json('GET', "/api/events/{$eventId}/admin")->statusCode);
        $this->assertSame(404, $this->json('DELETE', "/api/events/{$eventId}")->statusCode);
    }

    public function testLockAndUnlockToggle(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        $this->admin();

        $locked = $this->json('PUT', "/api/events/{$eventId}/lock");
        $this->assertSame(200, $locked->statusCode);
        $this->assertTrue($locked->json()['event']['isLocked']);

        $unlocked = $this->json('DELETE', "/api/events/{$eventId}/lock");
        $this->assertFalse($unlocked->json()['event']['isLocked']);

        $this->assertSame(404, $this->json('PUT', '/api/events/99999/lock')->statusCode);
    }

    public function testExportReturnsCsvWithOneLinePerRegistrationAndGuest(): void
    {
        $userId = $this->member();
        $eventId = DB::$events->add('Cup', '2099-06-01', 5);
        DB::$events->register($eventId, $userId, '');
        DB::$guests->add($eventId, true, 'Gus', 'Guest', null, null, null);
        $this->admin();

        $response = $this->json('GET', "/api/events/{$eventId}/export");

        $this->assertSame(200, $response->statusCode);
        $this->assertStringStartsWith('text/csv', $response->headers['Content-Type']);
        $this->assertStringContainsString('2099-06-01_Cup.csv', $response->headers['Content-Disposition']);
        $lines = explode("\r\n", $response->body);
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('"Jane Doe"', $lines[1]);
        $this->assertStringContainsString('"Gus Guest"', $lines[2]);

        $this->assertSame(404, $this->json('GET', '/api/events/99999/export')->statusCode);
    }

    public function testAdminRegistersAnotherUser(): void
    {
        $userId = $this->member();
        $eventId = DB::$events->add('Cup', '2099-06-01', 5);
        $this->admin();

        $before = $this->json('GET', "/api/events/{$eventId}/admin")->json();
        $this->assertContains($userId, array_column($before['availableUsers'], 'id'));

        $response = $this->json('POST', "/api/events/{$eventId}/registrations/{$userId}", ['comment' => 'Mit Trolley']);

        $this->assertSame(200, $response->statusCode);
        $data = $response->json();
        $this->assertSame($userId, $data['registrations'][0]['userId']);
        $this->assertSame('Jane Doe', $data['registrations'][0]['name']);
        $this->assertSame('Mit Trolley', $data['registrations'][0]['comment']);
        $this->assertSame('registered', $data['registrations'][0]['state']);
        $this->assertNotContains($userId, array_column($data['availableUsers'], 'id'));
    }

    public function testRegisterUnknownUserOrEventIs404(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 5);
        $this->admin();

        $user = $this->json('POST', "/api/events/{$eventId}/registrations/99999", ['comment' => '']);
        $this->assertSame(404, $user->statusCode);
        $this->assertSame('users.not_found', $user->json()['error']);

        $this->assertSame('events.not_found', $this->json('POST', '/api/events/99999/registrations/1', ['comment' => ''])->json()['error']);
    }

    public function testRegisterOnLockedEventIs409(): void
    {
        $userId = $this->member();
        $eventId = DB::$events->add('Cup', '2099-06-01', 5);
        DB::$events->lock($eventId);
        $this->admin();

        $response = $this->json('POST', "/api/events/{$eventId}/registrations/{$userId}", ['comment' => '']);

        $this->assertSame(409, $response->statusCode);
        $this->assertSame('events.locked_message', $response->json()['error']);
    }

    public function testAdminUnregistersUserAndWaitlistIsPromoted(): void
    {
        $first = $this->member('jane');
        $second = $this->member('bob', 'Bob', 'Builder');
        $eventId = DB::$events->add('Cup', '2099-06-01', 1);
        DB::$events->register($eventId, $first, '');
        DB::$events->register($eventId, $second, '');
        $this->admin();

        $before = $this->json('GET', "/api/events/{$eventId}/admin")->json();
        $this->assertSame(1, $before['event']['waitlist']);

        $response = $this->json('DELETE', "/api/events/{$eventId}/registrations/{$first}");

        $this->assertSame(200, $response->statusCode);
        $data = $response->json();
        $this->assertCount(1, $data['registrations']);
        $this->assertSame($second, $data['registrations'][0]['userId']);
        $this->assertSame('registered', $data['registrations'][0]['state']);
        $this->assertSame(0, $data['event']['waitlist']);
    }

    public function testUnregisterOnLockedEventIs409(): void
    {
        $userId = $this->member();
        $eventId = DB::$events->add('Cup', '2099-06-01', 5);
        DB::$events->register($eventId, $userId, '');
        DB::$events->lock($eventId);
        $this->admin();

        $this->assertSame(409, $this->json('DELETE', "/api/events/{$eventId}/registrations/{$userId}")->statusCode);
    }

    public function testAdminUpdatesComment(): void
    {
        $userId = $this->member();
        $eventId = DB::$events->add('Cup', '2099-06-01', 5);
        DB::$events->register($eventId, $userId, 'old');
        $this->admin();

        $response = $this->json('PATCH', "/api/events/{$eventId}/registrations/{$userId}", ['comment' => 'new']);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('new', $response->json()['registrations'][0]['comment']);
    }

    public function testCommentOfUnregisteredUserIs404AndLockedIs409(): void
    {
        $userId = $this->member();
        $eventId = DB::$events->add('Cup', '2099-06-01', 5);
        $this->admin();

        $missing = $this->json('PATCH', "/api/events/{$eventId}/registrations/{$userId}", ['comment' => 'x']);
        $this->assertSame(404, $missing->statusCode);
        $this->assertSame('auth.not_registered', $missing->json()['error']);

        DB::$events->register($eventId, $userId, '');
        DB::$events->lock($eventId);
        $this->assertSame(409, $this->json('PATCH', "/api/events/{$eventId}/registrations/{$userId}", ['comment' => 'x'])->statusCode);
    }

    public function testMemberRegistrationsMeRouteStillWinsOverUserIdParameter(): void
    {
        $this->member();
        $eventId = DB::$events->add('Cup', '2099-06-01', 5);
        $this->apiLoginAs('jane', 'Pass123!');
        $this->json('POST', "/api/events/{$eventId}/registrations", ['comment' => 'a']);

        $response = $this->json('PATCH', "/api/events/{$eventId}/registrations/me", ['comment' => 'b']);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('b', $response->json()['myRegistration']['comment']);
    }

    public function testGuestGetUpdateDelete(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 5);
        $guestId = DB::$guests->add($eventId, true, 'Gus', 'Guest', null, null, null);
        $this->admin();

        $get = $this->json('GET', "/api/events/{$eventId}/guests/{$guestId}");
        $this->assertSame(200, $get->statusCode);
        $this->assertSame('Gus', $get->json()['firstName']);
        $this->assertNull($get->json()['email']);

        $update = $this->json('PUT', "/api/events/{$eventId}/guests/{$guestId}", [
            'male' => false,
            'firstName' => 'Gina',
            'lastName' => 'Guest',
            'email' => 'gina@example.com',
            'handicap' => '12.4',
            'comment' => 'hi',
        ]);
        $this->assertSame(200, $update->statusCode);
        $data = $update->json();
        $this->assertFalse($data['male']);
        $this->assertSame('Gina', $data['firstName']);
        $this->assertSame('gina@example.com', $data['email']);
        $this->assertSame(12.4, $data['handicap']);
        $this->assertSame('hi', $data['comment']);

        $this->assertSame(204, $this->json('DELETE', "/api/events/{$eventId}/guests/{$guestId}")->statusCode);
        $this->assertSame(404, $this->json('GET', "/api/events/{$eventId}/guests/{$guestId}")->statusCode);
    }

    public function testGuestUpdateWithoutEmailAndHandicapIsAllowedButBadInputIs422(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 5);
        $guestId = DB::$guests->add($eventId, true, 'Gus', 'Guest', 'g@example.com', 3.0, null);
        $this->admin();

        $ok = $this->json('PUT', "/api/events/{$eventId}/guests/{$guestId}", ['male' => true, 'firstName' => 'Gus', 'lastName' => 'Guest']);
        $this->assertSame(200, $ok->statusCode);
        $this->assertNull($ok->json()['email']);
        $this->assertNull($ok->json()['handicap']);

        $bad = $this->json('PUT', "/api/events/{$eventId}/guests/{$guestId}", ['firstName' => '', 'lastName' => 'G', 'email' => 'nope']);
        $this->assertSame(422, $bad->statusCode);
        $fields = $bad->json()['fields'];
        $this->assertArrayHasKey('firstName', $fields);
        $this->assertArrayHasKey('email', $fields);
        $this->assertArrayHasKey('male', $fields);
    }

    public function testGuestOfAnotherEventIs404(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 5);
        $otherId = DB::$events->add('Other', '2099-06-02', 5);
        $guestId = DB::$guests->add($otherId, true, 'Gus', 'Guest', null, null, null);
        $this->admin();

        $get = $this->json('GET', "/api/events/{$eventId}/guests/{$guestId}");
        $this->assertSame(404, $get->statusCode);
        $this->assertSame('guests.not_found', $get->json()['error']);
        $this->assertSame(404, $this->json('PUT', "/api/events/{$eventId}/guests/{$guestId}", ['male' => true, 'firstName' => 'A', 'lastName' => 'B'])->statusCode);
        $this->assertSame(404, $this->json('DELETE', "/api/events/{$eventId}/guests/{$guestId}")->statusCode);
        $this->assertCount(1, DB::$guests->allForEvent($otherId));

        $this->assertSame('events.not_found', $this->json('GET', "/api/events/99999/guests/{$guestId}")->json()['error']);
    }

    public function testBulkPreviewComputesMondaysWithRegistrationClose(): void
    {
        $this->admin();

        $response = $this->json('POST', '/api/events/bulk/preview', [
            'startDate' => '2099-06-01',
            'endDate' => '2099-06-30',
            'dayOfWeek' => 1,
            'name' => 'Monday Cup',
            'capacity' => 12,
            'mixed' => true,
            'description' => null,
            'priceMembers' => 'CHF 40',
            'priceGuests' => null,
            'registrationCloseDays' => 2,
            'registrationCloseTime' => '18:00',
        ]);

        $this->assertSame(200, $response->statusCode);
        $events = $response->json()['events'];
        $this->assertCount(5, $events);
        $this->assertSame('2099-06-01', $events[0]['date']);
        $this->assertSame('2099-06-29', $events[4]['date']);
        $this->assertSame('2099-05-30T18:00:00', $events[0]['registrationClose']);
        $this->assertSame('Monday Cup', $events[0]['name']);
        $this->assertSame(12, $events[0]['capacity']);
        $this->assertSame('CHF 40', $events[0]['priceMembers']);
        $this->assertNull($events[0]['description']);
        $this->assertTrue($events[0]['mixed']);
    }

    public function testBulkPreviewWithoutCloseSettingsAndValidation(): void
    {
        $this->admin();

        $plain = $this->json('POST', '/api/events/bulk/preview', [
            'startDate' => '2099-06-01', 'endDate' => '2099-06-30', 'dayOfWeek' => 1, 'name' => 'C', 'capacity' => 2, 'mixed' => false,
        ])->json();
        $this->assertNull($plain['events'][0]['registrationClose']);
        $this->assertFalse($plain['events'][0]['mixed']);

        $bad = $this->json('POST', '/api/events/bulk/preview', [
            'startDate' => '', 'endDate' => '2099-06-30', 'dayOfWeek' => 9, 'name' => '', 'capacity' => 0,
            'registrationCloseDays' => 8, 'registrationCloseTime' => '25:99',
        ]);
        $this->assertSame(422, $bad->statusCode);
        $fields = $bad->json()['fields'];
        foreach (['startDate', 'dayOfWeek', 'name', 'capacity', 'registrationCloseDays', 'registrationCloseTime'] as $field) {
            $this->assertArrayHasKey($field, $fields);
        }
    }

    public function testBulkCreateCreatesLockedEventsFromPreview(): void
    {
        $this->admin();
        $preview = $this->json('POST', '/api/events/bulk/preview', [
            'startDate' => '2099-06-01', 'endDate' => '2099-06-30', 'dayOfWeek' => 1, 'name' => 'Monday Cup', 'capacity' => 12,
            'mixed' => true, 'priceMembers' => 'CHF 40', 'registrationCloseDays' => 2, 'registrationCloseTime' => '18:00',
        ])->json();

        $response = $this->json('POST', '/api/events/bulk', $preview);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['created' => 5], $response->json());
        $list = $this->json('GET', '/api/events')->json();
        $this->assertCount(5, $list);
        $this->assertSame('Monday Cup', $list[0]['name']);
        $this->assertTrue($list[0]['isLocked']);
        $first = $this->json('GET', "/api/events/{$list[0]['id']}/admin")->json();
        $this->assertSame('CHF 40', $first['priceMembers']);
        $this->assertSame('2099-05-30T18:00:00', $first['registrationClose']);
        $this->assertTrue($first['mixed']);
    }

    public function testBulkCreateValidatesEveryEvent(): void
    {
        $this->admin();
        $good = ['name' => 'A', 'date' => '2099-06-01', 'capacity' => 4, 'mixed' => true, 'description' => null, 'priceMembers' => null, 'priceGuests' => null, 'registrationClose' => null];

        $response = $this->json('POST', '/api/events/bulk', ['events' => [$good, ['name' => '', 'date' => 'not-a-date', 'capacity' => 0] + $good]]);

        $this->assertSame(422, $response->statusCode);
        $fields = $response->json()['fields'];
        $this->assertArrayHasKey('events.1.name', $fields);
        $this->assertArrayHasKey('events.1.date', $fields);
        $this->assertArrayHasKey('events.1.capacity', $fields);
        $this->assertArrayNotHasKey('events.0.name', $fields);
        $this->assertSame([], $this->json('GET', '/api/events')->json());

        $this->assertSame(422, $this->json('POST', '/api/events/bulk', ['events' => []])->statusCode);
    }
}
