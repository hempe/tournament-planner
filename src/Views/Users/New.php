<?php

use TP\Components\Color;
use TP\Components\Page;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\IconButton;
use TP\Components\Form;
use TP\Components\Input;

?>
<?= new Page(
    new Form(
        action: "/users/new",
        content: new Card(
            __('users.create_user'),
            new Table(
                ['', '', ''],
                items: [null],
                widths: [null, null, 1],
                projection: fn() => [
                    new Input(
                        type: 'text',
                        name: 'username',
                        placeholder: __('users.username'),
                        required: true
                    ),
                    new Input(
                        type: 'password',
                        name: 'password',
                        placeholder: __('users.password'),
                        required: true
                    ),
                    new IconButton(
                        title: __('users.register'),
                        type: 'submit',
                        icon: 'fa-save',
                        color: Color::Primary,
                    )
                ]
            )
        )
    )
);
