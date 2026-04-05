<?php

use TP\Components\Color;
use TP\Components\Input;
use TP\Components\Page;
use TP\Components\Select;
use TP\Components\Span;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\IconButton;
use TP\Components\Form;

assert(isset($user));

?>
<?= new Page(
    new Form(
        action: "/users/{$user->id}/update",
        content: new Card(
            [
                new IconButton(
                    title: __('nav.back'),
                    href: '/users',
                    icon: 'fa-chevron-left',
                    type: 'button',
                    color: Color::None,
                ),
                new Span(content: htmlspecialchars($user->displayName()), style: 'flex-grow:1'),
            ],
            new Table(
                ['', '', '', '', '', '', '', ''],
                items: [null],
                widths: [1, null, null, null, null, null, null, 1],
                projection: fn() => [
                    new Select(
                        name: 'male',
                        options: ['1' => __('users.mr'), '0' => __('users.mrs')],
                        required: true,
                        selected: $user->male ? '1' : '0',
                    ),
                    new Input(
                        type: 'text',
                        name: 'username',
                        placeholder: __('users.username'),
                        required: true,
                        value: $user->username,
                    ),
                    new Input(
                        type: 'password',
                        name: 'password',
                        placeholder: __('users.new_password'),
                    ),
                    new Input(
                        type: 'text',
                        name: 'first_name',
                        placeholder: __('users.first_name'),
                        value: $user->firstName ?? '',
                    ),
                    new Input(
                        type: 'text',
                        name: 'last_name',
                        placeholder: __('users.last_name'),
                        value: $user->lastName ?? '',
                    ),
                    new Input(
                        type: 'text',
                        name: 'member_number',
                        placeholder: __('users.member_number'),
                        value: $user->memberNumber ?? '',
                    ),
                    new Input(
                        type: 'text',
                        name: 'rfeg',
                        placeholder: __('users.rfeg'),
                        value: $user->rfeg ?? '',
                    ),
                    new IconButton(
                        title: __('users.update'),
                        type: 'submit',
                        icon: 'fa-save',
                        color: Color::Primary,
                    )
                ]
            )
        )
    )
);
