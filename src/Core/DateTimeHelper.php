<?php
namespace TP\Core;

class DateTimeHelper
{
    static function ago(string $timestamp): string
    {
        // Set the default timezone (use your system's or MySQL's timezone)
        $timezone = new \DateTimeZone('Europe/Berlin'); // Or any timezone you need

        // Create DateTime objects with the same timezone
        $current_time = new \DateTime('now', $timezone); // Current time
        $past_time = new \DateTime($timestamp, $timezone); // Past timestamp

        // Calculate the difference
        $diff = $current_time->diff($past_time);

        // Initialize an array for the result
        $relative_time = [];

        if ($diff->y > 0) {
            $relative_time[] = $diff->y . ' Jahr' . ($diff->y > 1 ? 'e' : '');
        }
        if ($diff->m > 0) {
            $relative_time[] = $diff->m . ' Monat' . ($diff->m > 1 ? 'e' : '');
        }
        if ($diff->d > 0) {
            $relative_time[] = $diff->d . ' Tag' . ($diff->d > 1 ? 'e' : '');
        }
        if ($diff->h > 0) {
            $relative_time[] = $diff->h . ' Std.';
        }
        if ($diff->i > 0) {
            $relative_time[] = $diff->i . ' Min.';
        }

        if (empty($relative_time)) {
            return "Gerade eben";
        } else {
            return implode(' ', $relative_time);
        }
    }
}
