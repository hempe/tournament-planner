<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Models\User;

final readonly class UserDto
{
    public function __construct(
        public int $id,
        public string $username,
        public ?string $firstName,
        public ?string $lastName,
        public string $displayName,
        public bool $isAdmin,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            $user->id,
            $user->username,
            $user->firstName,
            $user->lastName,
            $user->displayName(),
            $user->isAdmin,
        );
    }
}
