<?php

namespace TP\Components;

use TP\Components\Table;
use TP\Components\InputAction;
use TP\Components\IconActionButton;
use TP\Components\Div;

class EventRegistrations extends \Component
{
    /**
     * @param EventRegistration[] $eventUsers An array of EventUser objects to be processed.
     */
    public function __construct(
        public readonly array $eventUsers,
        public readonly Event $event
    ) {
    }

    protected function template(): void
    {
        echo new Table(
            ['Benutzer', 'Kommentar', ''],
            $this->eventUsers,
            fn($user) => [
                fn() => new Div(
                    content: [
                        new Div($user->name),
                        "<small style=\"font-size:0.8em\">{$user->ago}</small>"
                    ]
                ),
                new InputAction(
                    actionUrl: "/events/{$this->event->id}/user/comment",
                    title: 'Kommentar',
                    icon: 'fa-save',
                    inputName: 'comment',
                    inputValue: $user->comment,
                    inputPlaceholder: 'Kommentar',
                    confirmMessage: '',
                    hiddenInputs: [
                        'userId' => $user->userId
                    ]
                ),
                new IconActionButton(
                    actionUrl: "/events/{$this->event->id}/user/remove",
                    title: 'Abmelden',
                    color: Color::Accent,
                    icon: 'fa-user-minus',
                    confirmMessage: "{$user->name} abmelden?",
                    hiddenInputs: ['userId' => $user->userId]
                )
            ],
            widths: [200, null, 1]
        );
    }
}
