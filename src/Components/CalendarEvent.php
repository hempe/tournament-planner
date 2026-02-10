<?php

namespace TP\Components;

use TP\Components\IconButton;
use TP\Components\Icon;
use TP\Components\Div;
use TP\Components\Link;
use TP\Models\Event;

class CalendarEvent extends Component
{
    public function __construct(
        public readonly Event $event,
    ) {
    }

    private function eventStatusText(): string
    {
        if ($this->event->userState != 0)
            return "{$this->event->available} Plätze frei";

        if ($this->event->isLocked)
            return 'Gesprerrt';

        if (!$this->canJoin())
            return 'Gesprerrt';

        return match ($this->event->available) {
            0 => "Warteliste verfügbar",
            1 => "{$this->event->available} Platz frei",
            default => "{$this->event->available} Plätze frei"
        };
    }

    private function canJoin(): bool
    {
        return date("Y-m-d") <= date('Y-m-d', strtotime($this->event->date));
    }

    protected function template(): void
    {
        echo new Div(
            class: 'event',
            content: new Link(
                href: "/events/{$this->event->id}?b={$this->event->date}",
                content: new Div(
                    style: [
                        $this->canJoin() ? 'cursor: pointer;' : 'cursor: not-allowed;',
                        $this->event->userState == 1
                        ? 'background-color: var(--button-primary-light-bg);' : (
                            $this->event->userState == 2
                            ? 'background-color: var(--button-accent-light-bg);' : (
                                $this->canJoin()
                                ? 'opacity: 1;'
                                : 'opacity: 0.5;'
                            )
                        ),
                    ],
                    class: ['event-date'],
                    content: function () {
                        if ($eventName = $this->event->name)
                            yield new Div(
                                class: 'event-desc',
                                content: "{$eventName}<br><small>{$this->eventStatusText()}</small>"
                            );
                        yield new Div(
                            class: 'event-status',
                            content: function () {
                                if ($this->event->userState == 1)
                                    yield new Icon('fa-user-check', 'Angemeldet');
                                else if ($this->event->userState == 2)
                                    yield new Icon('fa-user-clock', 'Auf Warteliste');

                                if ($this->event->isLocked)
                                    yield new Icon('fa-lock', 'Gesperrt');
                                else if ($this->event->userState == 0 && $this->canJoin())
                                    yield new IconButton(
                                        title: $this->event->available > 0 ? 'Anmelden' : 'Warteliste',
                                        type: '',
                                        icon: 'fa-user-plus',
                                        color: $this->event->available > 0 ? Color::Primary : Color::Accent,
                                    );
                            }
                        );
                    }
                )
            )

        );
    }
}
