<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Models\SocialEvent;

final readonly class SocialEventSummaryDto
{
    public function __construct(
        public int $id,
        public ?int $tournamentId,
        public string $name,
        public string $date,
        public bool $isLocked,
        public ?int $totalCapacity,
        public int $registered,
        public ?int $available,
        public bool $userRegistered,
    ) {
    }

    public static function fromSocialEvent(SocialEvent $s): self
    {
        return new self(
            $s->id,
            $s->tournamentId,
            $s->name,
            ApiDate::date($s->date),
            $s->isLocked,
            $s->totalCapacity,
            $s->registered,
            $s->available,
            $s->userRegistered === 1,
        );
    }
}
