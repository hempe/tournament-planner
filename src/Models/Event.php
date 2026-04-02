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
        public readonly ?float $priceMembers = null,
        public readonly ?float $priceGuests = null,
        public readonly ?string $registrationClose = null,
    ) {
        $this->available = $capacity - $joined;
    }

}