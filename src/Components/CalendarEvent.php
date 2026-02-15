<?php

namespace TP\Components;

use TP\Components\IconButton;
use TP\Components\Icon;
use TP\Components\Div;
use TP\Components\Link;
use TP\Components\Color;
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
            return __('events.spots_available', ['count' => $this->event->available]);

        if ($this->event->isLocked)
            return __('events.locked');

        if (!$this->canJoin())
            return __('events.locked');

        return match ($this->event->available) {
            0 => __('events.waitlist_available'),
            1 => __('events.spot_available', ['count' => $this->event->available]),
            default => __('events.spots_available', ['count' => $this->event->available])
        };
    }

    private function canJoin(): bool
    {
        return date("Y-m-d") <= date('Y-m-d', strtotime($this->event->date));
    }

    protected function template(): void
    {
        // Preserve iframe parameter if present
        $isIframeMode = isset($_GET['iframe']) && $_GET['iframe'] === '1';
        $href = "/events/{$this->event->id}?b={$this->event->date}";
        if ($isIframeMode) {
            $href .= '&iframe=1';
        }

        echo new Div(
            class: 'event',
            content: new Link(
                href: $href,
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
                                    yield new Icon('fa-user-check', __('events.registered'));
                                else if ($this->event->userState == 2)
                                    yield new Icon('fa-user-clock', __('events.on_waitlist'));

                                if ($this->event->isLocked)
                                    yield new Icon('fa-lock', __('events.locked'));
                                else if ($this->event->userState == 0 && $this->canJoin())
                                    yield new IconButton(
                                        title: $this->event->available > 0 ? __('events.register') : __('events.waitlist'),
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
