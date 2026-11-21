<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Models\User;

final readonly class AdminUserDto
{
    public function __construct(
        public int $id,
        public string $username,
        public ?string $firstName,
        public ?string $lastName,
        public string $displayName,
        public bool $male,
        public bool $isAdmin,
        public ?string $rfeg,
        public ?string $memberNumber,
    ) {
    }

    public static function fromUser(User $u): self
    {
        return new self(
            $u->id,
            $u->username,
            $u->firstName,
            $u->lastName,
            $u->displayName(),
            $u->male,
            $u->isAdmin,
            $u->rfeg,
            $u->memberNumber,
        );
    }
}
