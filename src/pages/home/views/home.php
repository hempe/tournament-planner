<?php

use GolfElFaro\Components\Page;
use GolfElFaro\Components\Calendar;

$date = new DateTime(isset($_GET['date']) ? $_GET['date'] : date('Y') . '-' . date('m') . '-1');
?>
<?= new Page(
    new Calendar(
        $date,
        DB::$events->all($date)
    )
);
