<?php

declare(strict_types=1);

namespace TP\Core;

final class ValidationError
{
    /** @param array<string, scalar> $params */
    public function __construct(
        public readonly string $field,
        public readonly string $message,
        public readonly string $key = '',
        public readonly array $params = [],
    ) {
    }
}
