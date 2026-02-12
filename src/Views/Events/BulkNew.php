<?php

use TP\Components\Color;
use TP\Components\Page;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\IconButton;
use TP\Components\Form;

?>
<?= new Page(function () {
    return new Form(
        action: "/events/bulk/preview",
        content: new Card(
            "Mehrfache Termine erstellen",
            new Table(
                columns: ['', ''],
                items: [null, null, null, null, null],
                projection: fn($_, $index) => match($index) {
                    0 => [
                        '<label for="start_date">Startdatum</label>',
                        '<input type="date" id="start_date" name="start_date" class="input" required>'
                    ],
                    1 => [
                        '<label for="end_date">Enddatum</label>',
                        '<input type="date" id="end_date" name="end_date" class="input" required>'
                    ],
                    2 => [
                        '<label for="day_of_week">Wochentag</label>',
                        '<select id="day_of_week" name="day_of_week" class="input" required>' .
                        '<option value="">Bitte wählen</option>' .
                        '<option value="1">' . __('calendar.weekdays.monday') . '</option>' .
                        '<option value="2">' . __('calendar.weekdays.tuesday') . '</option>' .
                        '<option value="3">' . __('calendar.weekdays.wednesday') . '</option>' .
                        '<option value="4">' . __('calendar.weekdays.thursday') . '</option>' .
                        '<option value="5">' . __('calendar.weekdays.friday') . '</option>' .
                        '<option value="6">' . __('calendar.weekdays.saturday') . '</option>' .
                        '<option value="0">' . __('calendar.weekdays.sunday') . '</option>' .
                        '</select>'
                    ],
                    3 => [
                        '<label for="name">Name</label>',
                        '<input type="text" id="name" name="name" class="input" maxlength="255" placeholder="Name" required>'
                    ],
                    4 => [
                        '<label for="capacity">Max. Teilnehmer</label>',
                        '<input type="number" id="capacity" name="capacity" class="input" min="1" placeholder="Max. Teilnehmer" required>'
                    ],
                },
                footer: new IconButton(
                    title: 'Vorschau anzeigen',
                    type: 'submit',
                    icon: 'fa-eye',
                    color: Color::Primary,
                )
            )
        )
    );
});
