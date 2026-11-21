<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Models\User;

final readonly class UserOptionDto
{
    public function __construct(
        public int $id,
        public string $displayName,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self($user->id, $user->displayName());
    }
}
