<?php

use TP\Components\Page;
use TP\Components\Calendar;

$date = new DateTime(isset($_GET['date']) ? $_GET['date'] : date('Y') . '-' . date('m') . '-1');
assert(is_array($events));
assert($eventRenderer instanceof \Closure);
?>
<?= new Page(
    new Calendar(
        $date,
        $events,
        $eventRenderer
    )
);
