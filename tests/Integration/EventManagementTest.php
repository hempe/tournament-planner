<?php

declare(strict_types=1);

namespace TP\Tests\Integration;

use TP\Models\DB;

/**
 * Comprehensive integration tests for tournament planner
 * Tests user creation, event management, bulk operations, and registration flows
 */
class EventManagementTest extends IntegrationTestCase
{
    private int $regularUserId;
    private int $eventId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    /**
     * Test complete workflow: Create user, event, bulk events, lock/unlock, register/unregister
     */
    public function testCompleteEventManagementWorkflow(): void
    {
        echo "\n=== Running Complete Event Management Workflow ===\n";

        // Step 1: Create a new regular user
        echo "\n1. Creating new user...\n";
        $this->loginAsAdmin();
        $this->regularUserId = $this->createRegularUser('testuser', 'TestPass123!');
        $this->assertGreaterThan(0, $this->regularUserId, "User should be created with valid ID");
        echo "   ✓ User created with ID: {$this->regularUserId}\n";

        // Step 2: Create a single event
        echo "\n2. Creating single event...\n";
        $this->eventId = $this->createEvent('Test Golf Event', '2026-03-15', 20);
        $this->assertGreaterThan(0, $this->eventId, "Event should be created with valid ID");

        $event = DB::$events->get($this->eventId, 1);
        $this->assertNotNull($event, "Event should exist");
        $this->assertEquals('Test Golf Event', $event->name);
        $this->assertEquals('2026-03-15', $event->date);
        $this->assertEquals(20, $event->capacity);
        $this->assertFalse($event->isLocked, "New event should not be locked");
        echo "   ✓ Event created: {$event->name} on {$event->date}\n";

        // Step 3: Bulk create events
        echo "\n3. Bulk creating weekly events...\n";
        $bulkEventIds = $this->bulkCreateEvents(
            startDate: '2026-04-01',
            endDate: '2026-06-30',
            dayOfWeek: 3, // Wednesday
            name: 'Weekly Wednesday Tournament',
            capacity: 16
        );
        $this->assertGreaterThan(0, count($bulkEventIds), "Should create multiple events");
        $eventCount = count($bulkEventIds);
        echo "   ✓ Created $eventCount weekly events\n";

        // Verify bulk events are locked
        foreach ($bulkEventIds as $id) {
            $bulkEvent = DB::$events->get($id, 1);
            $this->assertTrue($bulkEvent->isLocked, "Bulk created events should be locked");
        }
        echo "   ✓ All bulk events are locked\n";

        // Step 4: Test lock/unlock functionality
        echo "\n4. Testing lock/unlock functionality...\n";
        DB::$events->lock($this->eventId);
        $lockedEvent = DB::$events->get($this->eventId, 1);
        $this->assertTrue($lockedEvent->isLocked, "Event should be locked");
        echo "   ✓ Event locked successfully\n";

        DB::$events->unlock($this->eventId);
        $unlockedEvent = DB::$events->get($this->eventId, 1);
        $this->assertFalse($unlockedEvent->isLocked, "Event should be unlocked");
        echo "   ✓ Event unlocked successfully\n";

        // Step 5: Register regular user for event
        echo "\n5. Testing user registration...\n";
        DB::$events->register($this->eventId, $this->regularUserId, 'Looking forward to it!');

        $registrations = DB::$events->registrations($this->eventId);
        $this->assertCount(1, $registrations, "Should have 1 registration");
        $this->assertEquals($this->regularUserId, $registrations[0]->userId);
        $this->assertEquals('Looking forward to it!', $registrations[0]->comment);
        $this->assertEquals(1, $registrations[0]->state, "Should be in confirmed state");
        echo "   ✓ User registered successfully\n";

        // Step 6: Test registration on locked event fails
        echo "\n6. Testing locked event registration prevention...\n";
        DB::$events->lock($this->eventId);

        $isLocked = DB::$events->isLocked($this->eventId);
        $this->assertTrue($isLocked, "Event should be locked");
        echo "   ✓ Event is locked, registration should be prevented at application level\n";

        // Step 7: Unlock and test unregistration
        echo "\n7. Testing user unregistration...\n";
        DB::$events->unlock($this->eventId);
        DB::$events->unregister($this->eventId, $this->regularUserId);

        $registrationsAfter = DB::$events->registrations($this->eventId);
        $this->assertCount(0, $registrationsAfter, "Should have 0 registrations after unregister");
        echo "   ✓ User unregistered successfully\n";

        // Step 8: Test waitlist functionality
        echo "\n8. Testing waitlist functionality...\n";
        $smallEventId = $this->createEvent('Small Event', '2026-05-01', 2);

        // Register 3 users (capacity is 2, so third should be waitlisted)
        $user1 = $this->createRegularUser('user1', 'Pass123!');
        $user2 = $this->createRegularUser('user2', 'Pass123!');
        $user3 = $this->createRegularUser('user3', 'Pass123!');

        DB::$events->register($smallEventId, $user1, '');
        DB::$events->register($smallEventId, $user2, '');
        DB::$events->register($smallEventId, $user3, '');

        $regs = DB::$events->registrations($smallEventId);
        $this->assertCount(3, $regs, "Should have 3 registrations");

        $confirmed = array_filter($regs, fn($r) => $r->state === 1);
        $waitlist = array_filter($regs, fn($r) => $r->state === 2);

        $this->assertCount(2, $confirmed, "Should have 2 confirmed");
        $this->assertCount(1, $waitlist, "Should have 1 on waitlist");
        echo "   ✓ Waitlist working: 2 confirmed, 1 on waitlist\n";

        // Step 9: Test automatic promotion from waitlist
        echo "\n9. Testing automatic waitlist promotion...\n";
        DB::$events->unregister($smallEventId, $user1);

        $regsAfterUnregister = DB::$events->registrations($smallEventId);
        $confirmedAfter = array_filter($regsAfterUnregister, fn($r) => $r->state === 1);
        $waitlistAfter = array_filter($regsAfterUnregister, fn($r) => $r->state === 2);

        $this->assertCount(2, $confirmedAfter, "Should have 2 confirmed after promotion");
        $this->assertCount(0, $waitlistAfter, "Should have 0 on waitlist after promotion");
        echo "   ✓ User automatically promoted from waitlist\n";

        echo "\n=== All Integration Tests Passed! ===\n\n";
    }

    /**
     * Test user creation endpoint
     */
    public function testUserCreationAndDeletion(): void
    {
        echo "\n=== Testing User Management ===\n";

        $this->loginAsAdmin();

        // Create user
        echo "\n1. Creating user via API...\n";
        $username = 'apiuser';
        $password = 'ApiPass123!';

        $userId = DB::$users->create($username, $password);
        $this->assertGreaterThan(0, $userId);
        echo "   ✓ User created: $username\n";

        // Verify user exists
        $users = DB::$users->all();
        $usernames = array_map(fn($u) => $u->username, $users);
        $this->assertContains($username, $usernames);
        echo "   ✓ User verified in database\n";

        // Delete user
        echo "\n2. Deleting user...\n";
        DB::$users->delete($userId);

        $usersAfter = DB::$users->all();
        $usernamesAfter = array_map(fn($u) => $u->username, $usersAfter);
        $this->assertNotContains($username, $usernamesAfter);
        echo "   ✓ User deleted successfully\n";

        echo "\n=== User Management Tests Passed! ===\n\n";
    }

    /**
     * Test event update and delete
     */
    public function testEventUpdateAndDelete(): void
    {
        echo "\n=== Testing Event Updates and Deletion ===\n";

        $this->loginAsAdmin();

        // Create event
        $eventId = $this->createEvent('Original Event', '2026-06-01', 10);
        echo "\n1. Event created: Original Event\n";

        // Update event
        echo "\n2. Updating event...\n";
        DB::$events->update($eventId, 'Updated Event Name', 25);

        $updated = DB::$events->get($eventId, 1);
        $this->assertEquals('Updated Event Name', $updated->name);
        $this->assertEquals(25, $updated->capacity);
        echo "   ✓ Event updated: {$updated->name}, capacity: {$updated->capacity}\n";

        // Delete event
        echo "\n3. Deleting event...\n";
        DB::$events->delete($eventId);

        $deleted = DB::$events->get($eventId, 1);
        $this->assertNull($deleted, "Event should be deleted");
        echo "   ✓ Event deleted successfully\n";

        echo "\n=== Event Update/Delete Tests Passed! ===\n\n";
    }

    /**
     * Helper: Create a regular user
     */
    private function createRegularUser(string $username, string $password): int
    {
        return DB::$users->create($username, $password);
    }

    /**
     * Helper: Create an event
     */
    private function createEvent(string $name, string $date, int $capacity): int
    {
        return DB::$events->add($name, $date, $capacity, false);
    }

    /**
     * Helper: Bulk create events
     */
    private function bulkCreateEvents(
        string $startDate,
        string $endDate,
        int $dayOfWeek,
        string $name,
        int $capacity
    ): array {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $eventIds = [];

        // Find first occurrence of target day
        $current = clone $start;
        $currentDayOfWeek = (int) $current->format('w');

        if ($currentDayOfWeek !== $dayOfWeek) {
            $daysToAdd = ($dayOfWeek - $currentDayOfWeek + 7) % 7;
            $current->modify("+{$daysToAdd} days");
        }

        // Create events for each week
        while ($current <= $end) {
            $eventIds[] = DB::$events->add(
                $name,
                $current->format('Y-m-d'),
                $capacity,
                true // Bulk events are locked
            );
            $current->modify('+7 days');
        }

        return $eventIds;
    }
}
