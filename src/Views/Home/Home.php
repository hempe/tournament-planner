<?php

use TP\Components\Page;
use TP\Components\Calendar;
use TP\Models\DB;

$date = new DateTime(isset($_GET['date']) ? $_GET['date'] : date('Y') . '-' . date('m') . '-1');
?>
<?= new Page(
    new Calendar(
        $date,
        DB::$events->all($date)
    )
);
