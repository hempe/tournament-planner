<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Models;

use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class GuestRepositoryTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    public function testGuestsAreDeletedWhenEventIsDeleted(): void
    {
        $eventId = DB::$events->add('Test Event', '2099-03-15', 20);
        DB::$guests->add($eventId, true, 'Hans', 'Müller', 'hans@example.com', 18.0, null);

        DB::$events->delete($eventId);

        $this->assertCount(0, DB::$guests->allForEvent($eventId));
    }

    public function testAllForEventReturnsOnlyGuestsForThatEvent(): void
    {
        $eventId1 = DB::$events->add('Event 1', '2099-03-15', 20);
        $eventId2 = DB::$events->add('Event 2', '2099-03-16', 20);
        DB::$guests->add($eventId1, true, 'Hans', 'Müller', 'hans@example.com', 18.0, null);
        DB::$guests->add($eventId2, false, 'Anna', 'Schmidt', 'anna@example.com', 12.0, null);

        $guests1 = DB::$guests->allForEvent($eventId1);
        $guests2 = DB::$guests->allForEvent($eventId2);

        $this->assertCount(1, $guests1);
        $this->assertSame('Hans', $guests1[0]->firstName);
        $this->assertCount(1, $guests2);
        $this->assertSame('Anna', $guests2[0]->firstName);
    }
}
