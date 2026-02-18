<?php

namespace TP\Components;

use TP\Components\Icon;
use TP\Components\Div;
use TP\Core\Url;
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
        $canRegister = !$this->event->isLocked && $this->event->available > 0;

        $style = $canRegister ? 'cursor: pointer;' : 'cursor: default;';
        $url = Url::build("/events/{$this->event->id}/guests/new?b={$this->event->date}");
        $onclick = $canRegister ? " onclick=\"window.location.href='{$url}'\"" : '';

        $inner = new Div(
            class: ['event-desc'],
            content: "{$this->event->name}<br><small>{$statusText}</small>"
        );
        $status = new Div(
            class: 'event-status',
            content: $this->event->isLocked ? new Icon('fa-lock', __('events.locked')) : ''
        );

        echo "<div class='event'><div class='event-date {$statusClass}' style='{$style}'{$onclick}>{$inner}{$status}</div></div>";
    }
}
