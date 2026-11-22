<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Core\Flavor;
use TP\Models\EventRegistration;

final readonly class ParticipantDto
{
    public function __construct(
        public string $name,
        public RegistrationState $state,
        public ?float $handicap,
    ) {
    }

    public static function fromRegistration(EventRegistration $r): self
    {
        return new self(
            $r->name,
            RegistrationState::fromDb($r->state),
            Flavor::hasUserField('handicap') ? $r->handicap : null,
        );
    }
}
