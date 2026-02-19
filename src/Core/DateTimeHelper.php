<?php

namespace TP\Core;

class DateTimeHelper
{
    static function ago(string $timestamp): string
    {
        $timezone = new \DateTimeZone('Europe/Berlin');
        $current_time = new \DateTime('now', $timezone);
        $past_time = new \DateTime($timestamp, $timezone);
        $diff = $current_time->diff($past_time);

        $translator = Translator::getInstance();
        $parts = [];

        if ($diff->y > 0) {
            $parts[] = $translator->choice('time.years', $diff->y);
        }
        if ($diff->m > 0) {
            $parts[] = $translator->choice('time.months', $diff->m);
        }
        if ($diff->d > 0) {
            $parts[] = $translator->choice('time.days', $diff->d);
        }
        if ($diff->h > 0) {
            $parts[] = $translator->translate('time.hours', ['count' => $diff->h]);
        }
        if ($diff->i > 0) {
            $parts[] = $translator->translate('time.minutes', ['count' => $diff->i]);
        }

        if (empty($parts)) {
            return $translator->translate('time.just_now');
        }

        return implode(' ', $parts);
    }
}
