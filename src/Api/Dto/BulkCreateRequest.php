<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Core\Attributes\ArrayOf;

final readonly class BulkCreateRequest
{
    /** @param BulkEventDto[] $events */
    public function __construct(
        #[ArrayOf(BulkEventDto::class)] public array $events,
    ) {
    }
}
