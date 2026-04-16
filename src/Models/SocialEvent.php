<?php

declare(strict_types=1);

namespace TP\Models;

final class SocialEvent
{
    public readonly ?int $available;

    public function __construct(
        public readonly int $id,
        public readonly ?int $tournamentId,
        public readonly string $name,
        public readonly string $date,
        public readonly ?string $description,
        public readonly ?string $registrationClose,
        public readonly bool $isLocked,
        public readonly ?int $totalCapacity,
        public readonly int $registered,
        public readonly int $userRegistered, // 1 if current user is registered, 0 if not
    ) {
        $this->available = $totalCapacity !== null ? $totalCapacity - $registered : null;
    }
}
