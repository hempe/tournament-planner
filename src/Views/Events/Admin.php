<?php

use TP\Models\DB;
use TP\Components\Div;
use TP\Components\Small;
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
            $result[$item->state][$item->male ? 'male' : 'female'][] = $item;
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
                    [__('events.name'), __('events.max_participants'), '', '', '', '', ''],
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
                        '<input type="hidden" name="mixed" value="0"><label style="display:flex;align-items:center;gap:.4rem;white-space:nowrap;margin-top:5px;"><input type="checkbox" name="mixed" value="1"' . ($event->mixed ? ' checked' : '') . '> ' . __('events.play_together') . '</label>',
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
                    widths: [null, null, 1, 1, 1, 1, 1]
                )
            )
        );

        $registeredMales = $eventRegistrations['1']['male'] ?? [];
        $registeredFemales = $eventRegistrations['1']['female'] ?? [];
        $waitlistMales = $eventRegistrations['2']['male'] ?? [];
        $waitlistFemales = $eventRegistrations['2']['female'] ?? [];

        if ($event->mixed) {
            $registered = array_merge($registeredMales, $registeredFemales);
            if (count($registered)) {
                yield new Card(__('events.participants'), new EventRegistrations($registered, $event));
            }
            $waitlist = array_merge($waitlistMales, $waitlistFemales);
            if (count($waitlist)) {
                yield new Card(__('events.waitlist'), new EventRegistrations($waitlist, $event));
            }
        } else {
            if (count($registeredMales)) {
                yield new Card(__('events.male'), new EventRegistrations($registeredMales, $event));
            }
            if (count($registeredFemales)) {
                yield new Card(__('events.female'), new EventRegistrations($registeredFemales, $event));
            }
            if (count($waitlistMales)) {
                yield new Card(__('events.male') . ' – ' . __('events.waitlist'), new EventRegistrations($waitlistMales, $event));
            }
            if (count($waitlistFemales)) {
                yield new Card(__('events.female') . ' – ' . __('events.waitlist'), new EventRegistrations($waitlistFemales, $event));
            }
        }

        $guests = DB::$guests->allForEvent($id);

        $guestTable = fn(array $guestList) => new Table(
            [__('guests.first_name'), __('guests.last_name'), __('guests.email'), __('guests.handicap'), __('guests.rfeg'), __('guests.comment'), '', ''],
            $guestList,
            fn($guest) => [
                new Div(
                    content: [
                        new Div($guest->firstName, ),
                        new Small(content: $guest->ago, style: 'font-size:0.8em'),
                    ]
                ),
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
        );

        $guestHeader = [
            new Span(content: __('guests.title'), style: 'flex-grow:1'),
            new IconButton(
                title: __('guests.add'),
                onClick: "window.location.href='/events/$id/guests/new'",
                icon: 'fa-user-plus',
                type: 'button',
                color: Color::Primary,
            ),
        ];

        if ($event->mixed) {
            yield new Card($guestHeader, count($guests) > 0 ? $guestTable($guests) : '');
        } else {
            $maleGuests = array_values(array_filter($guests, fn($g) => $g->male));
            $femaleGuests = array_values(array_filter($guests, fn($g) => !$g->male));
            yield new Card(
                array_merge([new Span(content: __('events.male') . ' – ' . __('guests.title'), style: 'flex-grow:1')], [
                    new IconButton(
                        title: __('guests.add'),
                        onClick: "window.location.href='/events/$id/guests/new'",
                        icon: 'fa-user-plus',
                        type: 'button',
                        color: Color::Primary,
                    ),
                ]),
                count($maleGuests) > 0 ? $guestTable($maleGuests) : ''
            );
            if (count($femaleGuests) > 0) {
                yield new Card(__('events.female') . ' – ' . __('guests.title'), $guestTable($femaleGuests));
            }
        }

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
