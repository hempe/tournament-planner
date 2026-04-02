<?php

use TP\Components\Checkbox;
use TP\Components\Color;
use TP\Components\Input;
use TP\Components\Page;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\IconButton;
use TP\Components\Form;
use TP\Components\Textarea;
use TP\Core\Translator;

?>
<?= new Page(function () {
    $date = $_GET['date'] ?? date('Y-m-d');
    $formatter = new IntlDateFormatter(Translator::getInstance()->getLocale(), IntlDateFormatter::FULL, IntlDateFormatter::NONE);
    return new Form(
        action: "/events/new",
        hiddenInputs: ['date' => $date],
        content: new Card(
            "Neuer Termin: " . $formatter->format(strtotime($date)),
            new Table(
                columns: ['', '', '', '', '', '', '', ''],
                items: [0, 1],
                widths: [null, null, null, null, null, null, null, 1],
                projection: fn($row) => match($row) {
                    0 => [
                        new Input(
                            type: 'text',
                            name: 'name',
                            placeholder: __('events.name'),
                            required: true
                        ),
                        new Input(
                            type: 'number',
                            name: 'capacity',
                            placeholder: __('events.capacity'),
                            required: true
                        ),
                        new Checkbox(
                            name: 'mixed',
                            label: __('events.play_together'),
                            checked: true,
                        ),
                        new Input(
                            type: 'number',
                            name: 'price_members',
                            placeholder: __('events.price_members'),
                            step: '0.01',
                        ),
                        new Input(
                            type: 'number',
                            name: 'price_guests',
                            placeholder: __('events.price_guests'),
                            step: '0.01',
                        ),
                        new Input(
                            type: 'datetime-local',
                            name: 'registration_close',
                            placeholder: __('events.registration_close'),
                        ),
                        '',
                        new IconButton(
                            title: 'Speichern',
                            type: 'submit',
                            icon: 'fa-save',
                            color: Color::Primary,
                        )
                    ],
                    1 => [
                        new Textarea(
                            name: 'description',
                            placeholder: __('events.description'),
                            style: 'width:100%',
                        ),
                        '', '', '', '', '', '', ''
                    ],
                }
            )
        )
    );
});
