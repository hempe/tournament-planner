<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

/**
 * Bad or unexpected input must never produce a 500 and must not create data.
 */
class MonkeyApiTest extends IntegrationTestCase
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
            'name' => 'Test', 'date' => '2099-01-01', 'capacity' => 10, 'mixed' => false,
            'description' => null, 'priceMembers' => null, 'priceGuests' => null, 'registrationClose' => null,
        ];
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function badEventBodies(): array
    {
        return [
            'string capacity' => [['capacity' => 'abc']],
            'zero capacity' => [['capacity' => 0]],
            'negative capacity' => [['capacity' => -99]],
            'invalid date' => [['date' => 'not-a-date']],
            'overlong name' => [['name' => str_repeat('A', 256)]],
        ];
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('badEventBodies')]
    public function testCreateEventWithBadInputIsRejected(array $override): void
    {
        $this->admin();

        $response = $this->json('POST', '/api/events', $this->eventBody($override));

        $this->assertContains($response->statusCode, [400, 422]);
        $this->assertCount(0, DB::$events->all());
    }

    public function testCreateEventWithEmptyBodyIsRejected(): void
    {
        $this->admin();

        $this->assertContains($this->json('POST', '/api/events', [])->statusCode, [400, 422]);
    }

    public function testSqlInjectionInNameIsStoredLiterally(): void
    {
        $this->admin();
        $name = "'; DROP TABLE events; --";

        $response = $this->json('POST', '/api/events', $this->eventBody(['name' => $name]));

        $this->assertSame(200, $response->statusCode);
        $this->assertSame($name, $response->json()['event']['name']);
    }

    public function testUnknownAndNonNumericIdsAre404(): void
    {
        $this->admin();

        $this->assertSame(404, $this->json('GET', '/api/events/abc')->statusCode);
        $this->assertSame(404, $this->json('PUT', '/api/events/99999', $this->eventBody())->statusCode);
        $this->assertSame(404, $this->json('POST', '/api/events/99999/registrations', ['comment' => ''])->statusCode);
        $this->assertSame(404, $this->json('POST', '/api/social-events/99999/registrations', ['menuId' => 1, 'tableId' => null])->statusCode);
    }

    public function testRegisteringTwiceKeepsOneRegistration(): void
    {
        $eventId = DB::$events->add('Test', '2099-01-01', 10);
        DB::$users->create('user1', 'Pass123!');
        $this->apiLoginAs('user1', 'Pass123!');

        $this->json('POST', "/api/events/{$eventId}/registrations", ['comment' => '']);
        $response = $this->json('POST', "/api/events/{$eventId}/registrations", ['comment' => '']);

        $this->assertSame(200, $response->statusCode);
        $this->assertCount(1, DB::$events->registrations($eventId));
    }

    public function testUnregisterWhenNotRegisteredIsHandled(): void
    {
        $eventId = DB::$events->add('Test', '2099-01-01', 10);
        DB::$users->create('user1', 'Pass123!');
        $this->apiLoginAs('user1', 'Pass123!');

        $this->assertLessThan(500, $this->json('DELETE', "/api/events/{$eventId}/registrations/me")->statusCode);
    }

    public function testBulkPreviewWithReversedRangeOrBadWeekdayIsHandled(): void
    {
        $this->admin();
        $base = ['name' => 'Test', 'capacity' => 10, 'mixed' => false];

        $reversed = $this->json('POST', '/api/events/bulk/preview', $base + ['startDate' => '2099-12-31', 'endDate' => '2099-01-01', 'dayOfWeek' => 1]);
        $this->assertLessThan(500, $reversed->statusCode);

        $badDay = $this->json('POST', '/api/events/bulk/preview', $base + ['startDate' => '2099-01-01', 'endDate' => '2099-03-01', 'dayOfWeek' => 99]);
        $this->assertSame(422, $badDay->statusCode);
    }

    public function testSocialEventWithBadInputIsRejected(): void
    {
        $this->admin();
        $base = ['tournamentId' => null, 'description' => null, 'registrationClose' => null, 'menus' => 'Meat', 'tables' => '10'];

        $this->assertSame(422, $this->json('POST', '/api/social-events', $base + ['name' => '', 'date' => '2099-01-01'])->statusCode);
        $this->assertSame(422, $this->json('POST', '/api/social-events', $base + ['name' => 'Dinner', 'date' => 'not-a-date'])->statusCode);
    }

    public function testSocialRegistrationWithBadMenuOrTableIsRejected(): void
    {
        $eventId = DB::$socialEvents->add('Dinner', '2099-01-01', null, null, null, 'Meat,Fish', '10');
        $menuId = DB::$socialEvents->menus($eventId)[0]->id;
        DB::$users->create('user1', 'Pass123!');
        $this->apiLoginAs('user1', 'Pass123!');

        foreach ([['menuId' => 999999, 'tableId' => null], ['menuId' => 0, 'tableId' => null], ['menuId' => $menuId, 'tableId' => 999999]] as $body) {
            $this->assertSame(422, $this->json('POST', "/api/social-events/{$eventId}/registrations", $body)->statusCode);
        }
        $this->assertCount(0, DB::$socialEvents->registrations($eventId));
    }

    public function testSocialGuestWithBadInputIsRejected(): void
    {
        $eventId = DB::$socialEvents->add('Dinner', '2099-01-01', null, null, null, 'Meat', '10');
        $menuId = DB::$socialEvents->menus($eventId)[0]->id;

        $this->apiLoginAs('admin', 'Admin123!');
        $this->json('POST', '/api/auth/logout');
        $this->json('GET', '/api/auth/me');

        $missing = $this->json('POST', "/api/social-events/{$eventId}/guests", []);
        $this->assertSame(422, $missing->statusCode);

        $badEmail = $this->json('POST', "/api/social-events/{$eventId}/guests", [
            'firstName' => 'John', 'lastName' => 'Doe', 'email' => 'not-an-email', 'menuId' => $menuId, 'tableId' => null,
        ]);
        $this->assertSame(422, $badEmail->statusCode);

        $long = $this->json('POST', "/api/social-events/{$eventId}/guests", [
            'firstName' => str_repeat('A', 300), 'lastName' => 'Doe', 'email' => 'test@example.com', 'menuId' => $menuId, 'tableId' => null,
        ]);
        $this->assertLessThan(500, $long->statusCode);
        $this->assertCount(0, DB::$socialEvents->registrations($eventId));
    }

    public function testCreateUserWithEmptyUsernameIsRejected(): void
    {
        $this->admin();

        $response = $this->json('POST', '/api/users', [
            'male' => true, 'username' => '', 'password' => 'Pass123!', 'firstName' => null, 'lastName' => null, 'rfeg' => null, 'memberNumber' => null,
        ]);

        $this->assertSame(422, $response->statusCode);
    }

    public function testNamesAreReturnedVerbatimAsJson(): void
    {
        $this->admin();
        $xss = '<script>alert(1)</script>';
        $id = $this->json('POST', '/api/events', $this->eventBody(['name' => $xss]))->json()['event']['id'];

        $this->assertSame($xss, $this->json('GET', "/api/events/{$id}")->json()['event']['name']);
    }
}
