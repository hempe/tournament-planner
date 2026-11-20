<?php

declare(strict_types=1);

namespace TP\Core\Attributes;

use Attribute;

/** Element type of an array property on a DTO (PHP has no generics). */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class ArrayOf
{
    /** @param class-string|'string'|'int'|'float'|'bool' $type */
    public function __construct(
        public readonly string $type,
    ) {
    }
}
