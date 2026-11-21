<?php

declare(strict_types=1);

namespace TP\Api\Dto;

final readonly class BulkResultDto
{
    public function __construct(
        public int $created,
    ) {
    }
}
