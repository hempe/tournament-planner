<?php

declare(strict_types=1);

namespace TP\Models;
use TP\Core\DateTimeHelper;

final class EventGuest
{
    public readonly string $ago;

    public function __construct(
        public readonly int $id,
        public readonly int $eventId,
        public readonly bool $male,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $email,
        public readonly ?float $handicap,
        public readonly ?string $rfeg,
        public readonly ?string $comment,
        public readonly string $timestamp,
    ) {
        $this->ago = DateTimeHelper::ago($timestamp);
    }
}
