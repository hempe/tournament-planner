<?php

declare(strict_types=1);

namespace TP\Api\Dto;

/** Dates: `date` is YYYY-MM-DD, `registrationClose` is YYYY-MM-DDTHH:MM. */
final readonly class EventRequest
{
    public function __construct(
        public string $name,
        public string $date,
        public int $capacity,
        public bool $mixed,
        public ?string $description,
        public ?string $priceMembers,
        public ?string $priceGuests,
        public ?string $registrationClose,
    ) {
    }
}
