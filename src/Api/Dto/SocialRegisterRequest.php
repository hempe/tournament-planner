<?php

declare(strict_types=1);

namespace TP\Api\Dto;

final readonly class SocialRegisterRequest
{
    public function __construct(
        public ?int $menuId,
        public ?int $tableId,
    ) {
    }
}
