<?php

use TP\Models\DB;
use TP\Models\Event;
use TP\Components\Checkbox;
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
use TP\Components\Textarea;
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
        if ($backDate = $_GET['b'] ?? null) {
            $queryParams[] = 'b=' . urlencode($backDate);
        }
        $queryString = !empty($queryParams) ? '?' . implode('&', $queryParams) : '';

        $eventFull = $event->available <= 0;

        $cardTitle = [
            new IconButton(
                title: __('nav.back'),
                href: "/events/$id{$queryString}",
                icon: 'fa-chevron-left',
                type: 'button',
                color: Color::None,
            ),
            new Span(
                content: $formattedDate,
                style: 'flex-grow:1'
            )
        ];

        yield new Form(
            action: "/events/$id",
            content: new Card(
                title: $cardTitle,
                content: new Card(
                    title: [
                        new Span(content: $event->name, style: 'flex-grow:1'),
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
                        $event->isLocked
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
                            href: "/events/$id/export",
                            icon: 'fa-download',
                            type: 'button',
                            color: Color::Light,
                        ),
                    ],
                    content: new Table(
                        columns: ['', ''],
                        items: [0, 1, 2, 3, 4, 5, 6],
                        projection: fn($i) => match ($i) {
                            0 => [__('events.name'), new Input(
                                type: 'text',
                                value: $event->name,
                                name: 'name',
                                placeholder: __('events.name'),
                                required: true,
                            )],
                            1 => [__('events.max_participants'), new Input(
                                type: 'number',
                                value: (string) $event->capacity,
                                name: 'capacity',
                                placeholder: __('events.max_participants'),
                                required: true,
                            )],
                            2 => [__('events.play_together'), new Checkbox(
                                name: 'mixed',
                                label: '',
                                checked: $event->mixed,
                            )],
                            3 => [__('events.description'), new Textarea(
                                name: 'description',
                                value: $event->description ?? '',
                                placeholder: __('events.description'),
                                style: 'width:100%',
                            )],
                            4 => [__('events.price_members'), new Input(
                                type: 'number',
                                name: 'price_members',
                                value: $event->priceMembers !== null ? (string) $event->priceMembers : '',
                                placeholder: __('events.price_members'),
                                step: '0.01',
                            )],
                            5 => [__('events.price_guests'), new Input(
                                type: 'number',
                                name: 'price_guests',
                                value: $event->priceGuests !== null ? (string) $event->priceGuests : '',
                                placeholder: __('events.price_guests'),
                                step: '0.01',
                            )],
                            6 => [__('events.registration_close'), new Input(
                                type: 'datetime-local',
                                name: 'registration_close',
                                value: $event->registrationClose ? substr(str_replace(' ', 'T', $event->registrationClose), 0, 16) : '',
                                placeholder: __('events.registration_close'),
                            )],
                        },
                        widths: [150, null]
                    )
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
            [__('guests.first_name'), __('guests.last_name'), __('guests.email'), __('guests.handicap'), __('guests.comment'), '', ''],
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
                $guest->comment ?? '',
                new IconButton(
                    title: __('events.edit'),
                    href: "/events/$id/guests/{$guest->id}/edit",
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
            widths: [null, null, null, 1, null, 1, 1]
        );

        $guestHeader = [
            new Span(content: __('guests.title'), style: 'flex-grow:1'),
            new IconButton(
                title: __('guests.add'),
                href: "/events/$id/guests/new",
                icon: 'fa-user-plus',
                type: 'button',
                color: Color::Primary,
            ),
        ];

        if ($event->mixed) {
            yield new Card(
                title: $guestHeader,
                content: count($guests) > 0 ? $guestTable($guests) : ''
            );
        } else {
            $maleGuests = array_values(array_filter($guests, fn($g) => $g->male));
            $femaleGuests = array_values(array_filter($guests, fn($g) => !$g->male));
            yield new Card(
                array_merge([new Span(content: __('events.male') . ' – ' . __('guests.title'), style: 'flex-grow:1')], [
                    new IconButton(
                        title: __('guests.add'),
                        href: "/events/$id/guests/new",
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
                title: __('auth.not_registered'),
                content: new Table(
                    columns: [__('events.user'), __('events.comment')],
                    items: $users,
                    projection: function ($user) use ($id, $eventFull, $queryString) {
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

        $socialEvent = DB::$socialEvents->getForTournament($id);
        if ($socialEvent) {
            $socialFormatter = new IntlDateFormatter(Translator::getInstance()->getLocale(), IntlDateFormatter::FULL, IntlDateFormatter::NONE);
            yield new Card(
                [
                    new Span(content: __('social_events.title') . ' – ' . $socialFormatter->format(strtotime($socialEvent->date)), style: 'flex-grow:1'),
                    new IconButton(
                        title: __('social_events.edit'),
                        href: "/social-events/{$socialEvent->id}/admin",
                        icon: 'fa-edit',
                        type: 'button',
                        color: Color::Light,
                    ),
                ],
                ''
            );
        } else {
            yield new Card(
                [
                    new Span(content: __('social_events.title'), style: 'flex-grow:1'),
                    new IconButton(
                        title: __('social_events.new'),
                        href: "/social-events/new?tournamentId=$id",
                        icon: 'fa-plus',
                        type: 'button',
                        color: Color::Primary,
                    ),
                ],
                ''
            );
        }
    }
);
