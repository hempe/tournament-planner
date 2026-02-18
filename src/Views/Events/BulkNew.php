<?php

use TP\Components\Color;
use TP\Components\Input;
use TP\Components\Page;
use TP\Components\Select;
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
                items: [0, 1, 2, 3, 4, 5],
                projection: fn($index) => match($index) {
                    0 => [
                        '<label for="start_date">' . __('events.bulk_start_date') . '</label>',
                        new Input(type: 'date', name: 'start_date', id: 'start_date', required: true),
                        ''
                    ],
                    1 => [
                        '<label for="end_date">' . __('events.bulk_end_date') . '</label>',
                        new Input(type: 'date', name: 'end_date', id: 'end_date', required: true),
                        ''
                    ],
                    2 => [
                        '<label for="day_of_week">' . __('events.bulk_day_of_week') . '</label>',
                        new Select(
                            options: [
                                ''  => __('events.bulk_select_day'),
                                '1' => __('calendar.weekdays.monday'),
                                '2' => __('calendar.weekdays.tuesday'),
                                '3' => __('calendar.weekdays.wednesday'),
                                '4' => __('calendar.weekdays.thursday'),
                                '5' => __('calendar.weekdays.friday'),
                                '6' => __('calendar.weekdays.saturday'),
                                '0' => __('calendar.weekdays.sunday'),
                            ],
                            name: 'day_of_week',
                            id: 'day_of_week',
                            required: true,
                        ),
                        ''
                    ],
                    3 => [
                        '<label for="name">' . __('events.name') . '</label>',
                        new Input(name: 'name', id: 'name', placeholder: __('events.name'), required: true),
                        ''
                    ],
                    4 => [
                        '<label for="capacity">' . __('events.max_participants') . '</label>',
                        new Input(type: 'number', name: 'capacity', id: 'capacity', placeholder: __('events.max_participants'), required: true),
                        ''
                    ],
                    5 => [
                        __('events.play_together'),
                        '<input type="hidden" name="mixed" value="0"><input type="checkbox" name="mixed" value="1" checked>',
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
