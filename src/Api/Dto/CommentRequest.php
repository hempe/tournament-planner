<?php

declare(strict_types=1);

namespace TP\Api\Dto;

final readonly class CommentRequest
{
    public function __construct(
        public string $comment,
    ) {
    }
}
