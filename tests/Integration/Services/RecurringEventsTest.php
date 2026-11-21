<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Services;

use PHPUnit\Framework\TestCase;
use TP\Api\Dto\BulkEventDto;
use TP\Services\RecurringEvents;

class RecurringEventsTest extends TestCase
{
    public function testFirstOccurrenceIsOnOrAfterStartDate(): void
    {
        // 2099-06-01 is a Monday; ask for Wednesdays (3)
        $events = RecurringEvents::plan('2099-06-01', '2099-06-30', 3, 'Cup', 20);

        $this->assertSame('2099-06-03', $events[0]->date);
        $this->assertSame(['2099-06-03', '2099-06-10', '2099-06-17', '2099-06-24'], array_map(fn($e) => $e->date, $events));
    }

    public function testStartDateItselfIsIncludedWhenItMatches(): void
    {
        $events = RecurringEvents::plan('2099-06-01', '2099-06-30', 1, 'Cup', 20);

        $this->assertCount(5, $events);
        $this->assertSame('2099-06-01', $events[0]->date);
        $this->assertSame('2099-06-29', $events[4]->date);
    }

    public function testEndDateIsInclusive(): void
    {
        $events = RecurringEvents::plan('2099-06-01', '2099-06-15', 1, 'Cup', 20);

        $this->assertSame(['2099-06-01', '2099-06-08', '2099-06-15'], array_map(fn($e) => $e->date, $events));
    }

    public function testNoEventsWhenNoMatchingDayInRange(): void
    {
        $this->assertSame([], RecurringEvents::plan('2099-06-01', '2099-06-02', 5, 'Cup', 20));
    }

    public function testRegistrationCloseIsComputedFromDaysAndTime(): void
    {
        $events = RecurringEvents::plan('2099-06-01', '2099-06-08', 1, 'Cup', 20, true, null, null, null, 2, '18:30');

        $this->assertSame('2099-05-30 18:30:00', $events[0]->registrationClose);
        $this->assertSame('2099-06-06 18:30:00', $events[1]->registrationClose);
    }

    public function testRegistrationCloseIsNullUnlessDaysAndTimeAreBothGiven(): void
    {
        $this->assertNull(RecurringEvents::plan('2099-06-01', '2099-06-01', 1, 'Cup', 20, true, null, null, null, 2, null)[0]->registrationClose);
        $this->assertNull(RecurringEvents::plan('2099-06-01', '2099-06-01', 1, 'Cup', 20, true, null, null, null, null, '18:30')[0]->registrationClose);
    }

    public function testOtherFieldsAreCopiedToEveryEvent(): void
    {
        $event = RecurringEvents::plan('2099-06-01', '2099-06-01', 1, 'Cup', 20, false, 'Desc', 'CHF 50', 'CHF 80')[0];

        $this->assertEquals(new BulkEventDto('Cup', '2099-06-01', 20, false, 'Desc', 'CHF 50', 'CHF 80', null), $event);
    }
}
