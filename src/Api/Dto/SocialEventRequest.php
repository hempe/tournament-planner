<?php

declare(strict_types=1);

namespace TP\Api\Dto;

/** Dates: `date` is YYYY-MM-DD, `registrationClose` is YYYY-MM-DDTHH:MM. `menus` and `tables` are comma-separated. */
final readonly class SocialEventRequest
{
    public function __construct(
        public string $name,
        public string $date,
        public ?int $tournamentId,
        public ?string $description,
        public ?string $registrationClose,
        public string $menus,
        public string $tables,
    ) {
    }
}
