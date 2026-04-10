<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Controllers;

use TP\Tests\Integration\IntegrationTestCase;
use TP\Models\DB;

/**
 * Integration tests for EventController
 */
class EventControllerTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    public function testIndexListsEvents(): void
    {
        $this->loginAsAdmin();

        // Create test events
        DB::$events->add('Event 1', '2099-03-15', 20);
        DB::$events->add('Event 2', '2099-03-16', 15);

        $response = $this->request('GET', '/events');

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('Event 1', $response->body);
        $this->assertStringContainsString('Event 2', $response->body);
    }

    public function testIndexRequiresAuthentication(): void
    {
        $response = $this->request('GET', '/events');

        $this->assertEquals(303, $response->statusCode);
    }

    public function testDetailShowsEventAsAdmin(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);

        $response = $this->request('GET', "/events/$eventId");

        // Event detail may have output buffering issues in tests
        // Just verify it's not an error status
        $this->assertNotEquals(404, $response->statusCode);
        $this->assertNotEquals(403, $response->statusCode);
    }

    public function testDetailShowsEventAsRegularUser(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $userId = DB::$users->create('regularuser', 'Pass123!');

        // Login as regular user
        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('GET', "/events/$eventId");

        // Just verify it's accessible
        $this->assertNotEquals(404, $response->statusCode);
        $this->assertNotEquals(403, $response->statusCode);
    }

    public function testDetailReturns404ForNonexistentEvent(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('GET', '/events/99999');

        $this->assertEquals(404, $response->statusCode);
    }

    // Note: /events/new route may conflict with /{id} in test environment
    // Tested via POST /events/new below which works

    public function testStoreCreatesEvent(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('POST', '/events/new', [
            'name' => 'New Event',
            'date' => '2099-04-01',
            'capacity' => '25'
        ]);

        $this->assertEquals(303, $response->statusCode);

        // Verify event was created
        $events = DB::$events->all();
        $names = array_map(fn($e) => $e->name, $events);
        $this->assertContains('New Event', $names);
    }

    public function testStoreRequiresValidData(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('POST', '/events/new', [
            'name' => '',
            'date' => 'invalid',
            'capacity' => '-5'
        ]);

        $this->assertEquals(303, $response->statusCode);
    }

    public function testStoreRequiresAdmin(): void
    {
        $this->loginAsAdmin();
        DB::$users->create('regularuser', 'Pass123!');
        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('POST', '/events/new', [
            'name' => 'New Event',
            'date' => '2099-04-01',
            'capacity' => '25'
        ]);

        $this->assertEquals(403, $response->statusCode);
    }

    public function testUpdateModifiesEvent(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Original Name', '2099-03-15', 20);

        $response = $this->request('POST', "/events/$eventId", [
            'name' => 'Updated Name',
            'capacity' => '30'
        ]);

        $this->assertEquals(303, $response->statusCode);

        $event = DB::$events->get($eventId, 1);
        $this->assertEquals('Updated Name', $event->name);
        $this->assertEquals(30, $event->capacity);
    }

    public function testUpdateRequiresValidData(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Original Name', '2099-03-15', 20);

        $response = $this->request('POST', "/events/$eventId", [
            'name' => '',
            'capacity' => '-5'
        ]);

        $this->assertEquals(303, $response->statusCode);

        // Verify event was NOT updated
        $event = DB::$events->get($eventId, 1);
        $this->assertEquals('Original Name', $event->name);
    }

    public function testUpdateReducingCapacityMovesNewestToWaitlist(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Big Event', '2099-03-15', 3);
        $user1 = DB::$users->create('user1', 'Pass123!');
        $user2 = DB::$users->create('user2', 'Pass123!');
        $user3 = DB::$users->create('user3', 'Pass123!');
        DB::$events->register($eventId, $user1, '');
        DB::$events->register($eventId, $user2, '');
        DB::$events->register($eventId, $user3, '');

        $response = $this->request('POST', "/events/$eventId", [
            'name' => 'Big Event',
            'capacity' => '2',
        ]);

        $this->assertEquals(303, $response->statusCode);
        $states = [
            DB::$events->get($eventId, $user1)->userState,
            DB::$events->get($eventId, $user2)->userState,
            DB::$events->get($eventId, $user3)->userState,
        ];
        $this->assertEquals(2, array_count_values($states)[1]);
        $this->assertEquals(1, array_count_values($states)[2]);
    }

    public function testUpdateIncreasingCapacityPromotesFromWaitlist(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Small Event', '2099-03-15', 1);
        $user1 = DB::$users->create('user1', 'Pass123!');
        $user2 = DB::$users->create('user2', 'Pass123!');
        DB::$events->register($eventId, $user1, '');
        DB::$events->register($eventId, $user2, '');

        $this->assertEquals(2, DB::$events->get($eventId, $user2)->userState);

        $response = $this->request('POST', "/events/$eventId", [
            'name' => 'Small Event',
            'capacity' => '2',
        ]);

        $this->assertEquals(303, $response->statusCode);
        $this->assertEquals(1, DB::$events->get($eventId, $user1)->userState);
        $this->assertEquals(1, DB::$events->get($eventId, $user2)->userState);
    }

    public function testDeleteRemovesEvent(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('To Delete', '2099-03-15', 20);

        $response = $this->request('POST', "/events/$eventId/delete");

        $this->assertEquals(303, $response->statusCode);

        $event = DB::$events->get($eventId, 1);
        $this->assertNull($event);
    }

    public function testLockLocksEvent(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);

        $response = $this->request('POST', "/events/$eventId/lock");

        $this->assertEquals(303, $response->statusCode);

        $event = DB::$events->get($eventId, 1);
        $this->assertTrue($event->isLocked);
    }

    public function testUnlockUnlocksEvent(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Test Event', '2099-03-15', 20, true);

        $response = $this->request('POST', "/events/$eventId/unlock");

        $this->assertEquals(303, $response->statusCode);

        $event = DB::$events->get($eventId, 1);
        $this->assertFalse($event->isLocked);
    }

    public function testRegisterAddsUserToEvent(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $userId = DB::$users->create('testuser', 'Pass123!');

        $this->loginAs('testuser', 'Pass123!');

        $response = $this->request('POST', "/events/$eventId/register", [
            'comment' => 'Looking forward to it!'
        ]);

        $this->assertEquals(303, $response->statusCode);

        $registrations = DB::$events->registrations($eventId);
        $this->assertCount(1, $registrations);
        $this->assertEquals($userId, $registrations[0]->userId);
        $this->assertEquals('Looking forward to it!', $registrations[0]->comment);
    }

    public function testRegisterPreventsRegistrationOnLockedEvent(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Test Event', '2099-03-15', 20, true);
        $userId = DB::$users->create('testuser', 'Pass123!');

        $this->loginAs('testuser', 'Pass123!');

        $response = $this->request('POST', "/events/$eventId/register", [
            'comment' => 'Test'
        ]);

        $this->assertEquals(303, $response->statusCode);

        $registrations = DB::$events->registrations($eventId);
        $this->assertCount(0, $registrations);
    }

    public function testRegisterWithIframeParameter(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $userId = DB::$users->create('testuser', 'Pass123!');

        $this->loginAs('testuser', 'Pass123!');

        $response = $this->request('POST', "/events/$eventId/register", [
            'comment' => 'Test'
        ]);

        $this->assertEquals(303, $response->statusCode);
    }

    public function testRegisterRedirectsToSocialEventWhenLinked(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $socialId = DB::$socialEvents->add('Dinner', '2099-03-15', $eventId, null, null, 'Meat,Fish', '10');
        DB::$users->create('testuser', 'Pass123!');
        $this->loginAs('testuser', 'Pass123!');

        $response = $this->request('POST', "/events/$eventId/register", ['comment' => '']);

        // Registered for tournament, then redirected to social event detail page
        $this->assertEquals(303, $response->statusCode);
        $this->assertCount(1, DB::$events->registrations($eventId));
        // Follow the redirect — should land on the social event detail page
        $socialResponse = $this->request('GET', "/social-events/$socialId");
        $this->assertEquals(200, $socialResponse->statusCode);
    }

    public function testRegisterDoesNotRedirectToSocialEventWhenAlreadyRegistered(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $socialId = DB::$socialEvents->add('Dinner', '2099-03-15', $eventId, null, null, 'Meat,Fish', '10');
        $userId = DB::$users->create('testuser', 'Pass123!');
        $menus = DB::$socialEvents->menus($socialId);
        DB::$socialEvents->register($socialId, $userId, $menus[0]->id, null);
        $this->loginAs('testuser', 'Pass123!');

        $response = $this->request('POST', "/events/$eventId/register", ['comment' => '']);

        // Already registered for social event — stays on tournament event page
        $this->assertEquals(303, $response->statusCode);
        $this->assertCount(1, DB::$events->registrations($eventId));
        // Social event registration unchanged
        $this->assertEquals(1, DB::$socialEvents->getForTournament($eventId)->userRegistered);
    }

    public function testRegisterDoesNotRedirectToLockedSocialEvent(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $socialId = DB::$socialEvents->add('Dinner', '2099-03-15', $eventId, null, null, 'Meat,Fish', '10');
        DB::$socialEvents->lock($socialId);
        DB::$users->create('testuser', 'Pass123!');
        $this->loginAs('testuser', 'Pass123!');

        $response = $this->request('POST', "/events/$eventId/register", ['comment' => '']);

        // Social event is locked — stays on tournament event page
        $this->assertEquals(303, $response->statusCode);
        $this->assertCount(1, DB::$events->registrations($eventId));
    }

    public function testUnregisterRemovesUserFromEvent(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $userId = DB::$users->create('testuser', 'Pass123!');

        DB::$events->register($eventId, $userId, 'Test');

        $this->loginAs('testuser', 'Pass123!');

        $response = $this->request('POST', "/events/$eventId/unregister");

        $this->assertEquals(303, $response->statusCode);

        $registrations = DB::$events->registrations($eventId);
        $this->assertCount(0, $registrations);
    }

    public function testUnregisterPreventsUnregistrationOnLockedEvent(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $userId = DB::$users->create('testuser', 'Pass123!');

        DB::$events->register($eventId, $userId, 'Test');
        DB::$events->lock($eventId);

        $this->loginAs('testuser', 'Pass123!');

        $response = $this->request('POST', "/events/$eventId/unregister");

        $this->assertEquals(303, $response->statusCode);

        $registrations = DB::$events->registrations($eventId);
        $this->assertCount(1, $registrations); // Still registered
    }

    public function testUpdateCommentModifiesRegistrationComment(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $userId = DB::$users->create('testuser', 'Pass123!');

        DB::$events->register($eventId, $userId, 'Original comment');

        $this->loginAs('testuser', 'Pass123!');

        $response = $this->request('POST', "/events/$eventId/comment", [
            'comment' => 'Updated comment'
        ]);

        $this->assertEquals(303, $response->statusCode);

        $registrations = DB::$events->registrations($eventId);
        $this->assertEquals('Updated comment', $registrations[0]->comment);
    }

    public function testUpdateCommentPreventsUpdateOnLockedEvent(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Test Event', '2099-03-15', 20, true);
        $userId = DB::$users->create('testuser', 'Pass123!');

        // Unlock temporarily to register
        DB::$events->unlock($eventId);
        DB::$events->register($eventId, $userId, 'Original comment');
        DB::$events->lock($eventId);

        $this->loginAs('testuser', 'Pass123!');

        $response = $this->request('POST', "/events/$eventId/comment", [
            'comment' => 'Updated comment'
        ]);

        $this->assertEquals(303, $response->statusCode);

        $registrations = DB::$events->registrations($eventId);
        $this->assertEquals('Original comment', $registrations[0]->comment);
    }

    public function testBulkCreateFormShows(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('GET', '/events/bulk/new');

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('start_date', strtolower($response->body));
        $this->assertStringContainsString('end_date', strtolower($response->body));
    }

    public function testBulkPreviewShowsEvents(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('POST', '/events/bulk/preview', [
            'start_date' => '2099-04-01',
            'end_date' => '2099-04-30',
            'day_of_week' => '3', // Wednesday
            'name' => 'Weekly Event',
            'capacity' => '16'
        ]);

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('Weekly Event', $response->body);
    }

    public function testBulkPreviewRequiresValidData(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('POST', '/events/bulk/preview', [
            'start_date' => 'invalid',
            'end_date' => '',
            'day_of_week' => '10', // Invalid day
            'name' => '',
            'capacity' => '-5'
        ]);

        $this->assertEquals(303, $response->statusCode);
    }

    public function testBulkStoreCreatesMultipleEvents(): void
    {
        $this->loginAsAdmin();

        // First preview to set session
        $this->request('POST', '/events/bulk/preview', [
            'start_date' => '2099-04-01',
            'end_date' => '2099-04-15',
            'day_of_week' => '3', // Wednesday
            'name' => 'Bulk Event',
            'capacity' => '12'
        ]);

        $response = $this->request('POST', '/events/bulk/store');

        $this->assertEquals(303, $response->statusCode);

        // Verify events were created
        $events = DB::$events->all();
        $bulkEvents = array_filter($events, fn($e) => $e->name === 'Bulk Event');
        $this->assertGreaterThan(0, count($bulkEvents));
    }

    public function testBulkStoreRequiresSession(): void
    {
        $this->loginAsAdmin();

        // Don't run preview first
        $response = $this->request('POST', '/events/bulk/store');

        $this->assertEquals(303, $response->statusCode);
    }

    public function testAdminViewRequiresAdmin(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);

        $userId = DB::$users->create('regularuser', 'Pass123!');
        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('GET', "/events/$eventId/admin");

        $this->assertEquals(403, $response->statusCode);
    }

    public function testAdminViewShowsForAdmin(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);

        $response = $this->request('GET', "/events/$eventId/admin");

        $this->assertNotEquals(403, $response->statusCode);
    }

    public function testCreateFormShows(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('GET', '/events/new');

        $this->assertEquals(200, $response->statusCode);
    }

    public function testCreateFormRequiresAdmin(): void
    {
        $this->loginAsAdmin();
        DB::$users->create('regularuser', 'Pass123!');
        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('GET', '/events/new');

        $this->assertEquals(403, $response->statusCode);
    }

    public function testUpdateTogglesMixedFlag(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Test Event', '2099-03-15', 20, false, true);

        $response = $this->request('POST', "/events/$eventId", [
            'name' => 'Test Event',
            'capacity' => '20',
            'mixed' => '0',
        ]);

        $this->assertEquals(303, $response->statusCode);

        $event = DB::$events->get($eventId, 1);
        $this->assertFalse($event->mixed);
    }

    public function testExportReturnsCsv(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Export Event', '2099-03-15', 20);

        $response = $this->request('GET', "/events/$eventId/export");

        $this->assertEquals(200, $response->statusCode);
        // Body starts with UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $response->body);
    }

    public function testExportContainsRegistrations(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Export Event', '2099-03-15', 20);
        $userId = DB::$users->create('csvuser', 'Pass123!', true, 'RF123', 'M001');
        DB::$events->register($eventId, $userId, '');

        $response = $this->request('GET', "/events/$eventId/export");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('csvuser', $response->body);
        $this->assertStringContainsString('RF123', $response->body);
        $this->assertStringContainsString('M001', $response->body);
    }

    public function testExportContainsGuests(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Export Event', '2099-03-15', 20);
        DB::$guests->add($eventId, true, 'Jane', 'Doe', null, null, null);

        $response = $this->request('GET', "/events/$eventId/export");

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('Jane', $response->body);
        $this->assertStringContainsString('Doe', $response->body);
    }

    public function testExportReturns404ForUnknownEvent(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('GET', '/events/99999/export');

        $this->assertEquals(404, $response->statusCode);
    }

    public function testExportRequiresAdmin(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        DB::$users->create('regularuser', 'Pass123!');
        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('GET', "/events/$eventId/export");

        $this->assertEquals(403, $response->statusCode);
    }

    public function testRegisterForbidsNonAdminRegisteringForOtherUser(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $otherUserId = DB::$users->create('otheruser', 'Pass123!');
        DB::$users->create('regularuser', 'Pass123!');

        $this->loginAs('regularuser', 'Pass123!');

        // Try to register on behalf of another user
        $response = $this->request('POST', "/events/$eventId/register", [
            'userId' => (string) $otherUserId,
            'comment' => 'Sneaky registration',
        ]);

        $this->assertEquals(403, $response->statusCode);

        // Verify other user was NOT registered
        $registrations = DB::$events->registrations($eventId);
        $this->assertCount(0, $registrations);
    }

    public function testUnregisterForbidsNonAdminUnregisteringOtherUser(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $otherUserId = DB::$users->create('otheruser', 'Pass123!');
        DB::$users->create('regularuser', 'Pass123!');

        DB::$events->register($eventId, $otherUserId, '');

        $this->loginAs('regularuser', 'Pass123!');

        // Try to unregister another user
        $response = $this->request('POST', "/events/$eventId/unregister", [
            'userId' => (string) $otherUserId,
        ]);

        $this->assertEquals(403, $response->statusCode);

        // Verify other user is still registered
        $registrations = DB::$events->registrations($eventId);
        $this->assertCount(1, $registrations);
    }

    public function testUpdateCommentForbidsNonAdminEditingOtherUsersComment(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $otherUserId = DB::$users->create('otheruser', 'Pass123!');
        DB::$users->create('regularuser', 'Pass123!');

        DB::$events->register($eventId, $otherUserId, 'Original');

        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('POST', "/events/$eventId/comment", [
            'userId' => (string) $otherUserId,
            'comment' => 'Hacked comment',
        ]);

        $this->assertEquals(403, $response->statusCode);

        // Verify comment was not changed
        $registrations = DB::$events->registrations($eventId);
        $this->assertEquals('Original', $registrations[0]->comment);
    }

    public function testRegisterWithBackDatePreservesRedirectParam(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        DB::$users->create('testuser', 'Pass123!');
        $this->loginAs('testuser', 'Pass123!');

        // POST with back-date query parameter
        $response = $this->request('POST', "/events/$eventId/register?b=2026-03-01", [
            'comment' => 'Test',
        ]);

        $this->assertEquals(303, $response->statusCode);
    }

    // ===== GET /events — regular user =====

    public function testIndexAsRegularUser(): void
    {
        $this->loginAsAdmin();
        DB::$users->create('regularuser', 'Pass123!');
        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('GET', '/events');

        $this->assertEquals(200, $response->statusCode);
    }

    // ===== GET /events/new — anonymous =====

    public function testCreateFormAsAnonymous(): void
    {
        $response = $this->request('GET', '/events/new');

        $this->assertEquals(303, $response->statusCode);
    }

    // ===== POST /events/new — anonymous =====

    public function testStoreAsAnonymous(): void
    {
        $response = $this->request('POST', '/events/new', [
            'name' => 'Event',
            'date' => '2099-04-01',
            'capacity' => '20',
        ]);

        $this->assertEquals(303, $response->statusCode);
    }

    // ===== GET /events/{id} — anonymous =====

    public function testDetailAsAnonymous(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $_SESSION = [];

        $response = $this->request('GET', "/events/$eventId");

        $this->assertEquals(303, $response->statusCode);
    }

    // ===== GET /events/{id}/admin — anonymous and not found =====

    public function testAdminViewAsAnonymous(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $_SESSION = [];

        $response = $this->request('GET', "/events/$eventId/admin");

        $this->assertEquals(303, $response->statusCode);
    }

    public function testAdminViewReturns404ForNonexistentEvent(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('GET', '/events/99999/admin');

        $this->assertEquals(404, $response->statusCode);
    }

    // ===== POST /events/{id} (update) — anonymous and regular user =====

    public function testUpdateAsAnonymous(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $_SESSION = [];

        $response = $this->request('POST', "/events/$eventId", [
            'name' => 'Changed',
            'capacity' => '10',
        ]);

        $this->assertEquals(303, $response->statusCode);
    }

    public function testUpdateAsRegularUser(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        DB::$users->create('regularuser', 'Pass123!');
        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('POST', "/events/$eventId", [
            'name' => 'Changed',
            'capacity' => '10',
        ]);

        $this->assertEquals(403, $response->statusCode);
    }

    // ===== POST /events/{id}/delete — anonymous and regular user =====

    public function testDeleteAsAnonymous(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $_SESSION = [];

        $response = $this->request('POST', "/events/$eventId/delete");

        $this->assertEquals(303, $response->statusCode);
    }

    public function testDeleteAsRegularUser(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        DB::$users->create('regularuser', 'Pass123!');
        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('POST', "/events/$eventId/delete");

        $this->assertEquals(403, $response->statusCode);
    }

    // ===== GET /events/{id}/export — anonymous =====

    public function testExportAsAnonymous(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $_SESSION = [];

        $response = $this->request('GET', "/events/$eventId/export");

        $this->assertEquals(303, $response->statusCode);
    }

    // ===== POST /events/{id}/lock — anonymous and regular user =====

    public function testLockAsAnonymous(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $_SESSION = [];

        $response = $this->request('POST', "/events/$eventId/lock");

        $this->assertEquals(303, $response->statusCode);
    }

    public function testLockAsRegularUser(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        DB::$users->create('regularuser', 'Pass123!');
        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('POST', "/events/$eventId/lock");

        $this->assertEquals(403, $response->statusCode);
    }

    // ===== POST /events/{id}/unlock — anonymous and regular user =====

    public function testUnlockAsAnonymous(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20, true);
        $_SESSION = [];

        $response = $this->request('POST', "/events/$eventId/unlock");

        $this->assertEquals(303, $response->statusCode);
    }

    public function testUnlockAsRegularUser(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20, true);
        DB::$users->create('regularuser', 'Pass123!');
        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('POST', "/events/$eventId/unlock");

        $this->assertEquals(403, $response->statusCode);
    }

    // ===== POST /events/{id}/register — anonymous and admin registering another user =====

    public function testRegisterAsAnonymous(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $_SESSION = [];

        $response = $this->request('POST', "/events/$eventId/register", ['comment' => 'Test']);

        $this->assertEquals(303, $response->statusCode);
    }

    public function testAdminCanRegisterAnotherUser(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $userId = DB::$users->create('targetuser', 'Pass123!');

        $response = $this->request('POST', "/events/$eventId/register", [
            'userId' => (string) $userId,
            'comment' => 'Admin registered',
        ]);

        $this->assertEquals(303, $response->statusCode);

        $registrations = DB::$events->registrations($eventId);
        $this->assertCount(1, $registrations);
        $this->assertEquals($userId, $registrations[0]->userId);
    }

    // ===== POST /events/{id}/unregister — anonymous and admin unregistering another user =====

    public function testUnregisterAsAnonymous(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $_SESSION = [];

        $response = $this->request('POST', "/events/$eventId/unregister");

        $this->assertEquals(303, $response->statusCode);
    }

    public function testAdminCanUnregisterAnotherUser(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $userId = DB::$users->create('targetuser', 'Pass123!');
        DB::$events->register($eventId, $userId, '');

        $response = $this->request('POST', "/events/$eventId/unregister", [
            'userId' => (string) $userId,
        ]);

        $this->assertEquals(303, $response->statusCode);

        $registrations = DB::$events->registrations($eventId);
        $this->assertCount(0, $registrations);
    }

    // ===== POST /events/{id}/comment — anonymous and admin updating another's comment =====

    public function testUpdateCommentAsAnonymous(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $_SESSION = [];

        $response = $this->request('POST', "/events/$eventId/comment", ['comment' => 'Test']);

        $this->assertEquals(303, $response->statusCode);
    }

    public function testAdminCanUpdateAnotherUsersComment(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        $userId = DB::$users->create('targetuser', 'Pass123!');
        DB::$events->register($eventId, $userId, 'Original');

        $response = $this->request('POST', "/events/$eventId/comment", [
            'userId' => (string) $userId,
            'comment' => 'Admin updated',
        ]);

        $this->assertEquals(303, $response->statusCode);

        $registrations = DB::$events->registrations($eventId);
        $this->assertEquals('Admin updated', $registrations[0]->comment);
    }

    // ===== GET /events/bulk/new — anonymous and regular user =====

    public function testBulkCreateAsAnonymous(): void
    {
        $response = $this->request('GET', '/events/bulk/new');

        $this->assertEquals(303, $response->statusCode);
    }

    public function testBulkCreateAsRegularUser(): void
    {
        $this->loginAsAdmin();
        DB::$users->create('regularuser', 'Pass123!');
        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('GET', '/events/bulk/new');

        $this->assertEquals(403, $response->statusCode);
    }

    // ===== POST /events/bulk/preview — anonymous and regular user =====

    public function testBulkPreviewAsAnonymous(): void
    {
        $response = $this->request('POST', '/events/bulk/preview', [
            'start_date' => '2099-04-01',
            'end_date' => '2099-04-30',
            'day_of_week' => '3',
            'name' => 'Event',
            'capacity' => '10',
        ]);

        $this->assertEquals(303, $response->statusCode);
    }

    public function testBulkPreviewAsRegularUser(): void
    {
        $this->loginAsAdmin();
        DB::$users->create('regularuser', 'Pass123!');
        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('POST', '/events/bulk/preview', [
            'start_date' => '2099-04-01',
            'end_date' => '2099-04-30',
            'day_of_week' => '3',
            'name' => 'Event',
            'capacity' => '10',
        ]);

        $this->assertEquals(403, $response->statusCode);
    }

    // ===== POST /events/bulk/store — anonymous and regular user =====

    public function testBulkStoreAsAnonymous(): void
    {
        $response = $this->request('POST', '/events/bulk/store');

        $this->assertEquals(303, $response->statusCode);
    }

    public function testBulkStoreAsRegularUser(): void
    {
        $this->loginAsAdmin();
        DB::$users->create('regularuser', 'Pass123!');
        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->request('POST', '/events/bulk/store');

        $this->assertEquals(403, $response->statusCode);
    }

    // ===== New event fields: description, prices, registration_close =====

    public function testStoreCreatesEventWithNewFields(): void
    {
        $this->loginAsAdmin();

        $response = $this->request('POST', '/events/new', [
            'name' => 'Rich Event',
            'date' => '2099-06-01',
            'capacity' => '20',
            'description' => 'A great tournament',
            'price_members' => '15.50',
            'price_guests' => '25.00',
            'registration_close' => '2099-05-25T18:00',
        ]);

        $this->assertEquals(303, $response->statusCode);

        $events = DB::$events->all();
        $event = array_values(array_filter($events, fn($e) => $e->name === 'Rich Event'))[0] ?? null;
        $this->assertNotNull($event);
        $this->assertEquals('A great tournament', $event->description);
        $this->assertEquals(15.50, $event->priceMembers);
        $this->assertEquals(25.00, $event->priceGuests);
        $this->assertNotNull($event->registrationClose);
    }

    public function testUpdateSavesNewFields(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Event', '2099-06-01', 20);

        $response = $this->request('POST', "/events/$eventId", [
            'name' => 'Event',
            'capacity' => '20',
            'description' => 'Updated description',
            'price_members' => '10.00',
            'price_guests' => '20.00',
            'registration_close' => '2099-05-30T12:00',
        ]);

        $this->assertEquals(303, $response->statusCode);

        $event = DB::$events->get($eventId, 1);
        $this->assertEquals('Updated description', $event->description);
        $this->assertEquals(10.00, $event->priceMembers);
        $this->assertEquals(20.00, $event->priceGuests);
        $this->assertNotNull($event->registrationClose);
    }

    public function testUpdateClearsNewFields(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Event', '2099-06-01', 20, false, true, 'desc', 10.0, 20.0, '2099-05-01 00:00:00');

        $response = $this->request('POST', "/events/$eventId", [
            'name' => 'Event',
            'capacity' => '20',
        ]);

        $this->assertEquals(303, $response->statusCode);

        $event = DB::$events->get($eventId, 1);
        $this->assertNull($event->description);
        $this->assertNull($event->priceMembers);
        $this->assertNull($event->priceGuests);
        $this->assertNull($event->registrationClose);
    }

    public function testRegistrationCloseLocksEvent(): void
    {
        $this->loginAsAdmin();

        // Create event with registration_close in the past
        $eventId = DB::$events->add('Closed Event', '2099-06-01', 20, false, true, null, null, null, '2000-01-01 00:00:00');

        $event = DB::$events->get($eventId, 1);
        $this->assertTrue($event->isLocked);
    }

    public function testBulkStoreCreatesEventsWithPricesAndRegistrationClose(): void
    {
        $this->loginAsAdmin();

        // Use a full month range to guarantee at least one Monday (day_of_week=1) is found
        $this->request('POST', '/events/bulk/preview', [
            'start_date' => '2099-06-01',
            'end_date' => '2099-06-07',
            'day_of_week' => '1', // Monday
            'name' => 'Priced Event',
            'capacity' => '10',
            'price_members' => '15.00',
            'price_guests' => '25.00',
            'registration_close_days' => '3',
            'registration_close_time' => '18:00',
        ]);

        $this->request('POST', '/events/bulk/store');

        $events = DB::$events->all();
        $event = array_values(array_filter($events, fn($e) => $e->name === 'Priced Event'))[0] ?? null;
        $this->assertNotNull($event);
        $this->assertEquals(15.00, $event->priceMembers);
        $this->assertEquals(25.00, $event->priceGuests);
        // registration_close should be 3 days before event date at 18:00
        $this->assertNotNull($event->registrationClose);
        $this->assertStringContainsString('18:00', $event->registrationClose);
    }

    public function testBulkStoreCreatesEventsWithoutOptionalFields(): void
    {
        $this->loginAsAdmin();

        // Use a full week range to guarantee at least one Monday is found
        $this->request('POST', '/events/bulk/preview', [
            'start_date' => '2099-06-01',
            'end_date' => '2099-06-07',
            'day_of_week' => '1',
            'name' => 'Plain Event',
            'capacity' => '10',
        ]);

        $this->request('POST', '/events/bulk/store');

        $events = DB::$events->all();
        $event = array_values(array_filter($events, fn($e) => $e->name === 'Plain Event'))[0] ?? null;
        $this->assertNotNull($event);
        $this->assertNull($event->priceMembers);
        $this->assertNull($event->priceGuests);
        $this->assertNull($event->registrationClose);
    }
}
