<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Models\EventGuest;

final readonly class AdminGuestDto
{
    public function __construct(
        public int $id,
        public bool $male,
        public string $firstName,
        public string $lastName,
        public ?string $email,
        public ?float $handicap,
        public ?string $comment,
        public string $registeredAt,
    ) {
    }

    public static function fromGuest(EventGuest $g): self
    {
        return new self(
            $g->id,
            $g->male,
            $g->firstName,
            $g->lastName,
            $g->email,
            $g->handicap,
            $g->comment,
            (string) ApiDate::dateTime($g->timestamp),
        );
    }
}
