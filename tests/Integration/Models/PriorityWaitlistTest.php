<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Models;

use TP\Core\Flavor;
use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class PriorityWaitlistTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    private function user(string $name, ?int $priority): int
    {
        $id = DB::$users->create($name, 'Pass123!', true, null, null, $name, $name);
        DB::$users->updateExtra($id, ['priority' => $priority === null ? null : (string) $priority]);
        return $id;
    }

    /** Registers in the given order with distinct timestamps; returns the event id. */
    private function eventWith(int $capacity, array $userIds): int
    {
        $eventId = DB::$events->add('Prio', '2099-05-01', $capacity);
        foreach ($userIds as $i => $userId) {
            DB::$events->register($eventId, $userId, '');
            DB::$events->setRegistrationTimestamp($eventId, $userId, sprintf('2099-01-01 10:00:%02d', $i));
        }
        return $eventId;
    }

    private function states(int $eventId): array
    {
        $out = [];
        foreach (DB::$events->registrations($eventId) as $r) {
            $out[] = [$r->name, $r->state];
        }
        return $out;
    }

    public function testPromotionByPriorityThenTimeWithNullLast(): void
    {
        Flavor::override(['waitlistOrder' => 'priority']);
        $first = $this->user('First', 5000);
        $none = $this->user('None', null);
        $late = $this->user('Late', 2000);
        $vip = $this->user('Vip', 1000);
        $eventId = $this->eventWith(1, [$first, $none, $late, $vip]);

        // waitlist shown in promotion order: Vip (1000), Late (2000), None (no priority)
        $this->assertSame([['First First', 1], ['Vip Vip', 2], ['Late Late', 2], ['None None', 2]], $this->states($eventId));

        DB::$events->unregister($eventId, $first);
        $this->assertSame(['Vip Vip', 1], $this->states($eventId)[0]);
    }

    public function testCapacityReductionDemotesLowestPriorityFirst(): void
    {
        Flavor::override(['waitlistOrder' => 'priority']);
        $a = $this->user('Aaa', 1000);
        $b = $this->user('Bbb', 3000);
        $c = $this->user('Ccc', 2000);
        $eventId = $this->eventWith(3, [$a, $b, $c]);

        DB::$events->update($eventId, 'Prio', 2);

        $waiting = array_values(array_filter($this->states($eventId), fn($s) => $s[1] === 2));
        $this->assertSame([['Bbb Bbb', 2]], $waiting);
    }

    public function testElFaroStaysFirstComeFirstServed(): void
    {
        $vip = $this->user('Vip', 1000);
        $first = $this->user('First', 5000);
        $second = $this->user('Second', 9000);
        $eventId = $this->eventWith(1, [$first, $second, $vip]);

        DB::$events->unregister($eventId, $first);

        $this->assertSame(['Second Second', 1], $this->states($eventId)[0]);
    }
}
