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
        /** Honeypot: hidden in the UI; anything but empty means a bot. */
        public ?string $website = null,
    ) {
    }
}
