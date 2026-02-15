<?php

declare(strict_types=1);

namespace TP\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Middleware
{
    /**
     * @param class-string $middlewareClass
     */
    public function __construct(
        public readonly string $middlewareClass
    ) {
    }
}
