<?php

declare(strict_types=1);

namespace TP\Api\Dto;

enum RegistrationState: string
{
    case None = 'none';
    case Registered = 'registered';
    case Waitlist = 'waitlist';

    /** event_users.state: 0 = not registered, 1 = registered, 2 = waitlist */
    public static function fromDb(int $state): self
    {
        return match ($state) {
            1 => self::Registered,
            2 => self::Waitlist,
            default => self::None,
        };
    }
}
