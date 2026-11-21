<?php

declare(strict_types=1);

namespace TP\Api\Dto;

final readonly class BulkEventDto
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
