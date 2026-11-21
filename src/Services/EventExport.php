<?php

declare(strict_types=1);

namespace TP\Services;

use DateTime;
use TP\Core\Response;
use TP\Models\DB;
use TP\Models\Event;

/** CSV export of an event's participants (members and guests). */
final class EventExport
{
    public static function csvResponse(Event $event): Response
    {
        $registrations = DB::$events->registrations($event->id);
        $guests = DB::$guests->allForEvent($event->id);

        $csvField = fn(string $s): string => '"' . str_replace('"', '""', $s) . '"';
        $isoDate = fn(string $ts): string => (new DateTime($ts))->format('Y-m-d\TH:i:s');
        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $event->date . '_' . $event->name) . '.csv';

        $rows = [];
        $rows[] = implode(',', [
            $csvField(__('events.export_col_name')),
            $csvField(__('events.export_col_gender')),
            $csvField(__('events.export_col_is_guest')),
            $csvField(__('users.member_number')),
            $csvField(__('users.rfeg')),
            $csvField(__('events.export_col_timestamp')),
        ]);

        foreach ($registrations as $reg) {
            $rows[] = implode(',', [
                $csvField($reg->name),
                $csvField($reg->male ? __('users.mr') : __('users.mrs')),
                $csvField(__('events.export_no')),
                $csvField($reg->memberNumber ?? ''),
                $csvField($reg->rfeg ?? ''),
                $csvField($isoDate($reg->timestamp)),
            ]);
        }

        foreach ($guests as $guest) {
            $rows[] = implode(',', [
                $csvField($guest->firstName . ' ' . $guest->lastName),
                $csvField($guest->male ? __('users.mr') : __('users.mrs')),
                $csvField(__('events.export_yes')),
                $csvField(''),
                $csvField(''),
                $csvField($isoDate($guest->timestamp)),
            ]);
        }

        return Response::ok("\xEF\xBB\xBF" . implode("\r\n", $rows), [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
