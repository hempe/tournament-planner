<?php

use TP\Components\Color;
use TP\Components\Page;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\IconActionButton;
use TP\Models\DB;

?>
<?= new Page(new Card(
    'Anlässe',
    new Table(
        ['Datum', 'Name', 'Max. Teilnehmer', 'Angemeldet', 'Warteliste', ''],
        DB::$events->all(),
        fn($event) => [
            $event->date,
            $event->name,
            $event->capacity,
            $event->joined,
            $event->onWaitList,
            new IconActionButton(
                actionUrl: "/events/{$event->id}/delete",
                title: 'Löschen',
                color: Color::Accent,
                icon: 'fa-trash',
                confirmMessage: "Termin ($event->name) definitiv löschen?"
            )
        ],
        fn($event) => "window.location.href='/events/{$event->id}'",
        widths: [null, null, null, null, null, 1]
    )
)) ?>