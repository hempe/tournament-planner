<?php

declare(strict_types=1);

namespace TP\Models;

final class SocialRegistration
{
    public readonly string $displayName;

    public function __construct(
        public readonly int $id,
        public readonly int $socialEventId,
        public readonly ?int $userId,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $email,
        public readonly ?int $tableId,
        public readonly ?int $tableNumber,
        public readonly int $menuId,
        public readonly string $menuName,
        public readonly string $timestamp,
    ) {
        $this->displayName = $userId !== null
            ? ($firstName !== null ? trim("$firstName $lastName") : '')
            : trim("$firstName $lastName");
    }
}
