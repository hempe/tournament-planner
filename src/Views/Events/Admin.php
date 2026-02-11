<?php

use TP\Core\Log;
use TP\Models\DB;
use TP\Components\Color;
use TP\Components\Page;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\IconButton;
use TP\Components\Form;
use TP\Components\Input;
use TP\Components\IconActionButton;
use TP\Components\EventRegistrations;
use TP\Components\InputAction;

?>
<?= new Page(
    function () use ($id) {
        $formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::FULL, IntlDateFormatter::NONE);

        $event = DB::$events->get(
            $id,
            $_SESSION['user_id']
        );
        if (!$event) {
            Log::trace('event/detail', 'Redirecting to /events because we could not find the ' . $id . ' event' . $event);
            header("Location: /events", true, 303);
            exit;
        }

        $formattedDate = $formatter->format(strtotime($event->date));
        $eventRegistrations = array_reduce(DB::$events->registrations($event->id), function ($result, $item) {
            $result[$item->state][] = $item;
            return $result;
        }, []);

        $eventFull = $event->available <= 0;

        yield new Form(
            action: "/events/$id",
            content: new Card(
                title: $formattedDate . ': ' . $event->name,
                content: new Table(
                    ['Name', 'Max. Teilnehmer', '', '', ''],
                    [$event],
                    fn($event) => [
                        new Input(
                            type: 'text',
                            value: $event->name,
                            name: 'name',
                            placeholder: 'Name',
                            style: 'flex-grow:1;',
                            required: true
                        ),
                        new Input(
                            type: 'text',
                            value: $event->capacity,
                            name: 'capacity',
                            placeholder: 'Max. Teilnehmer',
                            style: 'flex-grow:1;',
                            required: true
                        ),
                        new IconButton(
                            title: 'Speichern',
                            type: 'submit',
                            icon: 'fa-save',
                            color: Color::Primary,
                        ),
                        new IconActionButton(
                            actionUrl: "/events/$id/delete",
                            title: 'Löschen',
                            color: Color::Accent,
                            icon: 'fa-trash',
                            confirmMessage: 'Termin definitiv löschen?',
                        ),
                        $event->locked
                        ? new IconActionButton(
                            actionUrl: "/events/$id/unlock",
                            title: 'Entsprrren',
                            color: Color::Light,
                            icon: 'fa-lock',
                            confirmMessage: 'Termin entsperren?',
                        )
                        : new IconActionButton(
                            actionUrl: "/events/$id/lock",
                            title: 'Sperren',
                            color: Color::Light,
                            icon: 'fa-unlock',
                            confirmMessage: 'Termin sperren?',
                        )
                    ],
                    widths: [null, null, 1, 1, 1]
                )
            )
        );

        if (count($eventRegistrations['1'] ?? [])) {
            yield new Card(
                'Teilnehmer',
                new EventRegistrations($eventRegistrations['1'] ?? [], $event)
            );
        }

        if (count($eventRegistrations['2'] ?? [])) {
            yield new Card(
                'Warteliste',
                new EventRegistrations($eventRegistrations['2'] ?? [], $event)
            );
        }

        $users = DB::$events->availableUsers($id);
        $formatter = new IntlDateFormatter('de_DE', IntlDateFormatter::FULL, IntlDateFormatter::NONE);

        if (count($users) && !$event->isLocked) {
            yield new Card(
                'Nicht angemeldet',
                new Table(
                    ['Benutzer', 'Kommentar'],
                    $users,
                    fn($user) => [
                        $user->username,
                        new InputAction(
                            actionUrl: "/events/$id/user/join",
                            inputName: 'comment',
                            inputValue: '',
                            title: $eventFull ? 'Warteliste' : 'Anmelden',
                            icon: 'fa-user-plus',
                            inputPlaceholder: 'Kommentar',
                            color: $eventFull ? Color::Accent : Color::Primary,
                            confirmMessage: "$user->username " . ($eventFull ? 'auf die Warteliste setzen?' : 'anmelden?'),
                            hiddenInputs: ['userId' => $user->id]
                        ),
                    ],
                    widths: [200, null]
                )
            );
        }
    }
);
