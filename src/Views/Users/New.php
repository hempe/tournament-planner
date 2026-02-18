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
                    new Select(
                        name: 'male',
                        options: ['1' => __('users.mr'), '0' => __('users.mrs')],
                        required: true,
                    ),
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
