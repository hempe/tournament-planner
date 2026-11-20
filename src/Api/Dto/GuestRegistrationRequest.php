<?php

declare(strict_types=1);

namespace TP\Api\Dto;

final readonly class GuestRegistrationRequest
{
    public function __construct(
        public bool $male,
        public string $firstName,
        public string $lastName,
        public ?string $email,
        public ?string $handicap,
        public ?string $comment,
    ) {
    }
}
