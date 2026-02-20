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
        $bulkCreateText = __('events.bulk_create');
        yield <<<HTML
        <div style="margin-bottom: 1rem;">
            <a href="/events/bulk/new" class="button button--primary">
                <i class="fa fa-calendar-plus"></i> {$bulkCreateText}
            </a>
        </div>
        HTML;
    }

    $isAdmin = User::admin();

    $columns = [__('events.date'), __('events.name'), __('events.max_participants'), __('events.registered'), __('events.waitlist')];
    $widths = [null, null, null, null, null];

    if ($isAdmin) {
        $columns[] = '';
        $widths[] = 1;
    }

    yield new Card(
        __('events.title'),
        new Table(
            columns: $columns,
            items: DB::$events->all(),
            projection: function ($event) use ($isAdmin) {
                $row = [
                    $event->date,
                    $event->name,
                    $event->capacity,
                    $event->joined,
                    $event->onWaitList,
                ];

                if ($isAdmin) {
                    $row[] = new IconActionButton(
                        actionUrl: "/events/{$event->id}/delete",
                        title: __('events.delete'),
                        color: Color::Accent,
                        icon: 'fa-trash',
                        confirmMessage: __('events.delete_confirm', ['name' => $event->name])
                    );
                }

                return $row;
            },
            href: fn($event) => "/events/{$event->id}",
            widths: $widths
        )
    );
}) ?>