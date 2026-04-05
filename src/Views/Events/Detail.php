<?php

use TP\Models\DB;
use TP\Components\Span;
use TP\Components\Page;
use TP\Components\Table;
use TP\Components\Card;
use TP\Components\Color;
use TP\Components\Icon;
use TP\Components\IconButton;
use TP\Components\InputAction;
use TP\Components\IconActionButton;
use TP\Core\Translator;
use TP\Models\EventRegistration;
use TP\Models\User;
use TP\Models\EventGuest;
use TP\Components\Div;

assert(is_int($id));

?>

<?= new Page(function () use ($id) {
    $formatter = new \IntlDateFormatter(Translator::getInstance()->getLocale(), \IntlDateFormatter::FULL, \IntlDateFormatter::NONE);

    $event = DB::$events->get(
        $id,
        $_SESSION['user_id']
    );
    if (!$event) {
        logger()->debug('Redirecting to / because we could not find the event');
        header("Location: /", true, 303);
        exit;
    }

    $formattedDate = $formatter->format(strtotime($event->date));
    $eventRegistrations = DB::$events->registrations($event->id);

    $eventFull = $event->available <= 0;
    $filteredRegs = array_filter($eventRegistrations, fn($reg) => $reg->userId == User::id());
    $reg = !empty($filteredRegs) ? array_values($filteredRegs)[0] : null;

    $regState = !$reg
        ? ''
        : ($reg->state == 1
            ? new Icon('fa-user-check', __('events.registered'))
            : new Icon('fa-user-clock', __('events.on_waitlist'))
        );

    $socialEvent = DB::$socialEvents->getForTournament($event->id);

    // Build URLs with preserved query parameters
    $backUrl = isset($_GET['b']) ? '/?date=' . $_GET['b'] : '/';
    $queryParams = [];

    if ($backDate = $_GET['b'] ?? null) {
        $queryParams[] = 'b=' . urlencode($backDate);
    }

    $queryString = !empty($queryParams) ? '?' . implode('&', $queryParams) : '';
    $cardTitle = [
        new IconButton(
            title: __('nav.back'),
            href: $backUrl,
            icon: 'fa-chevron-left',
            type: 'button',
            color: Color::None,
        ),
        new Span(
            content: $formattedDate,
            style: "flex-grow:1"
        )
    ];

    yield new Card(
        $cardTitle,
        function () use ($id, $event, $eventFull, $reg, $regState, $queryString, $socialEvent) {
            $details = [];
            if ($event->description) {
                $details[] = [__('events.description'), nl2br(htmlspecialchars($event->description))];
            }
            if ($event->priceMembers !== null) {
                $details[] = [__('events.price_members'), number_format($event->priceMembers, 2)];
            }
            if ($event->priceGuests !== null) {
                $details[] = [__('events.price_guests'), number_format($event->priceGuests, 2)];
            }
            if ($event->registrationClose) {
                $closeFormatter = new \IntlDateFormatter(Translator::getInstance()->getLocale(), \IntlDateFormatter::FULL, \IntlDateFormatter::SHORT);
                $details[] = [__('events.registration_close'), $closeFormatter->format(strtotime($event->registrationClose))];
            }
            $innerTitle = [new Span(content: htmlspecialchars($event->name), style: 'flex-grow:1'), $regState];
            if (User::admin()) {
                $innerTitle[] = new IconButton(
                    title: __('events.edit'),
                    href: "/events/$id/admin{$queryString}",
                    icon: 'fa-edit',
                    type: 'button',
                    color: Color::Light,
                );
            }
            yield new Card(
                $innerTitle,
                new Table(['', ''], $details, fn($row) => $row, widths: [150, null])
            );
            yield '<br>';

            if (!$reg) {
                if (!$event->isLocked) {
                    yield new Div(__('events.register'), class: 'card-title');
                    yield new Table(
                        columns: [''],
                        items: [User::current()],
                        projection: fn($user) => [
                            $event->isLocked ? '' : new InputAction(
                                actionUrl: "/events/$id/register{$queryString}",
                                inputName: 'comment',
                                inputValue: '',
                                title: $eventFull ? __('events.waitlist') : __('events.register'),
                                icon: 'fa-user-plus',
                                inputPlaceholder: __('events.comment'),
                                color: $eventFull ? Color::Accent : Color::Primary,
                                confirmMessage: $eventFull ? __('events.join_waitlist') : __('events.register_confirm'),
                                hiddenInputs: ['userId' => $user->id],
                                title_inline: true
                            ),
                        ],
                        widths: [null]
                    );
                }
                return;
            }
            yield new Table(
                columns: [$event->isLocked ? __('events.locked_message') : __('events.comment_update')],
                items: [User::current()],
                projection: fn($user) => [
                    new InputAction(
                        actionUrl: "/events/$id/comment{$queryString}",
                        inputName: 'comment',
                        inputValue: $reg->comment,
                        title: __('events.save'),
                        icon: 'fa-save',
                        inputPlaceholder: __('events.comment'),
                        color: $event->isLocked ? Color::None : Color::Primary,
                        confirmMessage: $event->isLocked ? '' : __('events.comment_update_confirm'),
                        hiddenInputs: ['userId' => $user->id],
                        title_inline: true
                    ),
                ],
                widths: [null]
            );
            yield new Div(
                content: new IconActionButton(
                    actionUrl: "/events/$event->id/unregister{$queryString}",
                    title: __('events.unregister'),
                    color: Color::Accent,
                    icon: 'fa-user-minus',
                    confirmMessage: $socialEvent?->userRegistered ? '' : __('events.unregister_confirm'),
                    hiddenInputs: ['userId' => User::id()],
                    title_inline: true,
                    socialActionUrl: $socialEvent?->userRegistered ? "/social-events/{$socialEvent->id}/unregister" : '',
                ),
                style: [
                    'padding:12px;',
                    'display:flex;',
                    'flex-direction:column;',
                    'align-items:end;'
                ]
            );
        }
    );

    if ($socialEvent) {
        yield new Card(
            new IconButton(
                title: $socialEvent->name,
                href: "/social-events/{$socialEvent->id}",
                icon: 'fa-chevron-right',
                type: 'button',
                color: Color::None,
                title_inline: true,
                style: 'flex-grow:1',
            ),
            '',
            class: 'social',
        );
    }

    if ($eventRegistrations) {
        $registeredTitle = __('events.registered');
        yield new Card(
            new Span(content: $registeredTitle, style: 'flex-grow:1'),
            new Table(
                [''],
                $eventRegistrations,
                fn(EventRegistration $user) => [
                    htmlspecialchars($user->name) . ($user->state == 1 ? '' : ' (' . __('events.waitlist') . ')'),
                ],

            )
        );
    }

    $guests = DB::$guests->allForEvent($event->id);
    if (count($guests) > 0) {
        yield new Card(
            __('guests.title'),
            new Table(
                [''],
                $guests,
                fn(EventGuest $guest) => [htmlspecialchars($guest->firstName) . ' ' . htmlspecialchars($guest->lastName)],
            )
        );
    }
});
