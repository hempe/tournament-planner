<?php

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

    $isIframeMode = isset($_GET['iframe']) && $_GET['iframe'] === '1';
    $backUrl = Url::build(isset($_GET['b']) ? '/guest?date=' . $_GET['b'] : '/guest');
    $cardTitle = $isIframeMode
        ? [
            new IconButton(
                title: __('nav.back'),
                onClick: "window.location.href='{$backUrl}'",
                icon: 'fa-chevron-left',
                type: 'button',
                color: Color::None,
            ),
            new Span(
                content: "{$formattedDate}: {$event->name}",
                style: 'flex-grow:1'
            )
        ]
        : "{$formattedDate}: {$event->name}";

    $action = Url::build("/events/{$event->id}/guests/new");
    yield new Form(
        action: $action,
        content: new Card(
            title: $cardTitle,
            content: new Table(
                columns: ['', ''],
                items: [0, 1, 2, 3, 4, 5, 6, 7],
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
                    5 => [
                        __('guests.rfeg'),
                        new Input(
                            name: 'rfeg',
                            placeholder: __('guests.rfeg'),
                        )
                    ],
                    6 => [__('guests.comment'), '<textarea name="comment" class="input" placeholder="' . __('guests.comment') . '"></textarea>'],
                    7 => [
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
            )
        )
    );
});
