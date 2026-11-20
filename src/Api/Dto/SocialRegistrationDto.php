<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Models\SocialRegistration;

final readonly class SocialRegistrationDto
{
    public function __construct(
        public ?string $menuName,
        public ?int $tableNumber,
    ) {
    }

    public static function fromRegistration(SocialRegistration $r): self
    {
        return new self($r->menuName, $r->tableNumber);
    }
}
