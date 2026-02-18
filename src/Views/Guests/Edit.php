<?php

use TP\Components\Color;
use TP\Components\Input;
use TP\Components\Page;
use TP\Components\Select;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\IconButton;
use TP\Components\Form;
use TP\Models\Event;
use TP\Models\EventGuest;

assert($event instanceof Event);
assert($guest instanceof EventGuest);

?>
<?= new Page(function () use ($event, $guest) {
    $req = ' <span style="color:var(--color-accent)">*</span>';

    yield new Form(
        action: "/events/{$event->id}/guests/{$guest->id}/update",
        content: new Card(
            title: __('guests.edit'),
            content: new Table(
                columns: ['', ''],
                items: [0, 1, 2, 3, 4, 5, 6, 7],
                projection: fn($i) => match ($i) {
                    0 => [__('users.salutation') . $req, new Select(
                        name: 'male',
                        options: ['1' => __('users.mr'), '0' => __('users.mrs')],
                        selected: $guest->male ? '1' : '0',
                        required: true,
                    )],
                    1 => [__('guests.first_name') . $req, new Input(
                        name: 'first_name',
                        value: $guest->firstName,
                        placeholder: __('guests.first_name'),
                        required: true,
                    )],
                    2 => [__('guests.last_name') . $req, new Input(
                        name: 'last_name',
                        value: $guest->lastName,
                        placeholder: __('guests.last_name'),
                        required: true,
                    )],
                    3 => [__('guests.email'), new Input(
                        type: 'email',
                        name: 'email',
                        value: $guest->email ?? '',
                        placeholder: __('guests.email'),
                    )],
                    4 => [__('guests.handicap'), new Input(
                        type: 'number',
                        name: 'handicap',
                        value: $guest->handicap !== null ? (string) $guest->handicap : '',
                        placeholder: __('guests.handicap'),
                        step: '0.1',
                    )],
                    5 => [__('guests.rfeg'), new Input(
                        name: 'rfeg',
                        value: $guest->rfeg ?? '',
                        placeholder: __('guests.rfeg'),
                    )],
                    6 => [__('guests.comment'), '<textarea name="comment" class="input" placeholder="' . __('guests.comment') . '">' . htmlspecialchars($guest->comment ?? '') . '</textarea>'],
                    7 => [
                        '',
                        new IconButton(
                            type: 'submit',
                            title: __('events.save'),
                            title_inline: true,
                            icon: 'fa-save',
                            color: Color::Primary,
                            style: 'width:100%'
                        )
                    ],
                },
                widths: [150, null]
            )
        )
    );
});
