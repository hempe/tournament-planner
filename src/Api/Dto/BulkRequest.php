<?php

declare(strict_types=1);

namespace TP\Api\Dto;

/** Recurring events: every `dayOfWeek` (0 = Sunday) between the two dates, inclusive. */
final readonly class BulkRequest
{
    public function __construct(
        public string $startDate,
        public string $endDate,
        public int $dayOfWeek,
        public string $name,
        public int $capacity,
        public bool $mixed,
        public ?string $description,
        public ?string $priceMembers,
        public ?string $priceGuests,
        public ?int $registrationCloseDays,
        public ?string $registrationCloseTime,
    ) {
    }
}
