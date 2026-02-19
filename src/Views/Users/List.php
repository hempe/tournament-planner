<?php

use TP\Components\Page;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\InputAction;
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
        onClick: "window.location.href='/users/new'"
    ),
    new Table(
        ['', '', '', '', '', ''],
        DB::$users->all(),
        function ($user) {
            return [
                htmlspecialchars($user->username),
                new InputAction(
                    title: __('users.set_new_password'),
                    actionUrl: "/users/{$user->id}/password",
                    color: Color::Light,
                    inputName: 'password',
                    inputValue: '',
                    inputPlaceholder: __('users.new_password'),
                    icon: 'fa-save',
                    confirmMessage: __('users.set_new_password_confirm'),
                    type: 'password',
                ),
                new InputAction(
                    title: __('users.member_number'),
                    actionUrl: "/users/{$user->id}/member_number",
                    color: Color::Light,
                    inputName: 'member_number',
                    inputValue: $user->memberNumber ?? '',
                    inputPlaceholder: __('users.member_number'),
                    icon: 'fa-save',
                    confirmMessage: '',
                ),
                new InputAction(
                    title: __('users.rfeg'),
                    actionUrl: "/users/{$user->id}/rfeg",
                    color: Color::Light,
                    inputName: 'rfeg',
                    inputValue: $user->rfeg ?? '',
                    inputPlaceholder: __('users.rfeg'),
                    icon: 'fa-save',
                    confirmMessage: '',
                ),
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
                new IconActionButton(
                    actionUrl: "/users/{$user->id}/delete",
                    title: __('users.delete'),
                    color: Color::Accent,
                    icon: 'fa-trash',
                    confirmMessage: __('users.delete_confirm', ['username' => $user->username]),
                )
            ];
        },
        widths: [null, null, null, null, 1, 1]
    )
));
