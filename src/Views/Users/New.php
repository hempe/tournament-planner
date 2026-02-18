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
        action: "/users",
        content: new Card(
            __('users.create_user'),
            new Table(
                ['', '', '', ''],
                items: [null],
                widths: [1, null, null, 1],
                projection: fn() => [
                    '<select name="male" class="input" required>'
                        . '<option value="1">' . __('users.herr') . '</option>'
                        . '<option value="0">' . __('users.frau') . '</option>'
                        . '</select>',
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
