<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Core\Attributes\ArrayOf;

final readonly class SocialEventDetailDto
{
    public function __construct(
        public SocialEventSummaryDto $event,
        public ?string $description,
        public ?string $registrationClose,
        #[ArrayOf(MenuDto::class)] public array $menus,
        #[ArrayOf(TableDto::class)] public array $tables,
        public ?SocialRegistrationDto $myRegistration,
        #[ArrayOf(SocialParticipantDto::class)] public array $participants,
    ) {
    }
}
