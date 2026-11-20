<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Core\Attributes\ArrayOf;

final readonly class CalendarDto
{
    public function __construct(
        public string $month,
        #[ArrayOf(EventSummaryDto::class)] public array $events,
        #[ArrayOf(SocialEventSummaryDto::class)] public array $socialEvents,
    ) {
    }
}
