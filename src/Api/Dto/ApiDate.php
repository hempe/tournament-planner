<?php

declare(strict_types=1);

namespace TP\Api\Dto;

/** Dates go out as YYYY-MM-DD, date-times as local YYYY-MM-DDTHH:MM:SS (Europe/Zurich, no offset). */
final class ApiDate
{
    public static function date(string $value): string
    {
        return substr($value, 0, 10);
    }

    public static function dateTime(?string $value): ?string
    {
        return $value === null ? null : str_replace(' ', 'T', substr($value, 0, 19));
    }
}
