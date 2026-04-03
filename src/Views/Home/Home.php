<?php

use TP\Components\Calendar;
use TP\Components\CalendarEvent;
use TP\Components\Page;
use TP\Components\SocialCalendarEvent;
use TP\Models\DB;
use TP\Models\Event;
use TP\Models\SocialEvent;

$date = new DateTime(isset($_GET['date']) ? $_GET['date'] : date('Y') . '-' . date('m') . '-1');

$events = array_merge(
    DB::$events->all($date),
    DB::$socialEvents->all($date)
);

usort($events, fn($a, $b) => strcmp($a->date, $b->date));

?>
<?= new Page(
    new Calendar(
        $date,
        $events,
        fn($event) => $event instanceof SocialEvent
            ? new SocialCalendarEvent($event)
            : new CalendarEvent($event)
    )
);
