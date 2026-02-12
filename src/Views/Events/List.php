<?php

use TP\Components\Color;
use TP\Components\Page;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\IconActionButton;
use TP\Models\DB;
use TP\Models\User;

?>
<?= new Page(function () {
    if (User::admin()) {
        echo <<<HTML
        <div style="margin-bottom: 1rem;">
            <a href="/events/bulk/new" class="button button--primary">
                <i class="fa fa-calendar-plus"></i> Mehrfache Termine erstellen
            </a>
        </div>
        HTML;
    }

    yield new Card(
        __('events.title'),
        new Table(
            [__('events.date'), __('events.name'), __('events.max_participants'), __('events.registered'), __('events.waitlist'), ''],
            DB::$events->all(),
            fn($event) => [
                $event->date,
                $event->name,
                $event->capacity,
                $event->joined,
                $event->onWaitList,
                new IconActionButton(
                    actionUrl: "/events/{$event->id}/delete",
                    title: __('events.delete'),
                    color: Color::Accent,
                    icon: 'fa-trash',
                    confirmMessage: __('events.delete_confirm', ['name' => $event->name])
                )
            ],
            fn($event) => "window.location.href='/events/{$event->id}'",
            widths: [null, null, null, null, null, 1]
        )
    );
}) ?>