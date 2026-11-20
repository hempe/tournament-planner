<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Core\Attributes\ArrayOf;

final readonly class EventDetailDto
{
    public function __construct(
        public EventSummaryDto $event,
        public ?string $description,
        public ?string $priceMembers,
        public ?string $priceGuests,
        public ?string $registrationClose,
        public ?MyRegistrationDto $myRegistration,
        #[ArrayOf(ParticipantDto::class)] public array $participants,
        #[ArrayOf(GuestNameDto::class)] public array $guests,
        public ?SocialEventSummaryDto $socialEvent,
    ) {
    }
}
