<?php

use TP\Components\Color;
use TP\Components\Page;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\IconButton;
use TP\Components\Form;

?>
<?= new Page(function () use ($event) {
    $formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
    $formattedDate = $formatter->format(strtotime($event->date));

    yield new Form(
        action: "/events/{$event->id}/guests/new",
        content: new Card(
            title: "{$formattedDate}: {$event->name}",
            content: new Table(
                columns: ['', ''],
                items: [0, 1, 2, 3, 4, 5, 6],
                projection: fn($i) => match ($i) {
                    0 => [__('guests.first_name'), '<input type="text" name="first_name" class="input" placeholder="' . __('guests.first_name') . '" required>'],
                    1 => [__('guests.last_name'), '<input type="text" name="last_name" class="input" placeholder="' . __('guests.last_name') . '" required>'],
                    2 => [__('guests.email'), '<input type="email" name="email" class="input" placeholder="' . __('guests.email') . '" required>'],
                    3 => [__('guests.handicap'), '<input type="number" step="0.1" name="handicap" class="input" placeholder="' . __('guests.handicap') . '" required>'],
                    4 => [__('guests.rfeg'), '<input type="text" name="rfeg" class="input" placeholder="' . __('guests.rfeg') . '">'],
                    5 => [__('guests.comment'), '<textarea name="comment" class="input" placeholder="' . __('guests.comment') . '"></textarea>'],
                    6 => ['', new IconButton(
                        title: __('guests.register'),
                        type: 'submit',
                        icon: 'fa-user-plus',
                        color: Color::Primary,
                    )],
                },
                widths: [1, null]
            )
        )
    );
});
