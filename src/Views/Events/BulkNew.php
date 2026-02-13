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
            __('events.bulk_create'),
            new Table(
                columns: ['', '', ''],
                items: [0, 1, 2, 3, 4],
                projection: fn($index) => match($index) {
                    0 => [
                        '<label for="start_date">' . __('events.bulk_start_date') . '</label>',
                        '<input type="date" id="start_date" name="start_date" class="input" required>',
                        ''
                    ],
                    1 => [
                        '<label for="end_date">' . __('events.bulk_end_date') . '</label>',
                        '<input type="date" id="end_date" name="end_date" class="input" required>',
                        ''
                    ],
                    2 => [
                        '<label for="day_of_week">' . __('events.bulk_day_of_week') . '</label>',
                        '<select id="day_of_week" name="day_of_week" required>' .
                        '<option value="">' . __('events.bulk_select_day') . '</option>' .
                        '<option value="1">' . __('calendar.weekdays.monday') . '</option>' .
                        '<option value="2">' . __('calendar.weekdays.tuesday') . '</option>' .
                        '<option value="3">' . __('calendar.weekdays.wednesday') . '</option>' .
                        '<option value="4">' . __('calendar.weekdays.thursday') . '</option>' .
                        '<option value="5">' . __('calendar.weekdays.friday') . '</option>' .
                        '<option value="6">' . __('calendar.weekdays.saturday') . '</option>' .
                        '<option value="0">' . __('calendar.weekdays.sunday') . '</option>' .
                        '</select>',
                        ''
                    ],
                    3 => [
                        '<label for="name">' . __('events.name') . '</label>',
                        '<input type="text" id="name" name="name" class="input" maxlength="255" placeholder="' . __('events.name') . '" required>',
                        ''
                    ],
                    4 => [
                        '<label for="capacity">' . __('events.max_participants') . '</label>',
                        '<input type="number" id="capacity" name="capacity" class="input" min="1" placeholder="' . __('events.max_participants') . '" required>',
                        new IconButton(
                            title: __('events.bulk_preview'),
                            type: 'submit',
                            icon: 'fa-eye',
                            color: Color::Primary,
                        )
                    ],
                }
            )
        )
    );
});
