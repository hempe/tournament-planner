<?php

declare(strict_types=1);

namespace TP\Core\Attributes;

use Attribute;

/** Marks an API route that returns a non-JSON body (e.g. a file download); the TypeScript generator skips it. */
#[Attribute(Attribute::TARGET_METHOD)]
final class RawResponse
{
}
