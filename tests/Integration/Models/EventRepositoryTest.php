<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Models;

use TP\Tests\Integration\IntegrationTestCase;
use TP\Models\DB;

/**
 * Integration tests for EventRepository methods not covered by controller tests
 */
class EventRepositoryTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    public function testAllWithDateReturnsEventsForThatMonth(): void
    {
        $this->loginAsAdmin();

        DB::$events->add('March Event', '2026-03-15', 20);
        DB::$events->add('April Event', '2026-04-10', 20);

        // Trigger all() with a date via the home page
        $response = $this->request('GET', '/', ['date' => '2026-03-01']);

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('March Event', $response->body);
    }

    public function testAllForGuestWithDateReturnsEventsForThatMonth(): void
    {
        // Not logged in — guest view uses allForGuest($date)
        DB::$events->add('March Event', '2026-03-15', 20);
        DB::$events->add('April Event', '2026-04-10', 20);

        $response = $this->request('GET', '/', ['date' => '2026-03-01']);

        $this->assertEquals(200, $response->statusCode);
        $this->assertStringContainsString('March Event', $response->body);
    }

    public function testRegisteredEventsReturnsEventsForUser(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Test Event', '2026-03-15', 20);
        $userId = DB::$users->create('testuser', 'Pass123!');

        DB::$events->register($eventId, $userId, '');

        $registered = DB::$events->registeredEvents($userId);

        $this->assertCount(1, $registered);
        $this->assertEquals($eventId, $registered[0]->id);
    }

    public function testRegisteredEventsReturnsAllEventsWithUserState(): void
    {
        $this->loginAsAdmin();

        DB::$events->add('Test Event', '2026-03-15', 20);
        $userId = DB::$users->create('testuser', 'Pass123!');

        // registeredEvents returns all events (with LEFT JOIN for user state)
        // userState will be 0 for events the user isn't registered to
        $events = DB::$events->registeredEvents($userId);

        $this->assertCount(1, $events);
        $this->assertEquals(0, $events[0]->userState);
    }

    public function testAvailableUsersExcludesRegisteredUsersForAdmin(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Test Event', '2026-03-15', 20);
        $userId = DB::$users->create('testuser', 'Pass123!');

        DB::$events->register($eventId, $userId, '');

        $available = DB::$events->availableUsers($eventId);

        $availableIds = array_map(fn($u) => $u->id, $available);
        $this->assertNotContains($userId, $availableIds);
    }

    public function testAvailableUsersIncludesUnregisteredUsersForAdmin(): void
    {
        $this->loginAsAdmin();

        $eventId = DB::$events->add('Test Event', '2026-03-15', 20);
        $userId = DB::$users->create('testuser', 'Pass123!');

        $available = DB::$events->availableUsers($eventId);

        $availableIds = array_map(fn($u) => $u->id, $available);
        $this->assertContains($userId, $availableIds);
    }

    public function testAvailableUsersAsNonAdmin(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2026-03-15', 20);
        $userId = DB::$users->create('testuser', 'Pass123!');

        $this->loginAs('testuser', 'Pass123!');

        // Non-admin: only sees themselves if not registered
        $available = DB::$events->availableUsers($eventId);

        $availableIds = array_map(fn($u) => $u->id, $available);
        $this->assertContains($userId, $availableIds);
    }

    public function testAvailableUsersAsNonAdminExcludesIfRegistered(): void
    {
        $this->loginAsAdmin();
        $eventId = DB::$events->add('Test Event', '2026-03-15', 20);
        $userId = DB::$users->create('testuser', 'Pass123!');

        DB::$events->register($eventId, $userId, '');

        $this->loginAs('testuser', 'Pass123!');

        // Non-admin who is already registered should not appear in available list
        $available = DB::$events->availableUsers($eventId);

        $availableIds = array_map(fn($u) => $u->id, $available);
        $this->assertNotContains($userId, $availableIds);
    }

    public function testFixMovesPeopleFromWaitlistWhenCapacityFrees(): void
    {
        $this->loginAsAdmin();

        // Create event with capacity 1
        $eventId = DB::$events->add('Small Event', '2026-03-15', 1);
        $userId1 = DB::$users->create('user1', 'Pass123!');
        $userId2 = DB::$users->create('user2', 'Pass123!');

        // Register first user (state=1 joined), second goes to waitlist (state=2)
        DB::$events->register($eventId, $userId1, '');
        DB::$events->register($eventId, $userId2, '');

        $registrations = DB::$events->registrations($eventId);
        $states = array_column(
            array_map(fn($r) => ['userId' => $r->userId, 'state' => $r->state], $registrations),
            'state',
            'userId'
        );
        $this->assertEquals(1, $states[$userId1]);
        $this->assertEquals(2, $states[$userId2]);

        // Unregister user1 — fix() should promote user2 from waitlist
        DB::$events->unregister($eventId, $userId1);

        $registrations = DB::$events->registrations($eventId);
        $this->assertCount(1, $registrations);
        $this->assertEquals(1, $registrations[0]->state); // Promoted to joined
    }

    public function testFixMovesJoinedToWaitlistWhenCapacityReducedViaUpdate(): void
    {
        $this->loginAsAdmin();

        // Create event with capacity 2
        $eventId = DB::$events->add('Medium Event', '2026-03-15', 2);
        $userId1 = DB::$users->create('user1', 'Pass123!');
        $userId2 = DB::$users->create('user2', 'Pass123!');

        // Register both users (both state=1 joined)
        DB::$events->register($eventId, $userId1, '');
        DB::$events->register($eventId, $userId2, '');

        // Reduce capacity to 1 — fix() should demote one user to waitlist
        DB::$events->update($eventId, 'Medium Event', 1);

        $registrations = DB::$events->registrations($eventId);
        $states = array_map(fn($r) => $r->state, $registrations);
        sort($states);
        $this->assertEquals([1, 2], $states); // One joined, one waitlisted
    }
}
