<?php

declare(strict_types=1);

namespace TP\Models;

final class SocialTable
{
    public readonly int $available;

    public function __construct(
        public readonly int $id,
        public readonly int $socialEventId,
        public readonly int $number,
        public readonly int $capacity,
        public readonly int $registered,
    ) {
        $this->available = $capacity - $registered;
    }
}
