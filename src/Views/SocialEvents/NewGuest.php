<?php

use TP\Components\Card;
use TP\Components\Color;
use TP\Components\Form;
use TP\Components\IconButton;
use TP\Components\Input;
use TP\Components\Page;
use TP\Components\Select;
use TP\Components\Span;
use TP\Components\Table;
use TP\Core\Translator;
use TP\Models\SocialEvent;
use TP\Models\SocialMenu;
use TP\Models\SocialTable;
use TP\Models\User;

assert($socialEvent instanceof SocialEvent);
assert(is_array($menus));
assert(is_array($tables));

?>
<?= new Page(function () use ($socialEvent, $menus, $tables) {
    $formatter = new \IntlDateFormatter(Translator::getInstance()->getLocale(), \IntlDateFormatter::FULL, \IntlDateFormatter::NONE);
    $formattedDate = $formatter->format(strtotime($socialEvent->date));

    $isAdmin = User::admin();
    $req = new Span(content: ' *', style: 'color:var(--color-accent)');

    $cardTitle = [
        new IconButton(
            title: __('nav.back'),
            href: "/social-events/{$socialEvent->id}/admin",
            icon: 'fa-chevron-left',
            type: 'button',
            color: Color::None,
        ),
        new Span(content: $formattedDate, style: 'flex-grow:1'),
    ];

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
        action: "/social-events/{$socialEvent->id}/guests/new",
        content: new Card(
            title: $cardTitle,
            content: function () use ($socialEvent, $menuOptions, $tableOptions, $req, $isAdmin) {
                $details = [];
                if ($socialEvent->description) {
                    $details[] = [__('social_events.description'), nl2br(htmlspecialchars($socialEvent->description))];
                }
                yield new Card($socialEvent->name, new Table(
                    ['', ''],
                    $details,
                    fn($row) => $row,
                    widths: [150, null]
                ));
                yield new Table(
                    columns: ['', ''],
                    items: [0, 1, 2, 3, 4],
                    projection: fn($i) => match ($i) {
                        0 => [
                            __('guests.first_name') . $req,
                            new Input(name: 'first_name', placeholder: __('guests.first_name'), required: true),
                        ],
                        1 => [
                            __('guests.last_name') . $req,
                            new Input(name: 'last_name', placeholder: __('guests.last_name'), required: true),
                        ],
                        2 => [
                            __('guests.email') . (!$isAdmin ? $req : ''),
                            new Input(type: 'email', name: 'email', placeholder: __('guests.email'), required: !$isAdmin),
                        ],
                        3 => [
                            __('social_events.menu') . $req,
                            new Select(options: $menuOptions, name: 'menu_id', required: true),
                        ],
                        4 => [
                            __('social_events.table'),
                            new Select(options: $tableOptions, name: 'table_id'),
                        ],
                    },
                    widths: [150, null]
                );
                yield new IconButton(
                    type: 'submit',
                    title: __('guests.register'),
                    title_inline: true,
                    icon: 'fa-user-plus',
                    color: Color::Social,
                    style: 'width:100%',
                );
            }
        )
    );
});
