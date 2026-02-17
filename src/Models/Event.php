<?php

declare(strict_types=1);

namespace TP\Models;

final class Event
{
    public readonly int $available;

    public function __construct(
        public readonly int $id,
        public readonly bool $locked,
        public readonly bool $isLocked,
        public readonly string $date,
        public readonly string $name,
        public readonly int $capacity,
        public readonly int $joined,
        public readonly int $onWaitList,
        public readonly int $userState
    ) {
        $this->available = $capacity - $joined;
    }

}