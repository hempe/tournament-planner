<?php

use TP\Models\DB;
use TP\Components\Span;
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
use TP\Core\Translator;

assert(is_int($id));

?>
<?= new Page(
    function () use ($id) {
        $formatter = new IntlDateFormatter(Translator::getInstance()->getLocale(), IntlDateFormatter::FULL, IntlDateFormatter::NONE);

        $event = DB::$events->get(
            $id,
            $_SESSION['user_id']
        );
        if (!$event) {
            logger()->debug('Redirecting to /events because we could not find the event: ' . $id);
            header("Location: /events", true, 303);
            exit;
        }

        $formattedDate = $formatter->format(strtotime($event->date));
        $eventRegistrations = array_reduce(DB::$events->registrations($event->id), function ($result, $item) {
            $result[$item->state][] = $item;
            return $result;
        }, []);

        // Build query string for preserving parameters
        $queryParams = [];
        if (isset($_GET['iframe']) && $_GET['iframe'] === '1') {
            $queryParams[] = 'iframe=1';
        }
        if ($backDate = $_GET['b'] ?? null) {
            $queryParams[] = 'b=' . urlencode($backDate);
        }
        $queryString = !empty($queryParams) ? '?' . implode('&', $queryParams) : '';

        $eventFull = $event->available <= 0;

        yield new Form(
            action: "/events/$id",
            content: new Card(
                title: $formattedDate . ': ' . $event->name . ' · ' . ($event->mixed ? __('events.mixed') : __('events.separate')),
                content: new Table(
                    ['Name', 'Max. Teilnehmer', '', '', '', ''],
                    [$event],
                    fn($event) => [
                        new Input(
                            type: 'text',
                            value: $event->name,
                            name: 'name',
                            placeholder: __('events.name'),
                            style: 'flex-grow:1;',
                            required: true
                        ),
                        new Input(
                            type: 'text',
                            value: $event->capacity,
                            name: 'capacity',
                            placeholder: __('events.max_participants'),
                            style: 'flex-grow:1;',
                            required: true
                        ),
                        new IconButton(
                            title: __('events.save'),
                            type: 'submit',
                            icon: 'fa-save',
                            color: Color::Primary,
                        ),
                        new IconActionButton(
                            actionUrl: "/events/$id/delete",
                            title: __('events.delete'),
                            color: Color::Accent,
                            icon: 'fa-trash',
                            confirmMessage: __('events.delete_confirm_short'),
                        ),
                        $event->locked
                        ? new IconActionButton(
                            actionUrl: "/events/$id/unlock",
                            title: __('events.unlock'),
                            color: Color::Light,
                            icon: 'fa-lock',
                            confirmMessage: __('events.unlock_confirm'),
                        )
                        : new IconActionButton(
                            actionUrl: "/events/$id/lock",
                            title: __('events.lock'),
                            color: Color::Light,
                            icon: 'fa-unlock',
                            confirmMessage: __('events.lock_confirm'),
                        ),
                        new IconButton(
                            title: __('events.export'),
                            onClick: "window.location.href='/events/$id/export'",
                            icon: 'fa-download',
                            type: 'button',
                            color: Color::Light,
                        ),
                    ],
                    widths: [null, null, 1, 1, 1, 1]
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
                __('events.waitlist'),
                new EventRegistrations($eventRegistrations['2'] ?? [], $event)
            );
        }

        $guests = DB::$guests->allForEvent($id);

        yield new Card(
            [
                new Span(
                    content: __('guests.title'),
                    style: 'flex-grow:1'
                ),
                new IconButton(
                    title: __('guests.add'),
                    onClick: "window.location.href='/events/$id/guests/new'",
                    icon: 'fa-user-plus',
                    type: 'button',
                    color: Color::Primary,
                ),
            ],
            count($guests) > 0
            ? new Table(
                [__('guests.first_name'), __('guests.last_name'), __('guests.email'), __('guests.handicap'), __('guests.rfeg'), __('guests.comment'), '', ''],
                $guests,
                fn($guest) => [
                    $guest->firstName,
                    $guest->lastName,
                    $guest->email,
                    $guest->handicap,
                    $guest->rfeg ?? '',
                    $guest->comment ?? '',
                    new IconButton(
                        title: __('events.edit'),
                        onClick: "window.location.href='/events/$id/guests/{$guest->id}/edit'",
                        icon: 'fa-edit',
                        type: 'button',
                        color: Color::Light,
                    ),
                    new IconActionButton(
                        actionUrl: "/events/$id/guests/{$guest->id}/delete",
                        title: __('events.delete'),
                        color: Color::Accent,
                        icon: 'fa-trash',
                        confirmMessage: __('guests.delete_confirm'),
                    ),
                ],
                widths: [null, null, null, 1, null, null, 1, 1]
            )
            : ''
        );

        $users = DB::$events->availableUsers($id);
        $formatter = new IntlDateFormatter(Translator::getInstance()->getLocale(), IntlDateFormatter::FULL, IntlDateFormatter::NONE);

        if (count($users) && !$event->isLocked) {
            yield new Card(
                __('auth.not_registered'),
                new Table(
                    [__('events.user'), __('events.comment')],
                    $users,
                    function ($user) use ($id, $eventFull, $queryString) {
                        return [
                            $user->username,
                            new InputAction(
                                actionUrl: "/events/$id/register{$queryString}",
                                inputName: 'comment',
                                inputValue: '',
                                title: $eventFull ? __('events.waitlist') : __('events.register'),
                                icon: 'fa-user-plus',
                                inputPlaceholder: __('events.comment'),
                                color: $eventFull ? Color::Accent : Color::Primary,
                                confirmMessage: $eventFull ? __('auth.register_user_waitlist', ['username' => $user->username]) : __('auth.register_user', ['username' => $user->username]),
                                hiddenInputs: ['userId' => $user->id]
                            ),
                        ];
                    },
                    widths: [200, null]
                )
            );
        }
    }
);
