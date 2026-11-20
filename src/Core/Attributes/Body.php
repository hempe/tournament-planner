<?php

declare(strict_types=1);

namespace TP\Core\Attributes;

use Attribute;

/** Request body DTO of an API route, used by the TypeScript generator. */
#[Attribute(Attribute::TARGET_METHOD)]
final class Body
{
    /** @param class-string $type */
    public function __construct(
        public readonly string $type,
    ) {
    }
}
