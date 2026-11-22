<?php

declare(strict_types=1);

namespace TP\Api\Dto;

final readonly class SocialGuestRequest
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $email,
        public ?int $menuId,
        public ?int $tableId,
        /** Honeypot: hidden in the UI; anything but empty means a bot. */
        public ?string $website = null,
    ) {
    }
}
