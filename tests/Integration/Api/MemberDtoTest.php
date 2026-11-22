<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use PHPUnit\Framework\TestCase;
use TP\Api\Dto\ApiDate;
use TP\Api\Dto\EventSummaryDto;
use TP\Api\Dto\RegistrationState;
use TP\Api\Dto\SocialEventSummaryDto;
use TP\Api\Dto\TableDto;
use TP\Models\Event;
use TP\Models\SocialEvent;
use TP\Models\SocialTable;

class MemberDtoTest extends TestCase
{
    public function testRegistrationStateFromDatabaseInt(): void
    {
        $this->assertSame(RegistrationState::None, RegistrationState::fromDb(0));
        $this->assertSame(RegistrationState::Registered, RegistrationState::fromDb(1));
        $this->assertSame(RegistrationState::Waitlist, RegistrationState::fromDb(2));
        $this->assertSame('waitlist', RegistrationState::Waitlist->value);
    }

    public function testApiDate(): void
    {
        $this->assertSame('2099-05-01T18:00:00', ApiDate::dateTime('2099-05-01 18:00:00'));
        $this->assertNull(ApiDate::dateTime(null));
        $this->assertSame('2099-05-01', ApiDate::date('2099-05-01 00:00:00'));
        $this->assertSame('2099-05-01', ApiDate::date('2099-05-01'));
    }

    public function testEventSummaryFromEvent(): void
    {
        $event = new Event(5, false, '2099-06-01', 'Cup', 20, 18, 3, 2, true, 'desc', 'CHF 50', null, '2099-05-30 12:00:00');

        $dto = EventSummaryDto::fromEvent($event);

        $this->assertSame(
            [
                'id' => 5, 'name' => 'Cup', 'date' => '2099-06-01', 'capacity' => 20, 'joined' => 18,
                'waitlist' => 3, 'available' => 2, 'isLocked' => false, 'userState' => RegistrationState::Waitlist,
                'manuallyLocked' => false,
            ],
            get_object_vars($dto)
        );
    }

    public function testSocialEventSummaryFromSocialEvent(): void
    {
        $social = new SocialEvent(3, 5, 'Dinner', '2099-06-01', null, null, false, 10, 4, 1);

        $dto = SocialEventSummaryDto::fromSocialEvent($social);

        $this->assertSame(3, $dto->id);
        $this->assertSame(5, $dto->tournamentId);
        $this->assertSame(6, $dto->available);
        $this->assertTrue($dto->userRegistered);
    }

    public function testTableDtoFromSocialTable(): void
    {
        $dto = TableDto::fromTable(new SocialTable(9, 3, 2, 6, 4));

        $this->assertSame(['id' => 9, 'number' => 2, 'capacity' => 6, 'available' => 2], get_object_vars($dto));
    }
}
