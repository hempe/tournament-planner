<?php

use TP\Components\Page;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\InputAction;
use TP\Components\IconButton;
use TP\Components\IconActionButton;

?>
<?= new Page(new Card(
    'Benutzer' . new IconButton(
        title: 'Benutzer hinzufügen',
        type: 'button',
        icon: 'fa-user-plus',
        color: Color::None,
        onClick: "window.location.href='/users/new'"
    ),
    new Table(
        ['', '', '', '',],
        DB::$users->all(),
        function ($user) {
            return [
                htmlspecialchars($user->username),
                new InputAction(
                    title: 'Neues Passwort setzen',
                    actionUrl: "/users/password",
                    color: Color::Light,
                    inputName: 'password',
                    inputValue: '',
                    inputPlaceholder: 'Neues Passwort',
                    icon: 'fa-save',
                    confirmMessage: 'Neues Passwort setzen?',
                    type: 'password',
                    hiddenInputs: ['userId' => $user->id]
                ),
                new IconActionButton(
                    actionUrl: "/users/admin",
                    title: $user->isAdmin ? 'Admin rechte entnehmen?' : 'Admin rechte geben?',
                    color: $user->isAdmin ? Color::Primary : Color::Light,
                    icon: $user->isAdmin ? 'fa-toggle-on' : 'fa-toggle-off',
                    confirmMessage: "$user->username " . ($user->isAdmin ? 'Admin rechte entnehmen?' : 'Admin rechte geben?'),
                    hiddenInputs: [
                        'id' => $user->id,
                        'admin' =>  $user->isAdmin ? 0 : 1,
                    ],
                ),
                new IconActionButton(
                    actionUrl: "/users/delete",
                    title: 'Löschen',
                    color: Color::Accent,
                    icon: 'fa-trash',
                    confirmMessage: "{$user->username} definitiv löschen?",
                    hiddenInputs: ['id' => $user->id],
                )
            ];
        },
        widths: [null, null, 1, 1, 1]
    )
));
