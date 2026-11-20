<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Models\SocialMenu;

final readonly class MenuDto
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
    }

    public static function fromMenu(SocialMenu $m): self
    {
        return new self($m->id, $m->name);
    }
}
