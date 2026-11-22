<?php

declare(strict_types=1);

namespace TP\Api\Dto;

final readonly class UserCreateRequest
{
    public function __construct(
        /** Ignored (and optional) when the flavour has no gender split. */
        public ?bool $male,
        public string $username,
        public string $password,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $rfeg,
        public ?string $memberNumber,
        public ?string $phone,
        public ?float $handicap,
        public ?int $priority,
    ) {
    }
}
