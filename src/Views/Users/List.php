<?php

use TP\Components\Page;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\IconButton;
use TP\Components\IconActionButton;
use TP\Components\Color;
use TP\Models\DB;

?>
<?= new Page(new Card(
    __('users.title') . new IconButton(
        title: __('users.add_user'),
        type: 'button',
        icon: 'fa-user-plus',
        color: Color::None,
        href: "/users/new"
    ),
    new Table(
        ['', '', '', ''],
        DB::$users->all(),
        function ($user) {
            return [
                htmlspecialchars($user->username),
                new IconActionButton(
                    actionUrl: "/users/{$user->id}/admin",
                    title: $user->isAdmin ? __('users.remove_admin_rights') : __('users.give_admin_rights'),
                    color: $user->isAdmin ? Color::Primary : Color::Light,
                    icon: $user->isAdmin ? 'fa-toggle-on' : 'fa-toggle-off',
                    confirmMessage: $user->isAdmin ? __('users.remove_admin_rights_confirm', ['username' => $user->username]) : __('users.give_admin_rights_confirm', ['username' => $user->username]),
                    hiddenInputs: [
                        'admin' => $user->isAdmin ? 0 : 1,
                    ],
                ),
                new IconButton(
                    title: __('users.edit'),
                    type: 'button',
                    icon: 'fa-edit',
                    color: Color::Light,
                    href: "/users/{$user->id}/edit"
                ),
                new IconActionButton(
                    actionUrl: "/users/{$user->id}/delete",
                    title: __('users.delete'),
                    color: Color::Accent,
                    icon: 'fa-trash',
                    confirmMessage: __('users.delete_confirm', ['username' => $user->username]),
                )
            ];
        },
        widths: [null, 1, 1, 1]
    )
));
