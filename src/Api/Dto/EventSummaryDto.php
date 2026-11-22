<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Models\Event;

final readonly class EventSummaryDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $date,
        public int $capacity,
        public int $joined,
        public int $waitlist,
        public int $available,
        public bool $isLocked,
        public RegistrationState $userState,
        /** Locked by an admin; isLocked also covers a passed date or registration deadline. */
        public bool $manuallyLocked,
    ) {
    }

    public static function fromEvent(Event $event): self
    {
        return new self(
            $event->id,
            $event->name,
            ApiDate::date($event->date),
            $event->capacity,
            $event->joined,
            $event->onWaitList,
            $event->available,
            $event->isLocked,
            RegistrationState::fromDb($event->userState),
            $event->manuallyLocked,
        );
    }
}
