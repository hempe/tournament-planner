<?php

declare(strict_types=1);

namespace TP\Api\Dto;

final readonly class UserCreateRequest
{
    public function __construct(
        public bool $male,
        public string $username,
        public string $password,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $rfeg,
        public ?string $memberNumber,
    ) {
    }
}
