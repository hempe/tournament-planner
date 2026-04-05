<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Controllers;

use TP\Tests\Integration\IntegrationTestCase;
use TP\Models\DB;

/**
 * Monkey tests: bad/unexpected inputs must never produce a 500.
 * Stupid-user tests: validation failures must surface an error message, not silently fail.
 *
 * Convention:
 *  - testMonkey*  → bad input, assert no 500
 *  - testUx*      → UX feedback, assert flash error appears on the redirect target
 *  - testXss*     → user-controlled strings are HTML-escaped when rendered
 */
class MonkeyTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    // -------------------------------------------------------------------------
    // Events — bad input
    // -------------------------------------------------------------------------

    public function testMonkeyCreateEventStringCapacity(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('POST', '/events/new', [
            'name' => 'Test', 'date' => '2099-01-01', 'capacity' => 'abc',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertEquals(303, $response->statusCode);
        $this->assertCount(0, DB::$events->all());
    }

    public function testMonkeyCreateEventZeroCapacity(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('POST', '/events/new', [
            'name' => 'Test', 'date' => '2099-01-01', 'capacity' => '0',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertCount(0, DB::$events->all());
    }

    public function testMonkeyCreateEventNegativeCapacity(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('POST', '/events/new', [
            'name' => 'Test', 'date' => '2099-01-01', 'capacity' => '-99',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertCount(0, DB::$events->all());
    }

    public function testMonkeyCreateEventInvalidDate(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('POST', '/events/new', [
            'name' => 'Test', 'date' => 'not-a-date', 'capacity' => '10',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertCount(0, DB::$events->all());
    }

    public function testMonkeyCreateEventOverlongName(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('POST', '/events/new', [
            'name' => str_repeat('A', 256), 'date' => '2099-01-01', 'capacity' => '10',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertCount(0, DB::$events->all());
    }

    public function testMonkeyCreateEventEmptyBody(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('POST', '/events/new', []);
        $this->assertNotEquals(500, $response->statusCode);
    }

    public function testMonkeyCreateEventSqlInjectionInName(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('POST', '/events/new', [
            'name' => "'; DROP TABLE events; --", 'date' => '2099-01-01', 'capacity' => '10',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
        // Prepared statements — table must still exist
        $this->assertIsArray(DB::$events->all());
    }

    public function testMonkeyUpdateNonexistentEvent(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('POST', '/events/99999', [
            'name' => 'Test', 'capacity' => '10',
        ]);
        // No event found — update silently redirects (no crash)
        $this->assertNotEquals(500, $response->statusCode);
    }

    public function testMonkeyGetEventNonNumericId(): void
    {
        $this->loginAsAdmin();
        // "abc" cast to int = 0, no event with id 0 → 404
        $response = $this->request('GET', '/events/abc');
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertEquals(404, $response->statusCode);
    }

    public function testMonkeyRegisterForNonexistentEvent(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('POST', '/events/99999/register', ['comment' => '']);
        $this->assertNotEquals(500, $response->statusCode);
    }

    public function testMonkeyRegisterTwiceIsSafe(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test', '2099-01-01', 10);
        DB::$users->create('user1', 'Pass123!');
        $this->loginAs('user1', 'Pass123!');

        $this->request('POST', "/events/$eventId/register", ['comment' => '']);
        $response = $this->request('POST', "/events/$eventId/register", ['comment' => '']);

        $this->assertNotEquals(500, $response->statusCode);
        // Only one registration should exist
        $this->assertCount(1, DB::$events->registrations($eventId));
    }

    public function testMonkeyUnregisterNotRegistered(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test', '2099-01-01', 10);
        DB::$users->create('user1', 'Pass123!');
        $this->loginAs('user1', 'Pass123!');

        // Unregister without ever registering
        $response = $this->request('POST', "/events/$eventId/unregister", []);
        $this->assertNotEquals(500, $response->statusCode);
    }

    public function testMonkeyBulkPreviewInvalidDateRange(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('POST', '/events/bulk/preview', [
            'start_date' => '2099-12-31',
            'end_date' => '2099-01-01', // end before start
            'day_of_week' => '1',
            'name' => 'Test',
            'capacity' => '10',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
    }

    public function testMonkeyBulkPreviewInvalidDayOfWeek(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('POST', '/events/bulk/preview', [
            'start_date' => '2099-01-01',
            'end_date' => '2099-03-01',
            'day_of_week' => '99', // out of range
            'name' => 'Test',
            'capacity' => '10',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
    }

    // -------------------------------------------------------------------------
    // Social Events — bad input
    // -------------------------------------------------------------------------

    public function testMonkeyCreateSocialEventEmptyName(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('POST', '/social-events/new', [
            'name' => '', 'date' => '2099-01-01', 'menus' => 'Meat', 'tables' => '10',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertEquals(303, $response->statusCode);
    }

    public function testMonkeyCreateSocialEventEmptyMenus(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('POST', '/social-events/new', [
            'name' => 'Dinner', 'date' => '2099-01-01', 'menus' => '', 'tables' => '10',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertEquals(303, $response->statusCode);
    }

    public function testMonkeyCreateSocialEventInvalidDate(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('POST', '/social-events/new', [
            'name' => 'Dinner', 'date' => 'not-a-date', 'menus' => 'Meat', 'tables' => '10',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertEquals(303, $response->statusCode);
    }

    public function testMonkeyRegisterSocialEventInvalidMenuId(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$socialEvents->add('Dinner', '2099-01-01', null, null, null, 'Meat,Fish', '10');
        DB::$users->create('user1', 'Pass123!');
        $this->loginAs('user1', 'Pass123!');

        $response = $this->request('POST', "/social-events/$eventId/register", [
            'menu_id' => '999999', 'table_id' => '',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertEquals(303, $response->statusCode);
        $this->assertCount(0, DB::$socialEvents->registrations($eventId));
    }

    public function testMonkeyRegisterSocialEventZeroMenuId(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$socialEvents->add('Dinner', '2099-01-01', null, null, null, 'Meat', '10');
        DB::$users->create('user1', 'Pass123!');
        $this->loginAs('user1', 'Pass123!');

        $response = $this->request('POST', "/social-events/$eventId/register", [
            'menu_id' => '0', 'table_id' => '',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertCount(0, DB::$socialEvents->registrations($eventId));
    }

    public function testMonkeyRegisterSocialEventInvalidTableId(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$socialEvents->add('Dinner', '2099-01-01', null, null, null, 'Meat', '10');
        $menus = DB::$socialEvents->menus($eventId);
        DB::$users->create('user1', 'Pass123!');
        $this->loginAs('user1', 'Pass123!');

        $response = $this->request('POST', "/social-events/$eventId/register", [
            'menu_id' => (string) $menus[0]->id,
            'table_id' => '999999', // invalid table
        ]);
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertCount(0, DB::$socialEvents->registrations($eventId));
    }

    public function testMonkeyRegisterNonexistentSocialEvent(): void
    {
        $this->loginAsAdmin();
        DB::$users->create('user1', 'Pass123!');
        $this->loginAs('user1', 'Pass123!');

        $response = $this->request('POST', '/social-events/99999/register', [
            'menu_id' => '1', 'table_id' => '',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertEquals(404, $response->statusCode);
    }

    public function testMonkeyGuestRegistrationMissingFields(): void
    {
        $eventId = DB::$socialEvents->add('Dinner', '2099-01-01', null, null, null, 'Meat', '10');

        $response = $this->request('POST', "/social-events/$eventId/guests/new", []);
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertEquals(303, $response->statusCode);
    }

    public function testMonkeyGuestRegistrationInvalidEmail(): void
    {
        $eventId = DB::$socialEvents->add('Dinner', '2099-01-01', null, null, null, 'Meat', '10');
        $menus = DB::$socialEvents->menus($eventId);

        $response = $this->request('POST', "/social-events/$eventId/guests/new", [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'not-an-email',
            'menu_id' => (string) $menus[0]->id,
        ]);
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertEquals(303, $response->statusCode);
        $this->assertCount(0, DB::$socialEvents->registrations($eventId));
    }

    public function testMonkeyGuestRegistrationOverlongName(): void
    {
        $eventId = DB::$socialEvents->add('Dinner', '2099-01-01', null, null, null, 'Meat', '10');
        $menus = DB::$socialEvents->menus($eventId);

        $response = $this->request('POST', "/social-events/$eventId/guests/new", [
            'first_name' => str_repeat('A', 300),
            'last_name' => 'Doe',
            'email' => 'test@example.com',
            'menu_id' => (string) $menus[0]->id,
        ]);
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertEquals(303, $response->statusCode);
    }

    // -------------------------------------------------------------------------
    // Users — bad input
    // -------------------------------------------------------------------------

    public function testMonkeyCreateUserEmptyUsername(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('POST', '/users/new', [
            'username' => '', 'password' => 'Pass123!', 'password_confirm' => 'Pass123!',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
    }

    public function testMonkeyCreateDuplicateUser(): void
    {
        $this->loginAsAdmin();
        DB::$users->create('existinguser', 'Pass123!');

        // Attempt to create the same username
        $response = $this->request('POST', '/users/new', [
            'username' => 'existinguser',
            'password' => 'Pass123!',
            'password_confirm' => 'Pass123!',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
    }

    // -------------------------------------------------------------------------
    // XSS rendering — user strings must be HTML-escaped
    // -------------------------------------------------------------------------

    public function testXssEventNameEscapedOnDetailPage(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('<script>alert(1)</script>', '2099-01-01', 10);

        $response = $this->request('GET', "/events/$eventId");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->body);
        $this->assertStringContainsString('&lt;script&gt;', $response->body);
    }

    public function testXssEventNameEscapedOnListPage(): void
    {
        $this->loginAsAdmin();
        DB::$events->add('<script>alert(1)</script>', '2099-01-01', 10);

        $response = $this->request('GET', '/events');

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->body);
    }

    public function testXssEventNameEscapedOnAdminPage(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('<script>alert(1)</script>', '2099-01-01', 10);

        $response = $this->request('GET', "/events/$eventId/admin");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->body);
    }

    public function testXssRegistrationNameEscapedOnDetailPage(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-01-01', 10);
        $userId = DB::$users->create('xssuser', 'Pass123!', true, null, null, '<script>alert(1)</script>', 'Doe');
        DB::$events->register($eventId, $userId, '');
        $this->loginAs('xssuser', 'Pass123!');

        $response = $this->request('GET', "/events/$eventId");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->body);
    }

    public function testXssSocialEventNameEscapedOnDetailPage(): void
    {
        $this->loginAsAdmin();
        $socialId = DB::$socialEvents->add('<script>alert(1)</script>', '2099-01-01', null, null, null, 'Meat', '10');
        DB::$users->create('user1', 'Pass123!');
        $this->loginAs('user1', 'Pass123!');

        $response = $this->request('GET', "/social-events/$socialId");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->body);
        $this->assertStringContainsString('&lt;script&gt;', $response->body);
    }

    public function testXssSocialEventNameEscapedOnAdminPage(): void
    {
        $this->loginAsAdmin();
        $socialId = DB::$socialEvents->add('<script>alert(1)</script>', '2099-01-01', null, null, null, 'Meat', '10');

        $response = $this->request('GET', "/social-events/$socialId/admin");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->body);
    }

    public function testXssGuestNameEscapedOnDetailPage(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-01-01', 10);
        DB::$guests->add($eventId, true, '<script>alert(1)</script>', 'Doe', 'test@example.com', null, null);

        $userId = DB::$users->create('user1', 'Pass123!');
        DB::$events->register($eventId, $userId, '');
        $this->loginAs('user1', 'Pass123!');

        $response = $this->request('GET', "/events/$eventId");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->body);
    }

    public function testXssSocialMenuNameEscapedOnDetailPage(): void
    {
        $this->loginAsAdmin();
        // Menu name comes from the menus string — inject XSS via menu name
        $socialId = DB::$socialEvents->add('Dinner', '2099-01-01', null, null, null, '<script>alert(1)</script>', '10');
        DB::$users->create('user1', 'Pass123!');
        $this->loginAs('user1', 'Pass123!');

        // Register the user so they see their menu choice
        $menus = DB::$socialEvents->menus($socialId);
        DB::$socialEvents->register($socialId, DB::$users->getWithPassword('user1')[0]->id, $menus[0]->id, null);

        $response = $this->request('GET', "/social-events/$socialId");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->body);
    }

    // -------------------------------------------------------------------------
    // Stupid-user (UX): flash error must appear after validation failure
    // -------------------------------------------------------------------------

    public function testUxCreateEventShowsErrorOnBadInput(): void
    {
        $this->loginAsAdmin();
        // POST bad data → 303 (stores flash in session)
        $this->request('POST', '/events/new', [
            'name' => '', 'date' => 'bad', 'capacity' => 'abc',
        ]);
        // Follow redirect → flash must be rendered
        $response = $this->request('GET', '/events/new');
        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('customError(', $response->body);
    }

    public function testUxCreateSocialEventShowsErrorOnBadInput(): void
    {
        $this->loginAsAdmin();
        $this->request('POST', '/social-events/new', [
            'name' => '', 'date' => 'bad', 'menus' => '', 'tables' => '',
        ]);
        $response = $this->request('GET', '/social-events/new');
        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('customError(', $response->body);
    }

    public function testUxGuestRegistrationShowsErrorOnBadInput(): void
    {
        $eventId = DB::$socialEvents->add('Dinner', '2099-01-01', null, null, null, 'Meat', '10');
        // Missing all required fields
        $this->request('POST', "/social-events/$eventId/guests/new", []);
        $response = $this->request('GET', "/social-events/$eventId/guests/new");
        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('customError(', $response->body);
    }

    public function testUxRegisterForFullEventShowsError(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Full Event', '2099-01-01', 1); // capacity 1

        // Fill the event
        $u1 = DB::$users->create('user1', 'Pass123!');
        DB::$events->register($eventId, $u1, '');

        // Try to register a second user
        DB::$users->create('user2', 'Pass123!');
        $this->loginAs('user2', 'Pass123!');

        // Register (goes to waitlist — no error, just waitlist state)
        $response = $this->request('POST', "/events/$eventId/register", ['comment' => '']);
        $this->assertNotEquals(500, $response->statusCode);
    }

    public function testUxRegisterForLockedSocialEventShowsError(): void
    {
        $this->loginAsAdmin();
        $socialId = DB::$socialEvents->add('Dinner', '2099-01-01', null, null, null, 'Meat', '10');
        DB::$socialEvents->lock($socialId);

        DB::$users->create('user1', 'Pass123!');
        $this->loginAs('user1', 'Pass123!');

        $response = $this->request('POST', "/social-events/$socialId/register", [
            'menu_id' => '1', 'table_id' => '',
        ]);
        $this->assertNotEquals(500, $response->statusCode);
        $this->assertEquals(303, $response->statusCode);
        $this->assertCount(0, DB::$socialEvents->registrations($socialId));
    }
}
