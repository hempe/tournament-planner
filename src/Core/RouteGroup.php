<?php

declare(strict_types=1);

namespace TP\Core;

final class RouteGroup
{
    public function __construct(
        public readonly string $prefix,
        public readonly array $middleware
    ) {}
}
