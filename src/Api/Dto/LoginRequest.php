<?php

declare(strict_types=1);

namespace TP\Api\Dto;

final readonly class LoginRequest
{
    public function __construct(
        public string $username,
        public string $password,
    ) {
    }
}
