<?php

declare(strict_types=1);

namespace TP\Models;

final class EventGuest
{
    public function __construct(
        public readonly int $id,
        public readonly int $eventId,
        public readonly bool $male,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $email,
        public readonly float $handicap,
        public readonly ?string $rfeg,
        public readonly ?string $comment,
        public readonly string $timestamp,
    ) {}
}
