<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Core\Flavor;
use TP\Models\User;

final readonly class AdminUserDto
{
    public function __construct(
        public int $id,
        public string $username,
        public ?string $firstName,
        public ?string $lastName,
        public string $displayName,
        public bool $male,
        public bool $isAdmin,
        public ?string $rfeg,
        public ?string $memberNumber,
        public ?string $phone,
        public ?float $handicap,
        public ?int $priority,
    ) {
    }

    public static function fromUser(User $u): self
    {
        return new self(
            $u->id,
            $u->username,
            $u->firstName,
            $u->lastName,
            $u->displayName(),
            $u->male,
            $u->isAdmin,
            // Fields the flavour doesn't use are never sent
            Flavor::hasUserField('rfeg') ? $u->rfeg : null,
            Flavor::hasUserField('memberNumber') ? $u->memberNumber : null,
            Flavor::hasUserField('phone') ? $u->phone : null,
            Flavor::hasUserField('handicap') ? $u->handicap : null,
            Flavor::hasUserField('priority') ? $u->priority : null,
        );
    }
}
