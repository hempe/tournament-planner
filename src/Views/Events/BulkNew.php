<?php

use TP\Components\Checkbox;
use TP\Components\Color;
use TP\Components\Input;
use TP\Components\Label;
use TP\Components\Page;
use TP\Components\Select;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\IconButton;
use TP\Components\Form;
use TP\Components\Textarea;

?>
<?= new Page(function () {
    return new Form(
        action: "/events/bulk/preview",
        content: new Card(
            __('events.bulk_create'),
            new Table(
                columns: ['', ''],
                items: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
                widths: [180, null],
                projection: fn($index) => match ($index) {
                    0 => [
                        new Label(for: 'start_date', text: __('events.bulk_start_date')),
                        new Input(type: 'date', name: 'start_date', id: 'start_date', required: true),
                    ],
                    1 => [
                        new Label(for: 'end_date', text: __('events.bulk_end_date')),
                        new Input(type: 'date', name: 'end_date', id: 'end_date', required: true),
                    ],
                    2 => [
                        new Label(for: 'day_of_week', text: __('events.bulk_day_of_week')),
                        new Select(
                            options: [
                                '' => __('events.bulk_select_day'),
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
                    ],
                    3 => [
                        new Label(for: 'name', text: __('events.name')),
                        new Input(name: 'name', id: 'name', placeholder: __('events.name'), required: true),
                    ],
                    4 => [
                        new Label(for: 'capacity', text: __('events.max_participants')),
                        new Input(type: 'number', name: 'capacity', id: 'capacity', placeholder: __('events.max_participants'), required: true),
                    ],
                    5 => [
                        new Label(for: 'price_members', text: __('events.price_members')),
                        new Input(type: 'number', name: 'price_members', id: 'price_members', placeholder: __('events.price_members'), step: '0.01'),
                    ],
                    6 => [
                        new Label(for: 'price_guests', text: __('events.price_guests')),
                        new Input(type: 'number', name: 'price_guests', id: 'price_guests', placeholder: __('events.price_guests'), step: '0.01'),
                    ],
                    7 => [
                        new Label(for: 'registration_close_days', text: __('events.bulk_registration_close_days')),
                        new Input(type: 'number', name: 'registration_close_days', id: 'registration_close_days', placeholder: __('events.bulk_registration_close_days'), min: '1', max: '7'),
                    ],
                    8 => [
                        new Label(for: 'registration_close_time', text: __('events.bulk_registration_close_time')),
                        new Input(type: 'time', name: 'registration_close_time', id: 'registration_close_time'),
                    ],
                    9 => [
                        new Checkbox(
                            name: 'mixed',
                            label: __('events.play_together'),
                            checked: true
                        ),
                        new IconButton(
                            title: __('events.bulk_preview'),
                            title_inline: true,
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
