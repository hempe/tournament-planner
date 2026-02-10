<?php

use TP\Components\Page;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\IconButton;
use TP\Components\Form;
?>
<?= new Page(function () {
    $formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
    return new Form(
        action: "/events/new",
        hiddenInputs: ['date' => $_GET['date']],
        content: new Card(
            "Neuer Termin: " . $formatter->format(strtotime($_GET['date'])),
            new Table(
                columns: ['', '', ''],
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
