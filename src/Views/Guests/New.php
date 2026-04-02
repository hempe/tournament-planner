<?php

use TP\Components\Div;
use TP\Components\Span;
use TP\Components\Color;
use TP\Components\IconButton;
use TP\Components\Input;
use TP\Components\Page;
use TP\Components\Select;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\Form;
use TP\Core\Translator;
use TP\Core\Url;
use TP\Models\User;
use TP\Models\Event;
use TP\Models\EventGuest;

assert($event instanceof Event);

?>
<?= new Page(function () use ($event) {
    $formatter = new IntlDateFormatter(Translator::getInstance()->getLocale(), IntlDateFormatter::FULL, IntlDateFormatter::NONE);
    $formattedDate = $formatter->format(strtotime($event->date));

    $isAdmin = User::admin();
    $req = new Span(
        content: " *",
        style: 'color:var(--color-accent)'
    );

    $cardTitle = [
        new IconButton(
            title: __('nav.back'),
            href: isset($_GET['b']) ? '/guest?date=' . $_GET['b'] : '/guest',
            icon: 'fa-chevron-left',
            type: 'button',
            color: Color::None,
        ),
        new Span(
            content: $formattedDate,
            style: 'flex-grow:1'
        )
    ];

    yield new Form(
        action: "/events/{$event->id}/guests/new",
        content: new Card(
            title: $cardTitle,
            content: function () use ($event, $req, $isAdmin) {
                $details = [];

                if ($event->description || $event->priceMembers !== null || $event->priceGuests !== null || $event->registrationClose) {
                    if ($event->description) {
                        $details[] = [__('events.description'), nl2br(htmlspecialchars($event->description))];
                    }
                    if ($event->priceMembers !== null) {
                        $details[] = [__('events.price_members'), number_format($event->priceMembers, 2)];
                    }
                    if ($event->priceGuests !== null) {
                        $details[] = [__('events.price_guests'), number_format($event->priceGuests, 2)];
                    }
                    if ($event->registrationClose) {
                        $closeFormatter = new \IntlDateFormatter(Translator::getInstance()->getLocale(), \IntlDateFormatter::FULL, \IntlDateFormatter::SHORT);
                        $details[] = [__('events.registration_close'), $closeFormatter->format(strtotime($event->registrationClose))];
                    }
                }
                yield new Card($event->name, new Table(
                    ['', ''],
                    $details,
                    fn($row) => $row,
                    widths: [150, null]
                ));
                yield new Div(__('events.register'), class: 'card-title');
                yield new Table(
                    columns: ['', ''],
                    items: [0, 1, 2, 3, 4, 5, 6],
                    projection: fn($i) => match ($i) {
                        0 => [
                            __('users.salutation') . $req,
                            new Select(
                                name: 'male',
                                options: ['1' => __('users.mr'), '0' => __('users.mrs')],
                                required: true,
                            )
                        ],
                        1 => [
                            __('guests.first_name') . $req,
                            new Input(
                                name: 'first_name',
                                placeholder: __('guests.first_name'),
                                required: true,
                            )
                        ],
                        2 => [
                            __('guests.last_name') . $req,
                            new Input(
                                name: 'last_name',
                                placeholder: __('guests.last_name'),
                                required: true,
                            )
                        ],
                        3 => [
                            __('guests.email') . (!$isAdmin ? $req : ''),
                            new Input(
                                type: 'email',
                                name: 'email',
                                placeholder: __('guests.email'),
                                required: !$isAdmin,
                            )
                        ],
                        4 => [
                            __('guests.handicap') . (!$isAdmin ? $req : ''),
                            new Input(
                                type: 'number',
                                name: 'handicap',
                                placeholder: __('guests.handicap'),
                                required: !$isAdmin,
                                step: '0.1',
                            )
                        ],
                        5 => [__('guests.comment'), '<textarea name="comment" class="input" placeholder="' . __('guests.comment') . '"></textarea>'],
                        6 => [
                            '',
                            new IconButton(
                                type: 'submit',
                                title: __('guests.register'),
                                title_inline: true,
                                icon: 'fa-user-plus',
                                color: Color::Primary,
                                style: 'width:100%'
                            )
                        ],
                    },
                    widths: [150, null]
                );
            }
        )
    );
});
