<?php

namespace TP\Components;

use TP\Components\Color;
use TP\Components\Table;
use TP\Components\InputAction;
use TP\Components\IconActionButton;
use TP\Components\Div;
use TP\Components\Small;
use TP\Models\Event;
use TP\Models\EventRegistration;

class EventRegistrations extends Component
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
        // Build query string for preserving parameters
        $queryParams = [];
        if (isset($_GET['iframe']) && $_GET['iframe'] === '1') {
            $queryParams[] = 'iframe=1';
        }
        if ($backDate = $_GET['b'] ?? null) {
            $queryParams[] = 'b=' . urlencode($backDate);
        }
        $queryString = !empty($queryParams) ? '?' . implode('&', $queryParams) : '';

        echo new Table(
            [__('events.user'), __('events.comment'), ''],
            $this->eventUsers,
            function ($user) use ($queryString) {
                return [
                    fn() => new Div(
                        content: [
                            new Div($user->name),
                            new Small(content: $user->ago, style: 'font-size:0.8em'),
                        ]
                    ),
                    new InputAction(
                        actionUrl: "/events/{$this->event->id}/comment{$queryString}",
                        title: __('events.comment'),
                        icon: 'fa-save',
                        inputName: 'comment',
                        inputValue: $user->comment,
                        inputPlaceholder: __('events.comment'),
                        confirmMessage: '',
                        hiddenInputs: [
                            'userId' => $user->userId
                        ]
                    ),
                    new IconActionButton(
                        actionUrl: "/events/{$this->event->id}/unregister{$queryString}",
                        title: __('events.unregister'),
                        color: Color::Accent,
                        icon: 'fa-user-minus',
                        confirmMessage: __('events.unregister_user_confirm', ['name' => $user->name]),
                        hiddenInputs: ['userId' => $user->userId]
                    )
                ];
            },
            widths: [200, null, 1]
        );
    }
}
