<?php

declare(strict_types=1);

namespace TP\Models;

use TP\Core\DateTimeHelper;

final class EventRegistration
{
    public readonly string $ago;

    public function __construct(
        public readonly int $userId,
        public readonly string $name,
        public readonly string $comment,
        public readonly string $timestamp,
        public readonly int $state,
    ) {
        $this->ago = DateTimeHelper::ago($timestamp);
    }

}