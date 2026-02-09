<?php

use GolfElFaro\Components\Page;
use GolfElFaro\Components\Table;
use GolfElFaro\Components\Card;
use GolfElFaro\Components\IconButton;
use GolfElFaro\Components\Form;
use GolfElFaro\Components\Input;

?>
<?= new Page(
    new Form(
        action: "/users/new",
        content: new Card(
            'Benutzer erfassen',
            new Table(
                ['', '', ''],
                items: [null],
                widths: [null, null, 1],
                projection: fn() => [
                    new Input(
                        type: 'text',
                        name: 'username',
                        placeholder: 'Benutzername',
                        required: true
                    ),
                    new Input(
                        type: 'password',
                        name: 'password',
                        placeholder: 'Passwort',
                        required: true
                    ),
                    new IconButton(
                        title: 'Registrieren',
                        type: 'submit',
                        icon: 'fa-save',
                        color: Color::Primary,
                    )
                ]
            )
        )
    )
);
