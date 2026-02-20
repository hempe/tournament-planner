<?php

use TP\Components\Checkbox;
use TP\Components\Color;
use TP\Components\Input;
use TP\Components\Page;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\IconButton;
use TP\Components\Form;
use TP\Core\Translator;

?>
<?= new Page(function () {
    $formatter = new IntlDateFormatter(Translator::getInstance()->getLocale(), IntlDateFormatter::FULL, IntlDateFormatter::NONE);
    return new Form(
        action: "/events/new",
        hiddenInputs: ['date' => $_GET['date']],
        content: new Card(
            "Neuer Termin: " . $formatter->format(strtotime($_GET['date'])),
            new Table(
                columns: ['', '', '', ''],
                items: [null],
                projection: fn() => [
                    new Input(
                        type: 'text',
                        name: 'name',
                        class: 'input',
                        placeholder: __('events.name'),
                        required: true
                    ),
                    new Input(
                        type: 'number',
                        name: 'capacity',
                        class: 'input',
                        placeholder: __('events.capacity'),
                        required: true
                    ),
                    new Checkbox(
                        name: 'mixed',
                        label: __('events.play_together'),
                        checked: true,
                    ),
                    new IconButton(
                        title: 'Speichern',
                        type: 'submit',
                        icon: 'fa-save',
                        color: Color::Primary,
                    )
                ]
            )
        )
    );
});
