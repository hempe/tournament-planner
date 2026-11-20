<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Models\Event;

final readonly class PublicEventDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $date,
        public ?string $description,
        public ?string $priceMembers,
        public ?string $priceGuests,
        public ?string $registrationClose,
        public bool $isLocked,
    ) {
    }

    public static function fromEvent(Event $e): self
    {
        return new self(
            $e->id,
            $e->name,
            ApiDate::date($e->date),
            $e->description,
            $e->priceMembers,
            $e->priceGuests,
            ApiDate::dateTime($e->registrationClose),
            $e->isLocked,
        );
    }
}
