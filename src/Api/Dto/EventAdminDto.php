<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Core\Attributes\ArrayOf;

final readonly class EventAdminDto
{
    /**
     * @param AdminRegistrationDto[] $registrations
     * @param AdminGuestDto[] $guests
     * @param UserOptionDto[] $availableUsers
     */
    public function __construct(
        public EventSummaryDto $event,
        public bool $mixed,
        public ?string $description,
        public ?string $priceMembers,
        public ?string $priceGuests,
        public ?string $registrationClose,
        #[ArrayOf(AdminRegistrationDto::class)] public array $registrations,
        #[ArrayOf(AdminGuestDto::class)] public array $guests,
        #[ArrayOf(UserOptionDto::class)] public array $availableUsers,
        public ?SocialEventSummaryDto $socialEvent,
    ) {
    }
}
