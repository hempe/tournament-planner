<?php

use TP\Components\Color;
use TP\Components\IconActionButton;
use TP\Components\Page;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\Form;
use TP\Models\User;

?>
<?= new Page(function () use ($event) {
    $formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
    $formattedDate = $formatter->format(strtotime($event->date));

    $isAdmin = User::admin();
    $req = ' <span style="color:var(--color-accent)">*</span>';

    $anredeSelect = '<select name="male" class="input" required>'
        . '<option value="1">' . __('users.herr') . '</option>'
        . '<option value="0">' . __('users.frau') . '</option>'
        . '</select>';

    $action = "/events/{$event->id}/guests/new";
    yield new Form(
        action: $action,
        content: new Card(
            title: "{$formattedDate}: {$event->name}",
            content: new Table(
                columns: ['', ''],
                items: [0, 1, 2, 3, 4, 5, 6, 7],
                projection: fn($i) => match ($i) {
                    0 => [__('users.anrede') . $req, $anredeSelect],
                    1 => [__('guests.first_name') . $req, '<input type="text" name="first_name" class="input" placeholder="' . __('guests.first_name') . '" required>'],
                    2 => [__('guests.last_name') . $req, '<input type="text" name="last_name" class="input" placeholder="' . __('guests.last_name') . '" required>'],
                    3 => [__('guests.email') . (!$isAdmin ? $req : ''), '<input type="email" name="email" class="input" placeholder="' . __('guests.email') . '"' . (!$isAdmin ? ' required' : '') . '>'],
                    4 => [__('guests.handicap') . (!$isAdmin ? $req : ''), '<input type="number" step="0.1" name="handicap" class="input" placeholder="' . __('guests.handicap') . '"' . (!$isAdmin ? ' required' : '') . '>'],
                    5 => [__('guests.rfeg'), '<input type="text" name="rfeg" class="input" placeholder="' . __('guests.rfeg') . '">'],
                    6 => [__('guests.comment'), '<textarea name="comment" class="input" placeholder="' . __('guests.comment') . '"></textarea>'],
                    7 => [
                        '',
                        new IconActionButton(
                            actionUrl: $action,
                            title: __('guests.register'),
                            //type: 'submit',

                            icon: 'fa-user-plus',
                            color: Color::Primary,
                        )
                    ],
                },
                widths: [1, null]
            )
        )
    );
});
