<?php

namespace TP\Components;

use TP\Components\Icon;
use TP\Components\Div;
use TP\Models\Event;

class GuestCalendarEvent extends Component
{
    public function __construct(
        public readonly Event $event,
    ) {
    }

    private function statusClass(): string
    {
        if ($this->event->isLocked) {
            return 'locked';
        }
        if ($this->event->available <= 0) {
            return 'cant-join';
        }
        return 'can-join';
    }

    private function statusText(): string
    {
        if ($this->event->isLocked) {
            return __('events.locked');
        }
        if ($this->event->available <= 0) {
            return __('events.full');
        }
        return __('events.available');
    }

    protected function template(): void
    {
        $statusClass = $this->statusClass();
        $statusText = $this->statusText();

        echo new Div(
            class: 'event',
            content: new Div(
                style: ['cursor: default;'],
                class: ['event-date', $statusClass],
                content: function () use ($statusText) {
                    if ($eventName = $this->event->name)
                        yield new Div(
                            class: 'event-desc',
                            content: "{$eventName}<br><small>{$statusText}</small>"
                        );
                    yield new Div(
                        class: 'event-status',
                        content: function () {
                            if ($this->event->isLocked)
                                yield new Icon('fa-lock', __('events.locked'));
                        }
                    );
                }
            )
        );
    }
}
