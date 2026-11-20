<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Models\SocialTable;

final readonly class TableDto
{
    public function __construct(
        public int $id,
        public int $number,
        public int $capacity,
        public int $available,
    ) {
    }

    public static function fromTable(SocialTable $t): self
    {
        return new self($t->id, $t->number, $t->capacity, $t->available);
    }
}
