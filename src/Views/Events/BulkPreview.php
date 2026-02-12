<?php

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
        return new Card(
            "Keine Termine gefunden",
            <<<HTML
            <p>Es wurden keine Termine gefunden, die den angegebenen Kriterien entsprechen.</p>
            <a href="/events/bulk/new" class="button">Zurück</a>
            HTML
        );
    }

    return new Form(
        action: "/events/bulk/store",
        content: new Card(
            "Vorschau: {$count} Termine werden erstellt",
            new Table(
                columns: ['Datum', 'Name', 'Kapazität'],
                items: $events,
                projection: fn($event) => [
                    $event['date'],
                    $event['name'],
                    $event['capacity']
                ],
                footer: <<<HTML
                <div style="display: flex; gap: 1rem;">
                    <a href="/events/bulk/new" class="button">Zurück</a>
                    <button type="submit" class="button button--primary">
                        <i class="fa fa-check"></i> Alle erstellen
                    </button>
                </div>
                HTML
            )
        )
    );
});
