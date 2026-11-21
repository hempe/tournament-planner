<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Models\SocialRegistration;

final readonly class AdminSocialRegistrationDto
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $email,
        public bool $isGuest,
        public ?string $menuName,
        public ?int $tableNumber,
    ) {
    }

    public static function fromRegistration(SocialRegistration $r): self
    {
        return new self($r->id, $r->displayName, $r->email, $r->userId === null, $r->menuName, $r->tableNumber);
    }
}
