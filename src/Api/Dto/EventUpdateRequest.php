<?php

declare(strict_types=1);

namespace TP\Api\Dto;

/** The event date cannot be changed after creation. */
final readonly class EventUpdateRequest
{
    public function __construct(
        public string $name,
        public int $capacity,
        public bool $mixed,
        public ?string $description,
        public ?string $priceMembers,
        public ?string $priceGuests,
        public ?string $registrationClose,
    ) {
    }
}
