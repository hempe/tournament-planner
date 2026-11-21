<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Core\Attributes\ArrayOf;

final readonly class SocialEventAdminDto
{
    /**
     * @param MenuDto[] $menuOptions
     * @param TableDto[] $tableOptions
     * @param AdminSocialRegistrationDto[] $registrations
     */
    public function __construct(
        public SocialEventSummaryDto $event,
        public ?string $description,
        public ?string $registrationClose,
        public string $menus,
        public string $tables,
        #[ArrayOf(MenuDto::class)] public array $menuOptions,
        #[ArrayOf(TableDto::class)] public array $tableOptions,
        #[ArrayOf(AdminSocialRegistrationDto::class)] public array $registrations,
    ) {
    }
}
