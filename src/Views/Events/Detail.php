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
            ? new Icon('fa-user-check', __('events.registered'))
            : new Icon('fa-user-clock', __('events.on_waitlist'))
        );

    yield new Card(
        "$formattedDate: {$event->name} {$regState}",
        function () use ($id, $event, $eventFull, $reg) {
            if (!$reg) {
                if (!$event->isLocked) {
                    yield new Table(
                        columns: [__('events.register')],
                        items: [User::current()],
                        projection: fn($user) => [
                            $event->isLocked ? '' : new InputAction(
                                actionUrl: "/events/$id/user/join",
                                inputName: 'comment',
                                inputValue: '',
                                title: $eventFull ? __('events.waitlist') : __('events.register'),
                                icon: 'fa-user-plus',
                                inputPlaceholder: __('events.comment'),
                                color: $eventFull ? Color::Accent : Color::Primary,
                                confirmMessage: $eventFull ? __('events.join_waitlist') : __('events.register_confirm'),
                                hiddenInputs: ['userId' => $user->id]
                            ),
                        ],
                        widths: [null]
                    );
                }
                return;
            }
            yield new Table(
                columns: [$event->isLocked ? __('events.locked_message') : __('events.comment'), ''],
                items: [User::current()],
                projection: fn($user) => [
                    new InputAction(
                        actionUrl: "/events/$id/user/join",
                        inputName: 'comment',
                        inputValue: $reg->comment,
                        title: __('events.save'),
                        icon: 'fa-save',
                        inputPlaceholder: __('events.comment'),
                        color: $event->isLocked ? Color::None : Color::Primary,
                        confirmMessage: $event->isLocked ? '' : __('events.comment_update_confirm'),
                        hiddenInputs: ['userId' => $user->id]
                    ),
                    new IconActionButton(
                        actionUrl: "/events/$event->id/user/remove",
                        title: __('events.unregister'),
                        color: $event->isLocked ? Color::None : Color::Accent,
                        icon: 'fa-user-minus',
                        confirmMessage: $event->isLocked ? '' : __('events.unregister_confirm'),
                        hiddenInputs: ['userId' => $user->id]
                    )
                ],
                widths: [null, 1]
            );
        }
    );

    if ($eventRegistrations) {
        yield new Card(
            __('events.registered'),
            new Table(
                [''],
                $eventRegistrations,
                fn($user) => [
                    $user->name . ($user->state == 1 ? '' : ' (' . __('events.waitlist') . ')'),
                ],

            )
        );
    }
});
