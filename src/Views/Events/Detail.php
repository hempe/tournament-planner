<?php

use TP\Models\DB;
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

    // Build URLs with preserved query parameters
    $isIframeMode = isset($_GET['iframe']) && $_GET['iframe'] === '1';
    $backUrl = isset($_GET['b']) ? '/?date=' . $_GET['b'] : '/';
    $queryParams = [];

    if ($isIframeMode) {
        $queryParams[] = 'iframe=1';
        $backUrl .= (strpos($backUrl, '?') !== false ? '&' : '?') . 'iframe=1';
    }

    if ($backDate = $_GET['b'] ?? null) {
        $queryParams[] = 'b=' . urlencode($backDate);
    }

    $queryString = !empty($queryParams) ? '?' . implode('&', $queryParams) : '';

    $cardTitle = $isIframeMode
        ? [
            new IconButton(
                title: __('nav.back'),
                onClick: "window.location.href='$backUrl'",
                icon: 'fa-chevron-left',
                type: 'button',
                color: Color::None,
            ),
            "<span style=\"flex-grow:1\">$formattedDate: {$event->name} {$regState}</span>"
        ]
        : "$formattedDate: {$event->name} {$regState}";

    yield new Card(
        $cardTitle,
        function () use ($id, $event, $eventFull, $reg, $queryString, $isIframeMode) {
            if (!$reg) {
                if (!$event->isLocked) {
                    yield new Table(
                        columns: [__('events.register')],
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
                                title_inline: $isIframeMode
                            ),
                        ],
                        widths: [null]
                    );
                }
                return;
            }
            yield new Table(
                columns: [$event->isLocked ? __('events.locked_message') : __('events.comment_update'), ''],
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
                        title_inline: $isIframeMode
                    ),
                    new IconActionButton(
                        actionUrl: "/events/$event->id/unregister{$queryString}",
                        title: __('events.unregister'),
                        color: $event->isLocked ? Color::None : Color::Accent,
                        icon: 'fa-user-minus',
                        confirmMessage: $event->isLocked ? '' : __('events.unregister_confirm'),
                        hiddenInputs: ['userId' => $user->id],
                        title_inline: $isIframeMode
                    )
                ],
                widths: [null, 1]
            );
        }
    );

    if ($eventRegistrations) {
        $registeredTitle = __('events.registered');
        yield new Card(
            $isIframeMode ?
            "<span style=\"flex-grow:1\">$registeredTitle</span>"
            : $registeredTitle,
            new Table(
                [''],
                $eventRegistrations,
                fn(EventRegistration $user) => [
                    $user->name . ($user->state == 1 ? '' : ' (' . __('events.waitlist') . ')'),
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
                fn(EventGuest $guest) => [$guest->firstName . ' ' . $guest->lastName],
            )
        );
    }
});
