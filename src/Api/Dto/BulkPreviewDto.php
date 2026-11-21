<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Core\Attributes\ArrayOf;

final readonly class BulkPreviewDto
{
    /** @param BulkEventDto[] $events */
    public function __construct(
        #[ArrayOf(BulkEventDto::class)] public array $events,
    ) {
    }
}
