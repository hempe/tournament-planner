<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Models\SocialRegistration;

final readonly class SocialParticipantDto
{
    public function __construct(
        public string $name,
        public ?int $tableNumber,
    ) {
    }

    public static function fromRegistration(SocialRegistration $r): self
    {
        return new self($r->displayName, $r->tableNumber);
    }
}
