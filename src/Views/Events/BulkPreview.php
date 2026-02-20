<?php

use TP\Core\Url;
use TP\Components\Color;
use TP\Components\Page;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\IconButton;
use TP\Components\Form;

$events = $_SESSION['bulk_events'] ?? [];
$count = count($events);

?>
<?= new Page(function () use ($events, $count) {
    if ($count === 0) {
        $message = __('events.bulk_no_events_message');
        $backText = __('nav.back');
        return new Card(
            __('events.bulk_no_events'),
            <<<HTML
            <p>{$message}</p>
            <a href="/events/bulk/new" class="button">{$backText}</a>
            HTML
        );
    }
    yield new Form(
        action: "/events/bulk/store",
        content: new Card(
            new IconButton(
                title: __('nav.back'),
                type: 'button',
                icon: 'fa-arrow-left',
                color: Color::None,
                href: "/events/bulk/new"
            ) .
            __('events.bulk_preview_title', ['count' => $count]) .
            new IconButton(
                title: __('events.bulk_create_all'),
                type: 'submit',
                icon: 'fa-check',
                color: Color::Primary
            ),
            new Table(
                columns: [__('events.date'), __('events.name'), __('events.capacity'), __('events.play_together')],
                items: $events,
                projection: fn($event) => [
                    $event['date'],
                    $event['name'],
                    $event['capacity'],
                    ($event['mixed'] ?? true) ? '✓' : '✗',
                ]
            )
        )
    );
});
