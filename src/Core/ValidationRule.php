<?php

declare(strict_types=1);

namespace TP\Core;

final class ValidationRule
{
    public function __construct(
        public readonly string $field,
        public readonly array $rules,
        public readonly string $message = ''
    ) {
    }
}