<?php
if (isset($_SESSION['user_id'])) {
    header('Location: /', true, 303);
    exit;
}

use TP\Components\Page;
use TP\Components\Card;
use TP\Components\Form;
use TP\Components\Input;
use TP\Components\Div;
use TP\Components\IconButton;
use TP\Components\Color;

echo new Page(
    new Div(
        class: 'content',
        style: [
            'display: flex;',
            'justify-content:center;'
        ],
        content: new Form(
            action: "/login",
            content: new Card(
                style: 'width: min(90vw, 300px)',
                title: new Div(
                    content: '<img src="favicon.svg?v=2.0" style="width:calc(100% - 40px); margin:20px" /><p>' . __('auth.welcome') . '</p>',
                    style: [
                        'display: flex;',
                        'flex-direction: column;',
                        'align-items: center;',
                        'width: 100%;'
                    ]
                ),
                content: new Div(
                    content: [
                        new Input(
                            type: 'text',
                            name: 'username',
                            placeholder: __('auth.username'),
                            required: true
                        ),
                        new Input(
                            type: 'password',
                            name: 'password',
                            placeholder: __('auth.password'),
                            required: true
                        ),
                        new IconButton(
                            title: __('auth.login'),
                            type: 'submit',
                            icon: 'fa-sign-in',
                            color: Color::Primary,
                            title_inline: true,
                            style: [
                                'width: 100%;',
                                'margin-top:24px;'
                            ]
                        )

                    ],
                    style: [
                        'background-color: var(--bg-card-title);',
                        'display: flex;',
                        'flex-direction: column;',
                        'align-items: center;',
                        'gap:12px;',
                        'padding:0px 24px 24px 24px;',
                    ]
                )
            )
        )
    )
);
