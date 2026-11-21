<?php

declare(strict_types=1);

namespace TP\Services;

use DateTime;
use TP\Api\Dto\BulkEventDto;

/** Computes the dates of a weekly recurring series of events (bulk creation). */
final class RecurringEvents
{
    /**
     * One event per `$dayOfWeek` (0 = Sunday … 6 = Saturday) from the first such day on/after
     * `$startDate` up to and including `$endDate`. When both `$registrationCloseDays` and
     * `$registrationCloseTime` (HH:MM) are given, registration closes that many days before
     * the event, formatted as `YYYY-MM-DD HH:MM:00`.
     *
     * @return BulkEventDto[]
     */
    public static function plan(
        string $startDate,
        string $endDate,
        int $dayOfWeek,
        string $name,
        int $capacity,
        bool $mixed = true,
        ?string $description = null,
        ?string $priceMembers = null,
        ?string $priceGuests = null,
        ?int $registrationCloseDays = null,
        ?string $registrationCloseTime = null,
    ): array {
        $end = new DateTime($endDate);
        $events = [];

        // Find first occurrence of target day-of-week on/after start date
        $current = new DateTime($startDate);
        $currentDayOfWeek = (int) $current->format('w');
        if ($currentDayOfWeek !== $dayOfWeek) {
            $daysToAdd = ($dayOfWeek - $currentDayOfWeek + 7) % 7;
            $current->modify("+{$daysToAdd} days");
        }

        while ($current <= $end) {
            $registrationClose = null;
            if ($registrationCloseDays !== null && $registrationCloseTime !== null) {
                $closeDate = clone $current;
                $closeDate->modify("-{$registrationCloseDays} days");
                $registrationClose = $closeDate->format('Y-m-d') . ' ' . $registrationCloseTime . ':00';
            }

            $events[] = new BulkEventDto(
                $name,
                $current->format('Y-m-d'),
                $capacity,
                $mixed,
                $description,
                $priceMembers,
                $priceGuests,
                $registrationClose,
            );
            $current->modify('+7 days');
        }

        return $events;
    }
}
