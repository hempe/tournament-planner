<?php

namespace TP\Components;

use TP\Models\SocialEvent;

class SocialCalendarEvent extends Component
{
    public function __construct(
        public readonly SocialEvent $event,
    ) {}

    private function canJoin(): bool
    {
        return date("Y-m-d") <= date('Y-m-d', strtotime($this->event->date));
    }

    private function statusText(): string
    {
        if ($this->event->isLocked) {
            return __('social_events.locked_message');
        }
        if ($this->event->available <= 0) {
            return __('social_events.full');
        }
        return __('social_events.available', ['count' => $this->event->available]);
    }

    protected function template(): void
    {
        echo new Div(
            class: 'event',
            content: new Link(
                href: "/social-events/{$this->event->id}",
                content: new Div(
                    style: [
                        $this->canJoin() ? 'cursor: pointer;' : 'cursor: not-allowed;',
                    ],
                    class: [
                        'event-date',
                        'social-event',
                        $this->event->isLocked ? 'locked' : '',
                        $this->event->userRegistered ? 'joined' : ($this->canJoin() ? 'can-join' : 'cant-join'),
                    ],
                    content: function () {
                        yield new Div(
                            class: 'event-desc',
                            content: "{$this->event->name}<br><small>{$this->statusText()}</small>"
                        );
                        yield new Div(
                            class: 'event-status',
                            content: function () {
                                if ($this->event->userRegistered) {
                                    yield new Icon('fa-user-check', __('social_events.registered'));
                                }
                                if ($this->event->isLocked) {
                                    yield new Icon('fa-lock', __('social_events.locked_message'));
                                } elseif (!$this->event->userRegistered && $this->canJoin() && $this->event->available > 0) {
                                    yield new IconButton(
                                        title: __('social_events.register'),
                                        type: '',
                                        icon: 'fa-user-plus',
                                        color: Color::Social,
                                    );
                                }
                            }
                        );
                    }
                )
            )
        );
    }
}
