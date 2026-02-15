<?php

declare(strict_types=1);

namespace TP\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class RoutePrefix
{
    public function __construct(
        public readonly string $prefix
    ) {
    }
}
