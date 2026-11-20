<?php

declare(strict_types=1);

namespace TP\Core\Attributes;

use Attribute;

/**
 * Response type of an API route, used by the TypeScript generator.
 * No #[Returns] means the route answers 204 No Content.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Returns
{
    /** @param class-string|'string'|'int'|'float'|'bool' $type */
    public function __construct(
        public readonly string $type,
        public readonly bool $list = false,
        public readonly bool $nullable = false,
    ) {
    }
}
