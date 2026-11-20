<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Models\EventGuest;

final readonly class GuestNameDto
{
    public function __construct(
        public string $firstName,
        public string $lastName,
    ) {
    }

    public static function fromGuest(EventGuest $g): self
    {
        return new self($g->firstName, $g->lastName);
    }
}
