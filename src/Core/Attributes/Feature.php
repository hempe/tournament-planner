<?php

declare(strict_types=1);

namespace TP\Core\Attributes;

use Attribute;

/** Route only exists when the flavour switch is on (see TP\Core\Flavor); otherwise 404. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Feature
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
