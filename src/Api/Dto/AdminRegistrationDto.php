<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Models\EventRegistration;

final readonly class AdminRegistrationDto
{
    public function __construct(
        public int $userId,
        public string $name,
        public string $comment,
        public RegistrationState $state,
        public bool $male,
        public string $registeredAt,
        public ?string $rfeg,
        public ?string $memberNumber,
    ) {
    }

    public static function fromRegistration(EventRegistration $r): self
    {
        return new self(
            $r->userId,
            $r->name,
            $r->comment,
            RegistrationState::fromDb($r->state),
            $r->male,
            (string) ApiDate::dateTime($r->timestamp),
            $r->rfeg,
            $r->memberNumber,
        );
    }
}
