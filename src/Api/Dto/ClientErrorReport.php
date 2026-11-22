<?php

declare(strict_types=1);

namespace TP\Api\Dto;

final readonly class ClientErrorReport
{
    public function __construct(
        public string $message,
        public ?string $stack,
        public string $url,
        public string $appVersion,
    ) {
    }
}
