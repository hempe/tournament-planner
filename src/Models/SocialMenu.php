<?php

declare(strict_types=1);

namespace TP\Models;

final class SocialMenu
{
    public function __construct(
        public readonly int $id,
        public readonly int $socialEventId,
        public readonly string $name,
    ) {}
}
