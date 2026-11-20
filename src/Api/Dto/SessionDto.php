<?php

declare(strict_types=1);

namespace TP\Api\Dto;

/** Current session: the logged-in user, or null when anonymous. */
final readonly class SessionDto
{
    public function __construct(
        public ?UserDto $user,
    ) {
    }
}
