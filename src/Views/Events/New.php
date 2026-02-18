<?php

use TP\Components\Color;
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
                    <<<HTML
                        <input
                            type="text"
                            name="name"
                            class="input"
                            placeholder="Name"
                            required>
                    HTML,
                    <<<HTML
                        <input
                            type="number"
                            name="capacity"
                            class="input"
                            placeholder="Max. Teilnehmer"
                            required>
                    HTML,
                    '<input type="hidden" name="mixed" value="0"><label style="display:flex;align-items:center;gap:.4rem;white-space:nowrap"><input type="checkbox" name="mixed" value="1" checked> ' . __('events.play_together') . '</label>',
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
