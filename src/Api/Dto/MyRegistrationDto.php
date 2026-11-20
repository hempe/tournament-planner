<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Models\EventRegistration;

final readonly class MyRegistrationDto
{
    public function __construct(
        public RegistrationState $state,
        public string $comment,
    ) {
    }

    public static function fromRegistration(EventRegistration $r): self
    {
        return new self(RegistrationState::fromDb($r->state), $r->comment);
    }
}
