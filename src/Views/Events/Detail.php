<?php

use TP\Core\Log;
use TP\Models\DB;
use TP\Components\Page;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\Color;
use TP\Components\Icon;
use TP\Components\InputAction;
use TP\Components\IconActionButton;
use TP\Models\User;

?>

<?= new Page(function () use ($id) {
    $formatter = new \IntlDateFormatter('de_DE', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE);

    $event = DB::$events->get(
        $id,
        $_SESSION['user_id']
    );
    if (!$event) {
        Log::trace('event/detail', 'Redirecting to / because we could not find the event');
        header("Location: /", true, 303);
        exit;
    }

    $formattedDate = $formatter->format(strtotime($event->date));
    $eventRegistrations = DB::$events->registrations($event->id);

    $eventFull = $event->available <= 0;
    $user = User::current();
    $filteredRegs = array_filter($eventRegistrations, fn($reg) => $reg->userId == User::id());
    $reg = !empty($filteredRegs) ? array_values($filteredRegs)[0] : null;

    $regState = !$reg
        ? ''
        : ($reg->state == 1
            ? new Icon('fa-user-check', 'Angemeldet')
            : new Icon('fa-user-clock', 'Auf Warteliste')
        );

    yield new Card(
        "$formattedDate: {$event->name} {$regState}",
        function () use ($id, $event, $eventFull, $reg) {
            if (!$reg) {
                if (!$event->isLocked) {
                    yield new Table(
                        columns: ['Anmelden' . $event->isLocked],
                        items: [User::current()],
                        projection: fn($user) => [
                            $event->isLocked ? '' : new InputAction(
                                actionUrl: "/events/$id/user/join",
                                inputName: 'comment',
                                inputValue: '',
                                title: $eventFull ? 'Warteliste' : 'Anmelden',
                                icon: 'fa-user-plus',
                                inputPlaceholder: 'Kommentar',
                                color: $eventFull ? Color::Accent : Color::Primary,
                                confirmMessage: $eventFull ? 'Auf Warteliste setzen?' : 'Anmelden?',
                                hiddenInputs: ['userId' => $user->id]
                            ),
                        ],
                        widths: [null]
                    );
                }
                return;
            }
            yield new Table(
                columns: [$event->isLocked ? 'Anmeldung geschlossen' : 'Kommentar', ''],
                items: [User::current()],
                projection: fn($user) => [
                    new InputAction(
                        actionUrl: "/events/$id/user/join",
                        inputName: 'comment',
                        inputValue: $reg->comment,
                        title: 'Speichern',
                        icon: 'fa-save',
                        inputPlaceholder: 'Kommentar',
                        color: $event->isLocked ? Color::None : Color::Primary,
                        confirmMessage: $event->isLocked ? '' : 'Kommentar aktuallisieren?',
                        hiddenInputs: ['userId' => $user->id]
                    ),
                    new IconActionButton(
                        actionUrl: "/events/$event->id/user/remove",
                        title: 'Abmelden',
                        color: $event->isLocked ? Color::None : Color::Accent,
                        icon: 'fa-user-minus',
                        confirmMessage: $event->isLocked ? '' : "Abmelden?",
                        hiddenInputs: ['userId' => $user->id]
                    )
                ],
                widths: [null, 1]
            );
        }
    );

    if ($eventRegistrations) {
        yield new Card(
            'Angemeldet',
            new Table(
                [''],
                $eventRegistrations,
                fn($user) => [
                    $user->name . ($user->state == 1 ? '' : ' (Warteliste)'),
                ],

            )
        );
    }
});
