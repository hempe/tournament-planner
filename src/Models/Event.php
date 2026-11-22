<?php

declare(strict_types=1);

namespace TP\Models;

final class Event
{
    public readonly int $available;

    public function __construct(
        public readonly int $id,
        public readonly bool $isLocked,
        public readonly string $date,
        public readonly string $name,
        public readonly int $capacity,
        public readonly int $joined,
        public readonly int $onWaitList,
        public readonly int $userState,
        public readonly bool $mixed = true,
        public readonly ?string $description = null,
        public readonly ?string $priceMembers = null,
        public readonly ?string $priceGuests = null,
        public readonly ?string $registrationClose = null,
        /** Locked by an admin (isLocked is also true when the date or deadline passed). */
        public readonly bool $manuallyLocked = false,
    ) {
        $this->available = $capacity - $joined;
    }

}