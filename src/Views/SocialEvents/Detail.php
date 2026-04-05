<?php

use TP\Components\Card;
use TP\Components\Color;
use TP\Components\Form;
use TP\Components\Icon;
use TP\Components\IconActionButton;
use TP\Components\IconButton;
use TP\Components\Page;
use TP\Components\Select;
use TP\Components\Span;
use TP\Components\Table;
use TP\Core\Translator;
use TP\Models\SocialEvent;
use TP\Models\SocialMenu;
use TP\Models\SocialRegistration;
use TP\Models\SocialTable;
use TP\Models\User;

assert($socialEvent instanceof SocialEvent);
assert(is_array($menus));
assert(is_array($tables));
assert($registration === null || $registration instanceof SocialRegistration);
assert(is_array($registrations));

?>
<?= new Page(function () use ($socialEvent, $menus, $tables, $registration, $registrations) {
    $formatter = new \IntlDateFormatter(Translator::getInstance()->getLocale(), \IntlDateFormatter::FULL, \IntlDateFormatter::NONE);
    $formattedDate = $formatter->format(strtotime($socialEvent->date));

    $cardTitle = [
        new IconButton(
            title: __('nav.back'),
            href: '/',
            icon: 'fa-chevron-left',
            type: 'button',
            color: Color::None,
        ),
        new Span(content: $formattedDate, style: 'flex-grow:1'),
    ];

    // Event info card
    $innerTitle = [new Span(content: $socialEvent->name, style: 'flex-grow:1')];
    if (User::admin()) {
        $innerTitle[] = new IconButton(
            title: __('social_events.edit'),
            href: "/social-events/{$socialEvent->id}/admin",
            icon: 'fa-edit',
            type: 'button',
            color: Color::Light,
        );
    }
    $details = [];
    if ($socialEvent->description) {
        $details[] = [__('social_events.description'), nl2br(htmlspecialchars($socialEvent->description))];
    }
    if ($socialEvent->registrationClose) {
        $closeFormatter = new \IntlDateFormatter(Translator::getInstance()->getLocale(), \IntlDateFormatter::FULL, \IntlDateFormatter::SHORT);
        $details[] = [__('social_events.registration_close'), $closeFormatter->format(strtotime($socialEvent->registrationClose))];
    }
    yield new Card(
        $cardTitle,
        new Card(
            $innerTitle,
            new Table(['', ''], $details, fn($row) => $row, widths: [150, null])
        )
    );

    // Registration card
    $regCardTitle = $registration
        ? [new Span(content: __('social_events.registered'), style: 'flex-grow:1'), new Icon('fa-user-check', '')]
        : __('social_events.register');

    yield new Card(
        $regCardTitle,
        function () use ($socialEvent, $menus, $tables, $registration) {
            if ($registration) {
                $regDetails = [
                    [__('social_events.menu'), $registration->menuName],
                    [
                        __('social_events.table'),
                        $registration->tableNumber !== null
                        ? __('social_events.table_number', ['number' => $registration->tableNumber])
                        : __('social_events.libero')
                    ],
                ];
                yield new Table(['', ''], $regDetails, fn($row) => $row, widths: [150, null]);
                if (!$socialEvent->isLocked) {
                    yield new Form(
                        action: "/social-events/{$socialEvent->id}/unregister",
                        style: 'display:flex; flex-direction:column; align-items:end; padding:12px;',
                        content: new IconButton(
                            type: 'submit',
                            title: __('social_events.unregister'),
                            title_inline: true,
                            icon: 'fa-user-minus',
                            color: Color::Accent,
                        )
                    );
                }
                return;
            }

            if ($socialEvent->isLocked) {
                yield new Table([''], [0], fn($i) => [__('social_events.locked_message')], widths: [null]);
                return;
            }

            if ($socialEvent->available <= 0) {
                yield new Table([''], [0], fn($i) => [__('social_events.full')], widths: [null]);
                return;
            }

            $menuOptions = [];
            foreach ($menus as $menu) {
                /** @var SocialMenu $menu */
                $menuOptions[$menu->id] = $menu->name;
            }

            $tableOptions = ['' => __('social_events.libero')];
            foreach ($tables as $table) {
                /** @var SocialTable $table */
                if ($table->available > 0) {
                    $tableOptions[$table->id] = __('social_events.table_number', ['number' => $table->number])
                        . ' (' . $table->available . ')';
                }
            }

            yield new Form(
                action: "/social-events/{$socialEvent->id}/register",
                content: new Table(
                    columns: ['', ''],
                    items: [0, 1, 2],
                    projection: fn($i) => match ($i) {
                        0 => [
                            __('social_events.menu') . new Span(content: ' *', style: 'color:var(--color-accent)'),
                            new Select(options: $menuOptions, name: 'menu_id', required: true),
                        ],
                        1 => [
                            __('social_events.table'),
                            new Select(options: $tableOptions, name: 'table_id'),
                        ],
                        2 => [
                            '',
                            new IconButton(
                                type: 'submit',
                                title: __('social_events.register'),
                                title_inline: true,
                                icon: 'fa-user-plus',
                                color: Color::Primary,
                                style: 'width:100%',
                            )
                        ],
                    },
                    widths: [150, null],
                    style: 'width:100%'
                ),
            );
        }
    );

    if (count($registrations) > 0) {
        yield new Card(
            __('social_events.participants'),
            new Table(
                columns: [__('social_events.table'), __('events.name')],
                items: $registrations,
                projection: fn(SocialRegistration $reg) => [
                    $reg->tableNumber !== null
                    ? __('social_events.table_number', ['number' => $reg->tableNumber])
                    : __('social_events.libero'),
                    htmlspecialchars($reg->displayName)
                ],
            )
        );
    }
});
