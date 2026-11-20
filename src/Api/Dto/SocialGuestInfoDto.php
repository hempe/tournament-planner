<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Core\Attributes\ArrayOf;

final readonly class SocialGuestInfoDto
{
    public function __construct(
        public SocialEventSummaryDto $event,
        public ?string $description,
        #[ArrayOf(MenuDto::class)] public array $menus,
        #[ArrayOf(TableDto::class)] public array $tables,
    ) {
    }
}
