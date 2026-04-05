<?php

use TP\Components\Card;
use TP\Components\Color;
use TP\Components\Form;
use TP\Components\IconActionButton;
use TP\Components\IconButton;
use TP\Components\Input;
use TP\Components\Page;
use TP\Components\Span;
use TP\Components\Table;
use TP\Components\Textarea;
use TP\Core\Translator;
use TP\Models\SocialEvent;
use TP\Models\SocialRegistration;
use TP\Models\SocialTable;

assert($socialEvent instanceof SocialEvent);
assert(is_array($registrations));
assert(is_array($tables));
assert(is_array($menus));

?>
<?= new Page(function () use ($socialEvent, $registrations, $tables, $menus) {
    $formatter = new \IntlDateFormatter(Translator::getInstance()->getLocale(), \IntlDateFormatter::FULL, \IntlDateFormatter::NONE);
    $formattedDate = $formatter->format(strtotime($socialEvent->date));

    $backUrl = $socialEvent->tournamentId ? "/events/{$socialEvent->tournamentId}" : '/';

    $cardTitle = [
        new IconButton(
            title: __('nav.back'),
            href: $backUrl,
            icon: 'fa-chevron-left',
            type: 'button',
            color: Color::None,
        ),
        new Span(content: $formattedDate, style: 'flex-grow:1'),
    ];

    $menusString = implode(', ', array_map(fn($m) => $m->name, $menus));
    $tablesString = implode(', ', array_map(fn($t) => (string) $t->capacity, $tables));

    yield new Form(
        action: "/social-events/{$socialEvent->id}",
        content: new Card(
            title: $cardTitle,
            content: new Card(
                title: [
                    new Span(content: $socialEvent->name, style: 'flex-grow:1'),
                    new IconButton(
                        title: __('social_events.save'),
                        type: 'submit',
                        icon: 'fa-save',
                        color: Color::Social,
                    ),
                    new IconActionButton(
                        actionUrl: "/social-events/{$socialEvent->id}/delete",
                        title: __('social_events.delete'),
                        color: Color::Accent,
                        icon: 'fa-trash',
                        confirmMessage: __('social_events.delete_confirm'),
                    ),
                    $socialEvent->isLocked
                        ? new IconActionButton(
                            actionUrl: "/social-events/{$socialEvent->id}/unlock",
                            title: __('social_events.unlock'),
                            color: Color::Light,
                            icon: 'fa-lock',
                            confirmMessage: __('social_events.unlock_confirm'),
                        )
                        : new IconActionButton(
                            actionUrl: "/social-events/{$socialEvent->id}/lock",
                            title: __('social_events.lock'),
                            color: Color::Light,
                            icon: 'fa-unlock',
                            confirmMessage: __('social_events.lock_confirm'),
                        ),
                ],
                content: new Table(
                    columns: ['', ''],
                    items: [0, 1, 2, 3, 4, 5],
                    projection: fn($i) => match ($i) {
                        0 => [__('social_events.name'), new Input(
                            type: 'text',
                            value: $socialEvent->name,
                            name: 'name',
                            placeholder: __('social_events.name'),
                            required: true,
                        )],
                        1 => [__('social_events.date'), new Input(
                            type: 'date',
                            value: $socialEvent->date,
                            name: 'date',
                            required: true,
                        )],
                        2 => [__('social_events.description'), new Textarea(
                            name: 'description',
                            value: $socialEvent->description ?? '',
                            placeholder: __('social_events.description'),
                            style: 'width:100%',
                        )],
                        3 => [__('social_events.registration_close'), new Input(
                            type: 'datetime-local',
                            name: 'registration_close',
                            value: $socialEvent->registrationClose
                                ? substr(str_replace(' ', 'T', $socialEvent->registrationClose), 0, 16)
                                : '',
                            placeholder: __('social_events.registration_close'),
                        )],
                        4 => [
                            __('social_events.menus') . '<br><small>' . __('social_events.menus_hint') . '</small>',
                            new Input(
                                type: 'text',
                                name: 'menus',
                                value: $menusString,
                                placeholder: __('social_events.menus_hint'),
                                required: true,
                            )
                        ],
                        5 => [
                            __('social_events.tables') . '<br><small>' . __('social_events.tables_hint') . '</small>',
                            new Input(
                                type: 'text',
                                name: 'tables',
                                value: $tablesString,
                                placeholder: __('social_events.tables_hint'),
                                required: true,
                            )
                        ],
                    },
                    widths: [200, null]
                )
            )
        )
    );

    if (count($registrations) > 0) {
        $guestHeader = [
            new Span(content: __('social_events.participants'), style: 'flex-grow:1'),
            new IconButton(
                title: __('social_events.add_guest'),
                href: "/social-events/{$socialEvent->id}/guests/new",
                icon: 'fa-user-plus',
                type: 'button',
                color: Color::Social,
            ),
        ];
        yield new Card(
            $guestHeader,
            new Table(
                columns: [__('guests.first_name') . ' ' . __('guests.last_name'), __('social_events.table'), __('social_events.menu'), ''],
                items: $registrations,
                projection: fn(SocialRegistration $reg) => [
                    $reg->displayName,
                    $reg->tableNumber !== null
                        ? __('social_events.table_number', ['number' => $reg->tableNumber])
                        : __('social_events.libero'),
                    $reg->menuName,
                    new IconActionButton(
                        actionUrl: "/social-events/{$socialEvent->id}/registrations/{$reg->id}/delete",
                        title: __('events.delete'),
                        color: Color::Accent,
                        icon: 'fa-trash',
                        confirmMessage: __('social_events.delete_confirm'),
                    ),
                ],
                widths: [null, 1, 1, 1]
            )
        );
    } else {
        $guestHeader = [
            new Span(content: __('social_events.participants'), style: 'flex-grow:1'),
            new IconButton(
                title: __('social_events.add_guest'),
                href: "/social-events/{$socialEvent->id}/guests/new",
                icon: 'fa-user-plus',
                type: 'button',
                color: Color::Social,
            ),
        ];
        yield new Card($guestHeader, '');
    }
});
