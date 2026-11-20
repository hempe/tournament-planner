<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class GuestApiTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    private function guestBody(array $override = []): array
    {
        return array_merge([
            'male' => true,
            'firstName' => 'Gus',
            'lastName' => 'Guest',
            'email' => 'gus@example.com',
            'handicap' => '12.5',
            'comment' => 'Hi',
        ], $override);
    }

    private function socialGuestBody(array $override = []): array
    {
        return array_merge([
            'firstName' => 'Gus',
            'lastName' => 'Guest',
            'email' => 'gus@example.com',
            'menuId' => null,
            'tableId' => null,
        ], $override);
    }

    private function csrf(): void
    {
        $this->json('GET', '/api/auth/me');
    }

    private function dinner(string $menus = 'Fisch,Fleisch', string $tables = '4,6'): int
    {
        return DB::$socialEvents->add('Dinner', '2099-06-10', null, 'Nice', null, $menus, $tables);
    }

    public function testEventGuestInfoIsPublicAndMinimal(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 2, false, true, 'Nice day', 'CHF 50', 'CHF 80');

        $response = $this->json('GET', "/api/events/{$eventId}/guest-info");

        $this->assertSame(200, $response->statusCode);
        $data = $response->json();
        $keys = array_keys($data);
        sort($keys);
        $this->assertSame(
            ['date', 'description', 'id', 'isLocked', 'name', 'priceGuests', 'priceMembers', 'registrationClose'],
            $keys
        );
        $this->assertSame('Cup', $data['name']);
        $this->assertFalse($data['isLocked']);
    }

    public function testUnknownEventIs404(): void
    {
        $this->csrf();

        $info = $this->json('GET', '/api/events/999999/guest-info');
        $this->assertSame(404, $info->statusCode);
        $this->assertSame(['error' => 'events.not_found'], $info->json());

        $post = $this->json('POST', '/api/events/999999/guests', $this->guestBody());
        $this->assertSame(404, $post->statusCode);
        $this->assertSame(['error' => 'events.not_found'], $post->json());
    }

    public function testAnonymousGuestRegistrationStoresGuest(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        $this->csrf();

        $response = $this->json('POST', "/api/events/{$eventId}/guests", $this->guestBody());

        $this->assertSame(204, $response->statusCode);
        $guests = DB::$guests->allForEvent($eventId);
        $this->assertCount(1, $guests);
        $this->assertSame('Gus', $guests[0]->firstName);
        $this->assertSame(12.5, (float) $guests[0]->handicap);
    }

    public function testAnonymousMissingEmailAndHandicapIs422(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        $this->csrf();

        $response = $this->json('POST', "/api/events/{$eventId}/guests", $this->guestBody(['email' => '', 'handicap' => '']));

        $this->assertSame(422, $response->statusCode);
        $body = $response->json();
        $this->assertSame('validation.failed', $body['error']);
        $this->assertSame('validation.required', $body['fields']['email']['key']);
        $this->assertSame('validation.required', $body['fields']['handicap']['key']);
        $this->assertSame([], DB::$guests->allForEvent($eventId));
    }

    public function testAdminMayOmitEmailAndHandicap(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        $this->apiLoginAs('admin', 'Admin123!');

        $response = $this->json('POST', "/api/events/{$eventId}/guests", $this->guestBody(['email' => '', 'handicap' => '']));

        $this->assertSame(204, $response->statusCode);
        $guests = DB::$guests->allForEvent($eventId);
        $this->assertCount(1, $guests);
        $this->assertNull($guests[0]->handicap);
    }

    public function testInvalidEmailIs422(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        $this->csrf();

        $response = $this->json('POST', "/api/events/{$eventId}/guests", $this->guestBody(['email' => 'nope']));

        $this->assertSame(422, $response->statusCode);
        $this->assertSame('validation.email', $response->json()['fields']['email']['key']);
    }

    public function testLockedEventRejectsAnonymousGuest(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        DB::$events->lock($eventId);
        $this->csrf();

        $response = $this->json('POST', "/api/events/{$eventId}/guests", $this->guestBody());

        $this->assertSame(409, $response->statusCode);
        $this->assertSame(['error' => 'events.locked_message'], $response->json());
        $this->assertSame([], DB::$guests->allForEvent($eventId));
    }

    public function testLockedEventAllowsAdminGuest(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);
        DB::$events->lock($eventId);
        $this->apiLoginAs('admin', 'Admin123!');

        $response = $this->json('POST', "/api/events/{$eventId}/guests", $this->guestBody());

        $this->assertSame(204, $response->statusCode);
        $this->assertCount(1, DB::$guests->allForEvent($eventId));
    }

    public function testGuestRegistrationWithoutCsrfIs403(): void
    {
        $eventId = DB::$events->add('Cup', '2099-06-01', 2);

        $response = $this->json('POST', "/api/events/{$eventId}/guests", $this->guestBody(), false);

        $this->assertSame(403, $response->statusCode);
        $this->assertSame([], DB::$guests->allForEvent($eventId));
    }

    public function testSocialGuestInfoReturnsMenusAndTables(): void
    {
        $id = $this->dinner();

        $response = $this->json('GET', "/api/social-events/{$id}/guest-info");

        $this->assertSame(200, $response->statusCode);
        $data = $response->json();
        $this->assertSame('Dinner', $data['event']['name']);
        $this->assertSame(['Fisch', 'Fleisch'], array_column($data['menus'], 'name'));
        $this->assertSame([4, 6], array_column($data['tables'], 'capacity'));
        $this->assertArrayNotHasKey('participants', $data);
    }

    public function testSocialUnknownIs404(): void
    {
        $this->csrf();

        $info = $this->json('GET', '/api/social-events/999999/guest-info');
        $this->assertSame(404, $info->statusCode);
        $this->assertSame(['error' => 'social_events.not_found'], $info->json());

        $post = $this->json('POST', '/api/social-events/999999/guests', $this->socialGuestBody());
        $this->assertSame(404, $post->statusCode);
    }

    public function testSocialGuestWithMenuIsRegistered(): void
    {
        $id = $this->dinner();
        $menuId = DB::$socialEvents->menus($id)[0]->id;
        $tableId = DB::$socialEvents->tables($id)[0]->id;
        $this->csrf();

        $response = $this->json('POST', "/api/social-events/{$id}/guests", $this->socialGuestBody(['menuId' => $menuId, 'tableId' => $tableId]));

        $this->assertSame(204, $response->statusCode);
        $registrations = DB::$socialEvents->registrations($id);
        $this->assertCount(1, $registrations);
        $this->assertSame('Fisch', $registrations[0]->menuName);
        $this->assertSame(1, $registrations[0]->tableNumber);
    }

    public function testSocialGuestWithoutMenuWhenMenusExistIs422(): void
    {
        $id = $this->dinner();
        $this->csrf();

        $response = $this->json('POST', "/api/social-events/{$id}/guests", $this->socialGuestBody());

        $this->assertSame(422, $response->statusCode);
        $this->assertArrayHasKey('menuId', $response->json()['fields']);
        $this->assertSame([], DB::$socialEvents->registrations($id));
    }

    public function testSocialGuestForeignMenuIs422(): void
    {
        $id = $this->dinner();
        $other = $this->dinner();
        $this->csrf();

        $response = $this->json('POST', "/api/social-events/{$id}/guests", $this->socialGuestBody([
            'menuId' => DB::$socialEvents->menus($other)[0]->id,
        ]));

        $this->assertSame(422, $response->statusCode);
        $this->assertSame('social_events.invalid_menu', $response->json()['fields']['menuId']['key']);
    }

    public function testSocialGuestForeignTableIs422(): void
    {
        $id = $this->dinner();
        $other = $this->dinner();
        $this->csrf();

        $response = $this->json('POST', "/api/social-events/{$id}/guests", $this->socialGuestBody([
            'menuId' => DB::$socialEvents->menus($id)[0]->id,
            'tableId' => DB::$socialEvents->tables($other)[0]->id,
        ]));

        $this->assertSame(422, $response->statusCode);
        $this->assertSame('social_events.invalid_table', $response->json()['fields']['tableId']['key']);
    }

    public function testSocialGuestOnFullEventAndFullTableIs409(): void
    {
        $full = $this->dinner('', '1');
        DB::$socialEvents->registerGuest($full, 'A', 'B', 'a@b.ch', null, null);
        $this->csrf();

        $response = $this->json('POST', "/api/social-events/{$full}/guests", $this->socialGuestBody());
        $this->assertSame(409, $response->statusCode);
        $this->assertSame(['error' => 'social_events.event_full'], $response->json());

        $id = $this->dinner('', '1,5');
        $tableId = DB::$socialEvents->tables($id)[0]->id;
        DB::$socialEvents->registerGuest($id, 'A', 'B', 'a@b.ch', null, $tableId);

        $response = $this->json('POST', "/api/social-events/{$id}/guests", $this->socialGuestBody(['tableId' => $tableId]));
        $this->assertSame(409, $response->statusCode);
        $this->assertSame(['error' => 'social_events.table_full'], $response->json());
    }

    public function testSocialGuestMissingEmailIs422ForAnonymousButNotAdmin(): void
    {
        $id = $this->dinner('', '4');
        $this->csrf();

        $anon = $this->json('POST', "/api/social-events/{$id}/guests", $this->socialGuestBody(['email' => '']));
        $this->assertSame(422, $anon->statusCode);
        $this->assertSame('validation.required', $anon->json()['fields']['email']['key']);

        $this->apiLoginAs('admin', 'Admin123!');
        $admin = $this->json('POST', "/api/social-events/{$id}/guests", $this->socialGuestBody(['email' => '']));
        $this->assertSame(204, $admin->statusCode);
    }

    public function testSocialGuestOnLockedEventIs409ForAnonymousAnd204ForAdmin(): void
    {
        $id = $this->dinner('', '4');
        DB::$socialEvents->lock($id);
        $this->csrf();

        $anon = $this->json('POST', "/api/social-events/{$id}/guests", $this->socialGuestBody());
        $this->assertSame(409, $anon->statusCode);
        $this->assertSame(['error' => 'social_events.locked_message'], $anon->json());
        $this->assertSame([], DB::$socialEvents->registrations($id));

        $this->apiLoginAs('admin', 'Admin123!');
        $admin = $this->json('POST', "/api/social-events/{$id}/guests", $this->socialGuestBody());
        $this->assertSame(204, $admin->statusCode);
    }
}
