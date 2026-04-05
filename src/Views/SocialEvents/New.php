<?php

use TP\Components\Card;
use TP\Components\Color;
use TP\Components\Form;
use TP\Components\IconButton;
use TP\Components\Input;
use TP\Components\Page;
use TP\Components\Span;
use TP\Components\Table;
use TP\Components\Textarea;
use TP\Models\Event;

assert(!isset($tournament) || $tournament instanceof Event || $tournament === null);

?>
<?= new Page(function () use ($tournament) {
    $backUrl = $tournament ? "/events/{$tournament->id}" : '/events';
    $tournamentId = $tournament ? $tournament->id : null;

    $cardTitle = [
        new IconButton(
            title: __('nav.back'),
            href: $backUrl,
            icon: 'fa-chevron-left',
            type: 'button',
            color: Color::None,
        ),
        new Span(content: __('social_events.new'), style: 'flex-grow:1'),
    ];

    yield new Form(
        action: '/social-events/new' . ($tournamentId ? '?tournamentId=' . $tournamentId : ''),
        content: new Card(
            title: $cardTitle,
            content: new Table(
                columns: ['', ''],
                items: [0, 1, 2, 3, 4, 5, 6],
                projection: fn($i) => match ($i) {
                    0 => [__('social_events.name'), new Input(
                        type: 'text',
                        name: 'name',
                        value: old('name'),
                        placeholder: __('social_events.name'),
                        required: true,
                    )],
                    1 => [__('social_events.date'), new Input(
                        type: 'date',
                        name: 'date',
                        value: old('date', $tournament ? $tournament->date : ''),
                        required: true,
                    )],
                    2 => [__('social_events.description'), new Textarea(
                        name: 'description',
                        value: old('description'),
                        placeholder: __('social_events.description'),
                        style: 'width:100%',
                    )],
                    3 => [__('social_events.registration_close'), new Input(
                        type: 'datetime-local',
                        name: 'registration_close',
                        value: old('registration_close'),
                        placeholder: __('social_events.registration_close'),
                    )],
                    4 => [
                        __('social_events.menus') . '<br><small>' . __('social_events.menus_hint') . '</small>',
                        new Input(
                            type: 'text',
                            name: 'menus',
                            value: old('menus'),
                            placeholder: __('social_events.menus_hint'),
                            required: true,
                        )
                    ],
                    5 => [
                        __('social_events.tables') . '<br><small>' . __('social_events.tables_hint') . '</small>',
                        new Input(
                            type: 'text',
                            name: 'tables',
                            value: old('tables'),
                            placeholder: __('social_events.tables_hint'),
                            required: true,
                        )
                    ],
                    6 => ['', new IconButton(
                        type: 'submit',
                        title: __('social_events.create'),
                        title_inline: true,
                        icon: 'fa-plus',
                        color: Color::Social,
                        style: 'width:100%',
                    )],
                },
                widths: [200, null]
            )
        )
    );
});
