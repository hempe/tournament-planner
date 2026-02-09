<?php

declare(strict_types=1);

namespace GolfElFaro\Models;

use GolfElFaro\Core\DateTimeHelper;

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

    public function isConfirmed(): bool
    {
        return $this->state === 1;
    }

    public function isOnWaitList(): bool
    {
        return $this->state === 2;
    }

    public function getStateText(): string
    {
        return match ($this->state) {
            1 => __('events.confirmed'),
            2 => __('events.waitlist'),
            default => __('events.unknown_state'),
        };
    }
}