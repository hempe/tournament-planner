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

    public function isUserRegistered(): bool
    {
        return $this->userState === 1;
    }

    public function isUserOnWaitList(): bool
    {
        return $this->userState === 2;
    }

    public function hasAvailableSpots(): bool
    {
        return $this->available > 0;
    }

    public function isFull(): bool
    {
        return $this->available <= 0;
    }

    public function canUserRegister(int $userId): bool
    {
        if ($this->isLocked) {
            return false;
        }

        if (User::admin()) {
            return true;
        }

        return User::canEdit($userId) && $this->userState === 0;
    }

    public function canUserUnregister(int $userId): bool
    {
        if ($this->isLocked) {
            return false;
        }

        return User::canEdit($userId) && $this->userState > 0;
    }
}