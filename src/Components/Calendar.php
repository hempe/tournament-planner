<?php

namespace TP\Components;

date_default_timezone_set('Atlantic/Canary');

use TP\Components\Div;
use TP\Components\Card;
use TP\Components\CalendarEvent;
use TP\Components\Color;
use TP\Components\IconActionButton;
use TP\Models\Event;
use TP\Models\User;
use TP\Core\Translator;
use TP\Core\Url;

class Calendar extends Component
{
    private readonly CalendarDay $_active;

    private function getDaysNames(): array
    {
        return [
            __('calendar.weekdays.monday'),
            __('calendar.weekdays.tuesday'),
            __('calendar.weekdays.wednesday'),
            __('calendar.weekdays.thursday'),
            __('calendar.weekdays.friday'),
            __('calendar.weekdays.saturday'),
            __('calendar.weekdays.sunday'),
        ];
    }

    /** @var Event[] */
    private readonly array $_events;

    private readonly \Closure $eventRenderer;

    /**
     * Calendar constructor.
     *
     * @param \DateTime $date The date for the calendar.
     * @param array $events List of events for the calendar.
     * @param callable|null $eventRenderer Optional renderer; defaults to CalendarEvent.
     */
    public function __construct(\DateTime $date, array $events, ?callable $eventRenderer = null)
    {
        $this->_active = new CalendarDay(
            $date ? $date->format('d') : date('d'),
            $date ? $date->format('m') : date('m'),
            $date ? $date->format('Y') : date('Y'),
            true
        );
        $this->_events = $events;
        $this->eventRenderer = \Closure::fromCallable($eventRenderer ?? fn(Event $event) => new CalendarEvent($event));
    }

    /**
     * Get the previous month.
     *
     * @return string The previous month in 'Y-m-d' format.
     */
    public function prevMonth(): string
    {
        return date('Y-m-d', strtotime(1 . '-' . $this->_active->month . '-' . $this->_active->year . ' -1 months'));
    }

    /**
     * Get the next month.
     *
     * @return string The next month in 'Y-m-d' format.
     */
    public function nextMonth(): string
    {
        return date('Y-m-d', strtotime(1 . '-' . $this->_active->month . '-' . $this->_active->year . ' +1 months'));
    }

    /**
     * Get the formatted date.
     *
     * @return string The formatted date.
     */
    public function formattedDate(): string
    {

        $date = $this->_active->time;
        $formatter = new \IntlDateFormatter(Translator::getInstance()->getLocale(), \IntlDateFormatter::FULL, \IntlDateFormatter::FULL);
        $formatter->setPattern('LLLL Y');
        return $formatter->format($date);
    }

    /**
     * Get the calendar weeks.
     *
     * @return CalendarWeek[] The weeks of the active month.
     */
    public function weeks(): array
    {
        $year = $this->_active->year;
        $month = $this->_active->month;

        $firstOfMonth = new \DateTime("$year-$month-1");
        $daysInMonth = (int) $firstOfMonth->format('t');
        $firstDayOfWeek = (int) $firstOfMonth->format('N') - 1; // Monday as index 0
        $daysInLastMonth = (int) (new \DateTime("$year-$month-1 -1 day"))->format('t');

        $weeks = [];
        $week = [];

        // Fill previous month days
        for ($i = $firstDayOfWeek; $i > 0; $i--) {
            $week[] = new CalendarDay(
                $daysInLastMonth - $i + 1,
                $month - 1 ?: 12,
                $month === 1 ? $year - 1 : $year,
                false
            );
        }

        // Fill current month days
        for ($i = 1; $i <= $daysInMonth; $i++) {
            if (count($week) === 7) {
                $weeks[] = new CalendarWeek($week);
                $week = [];
            }
            $week[] = new CalendarDay($i, $month, $year, true);
        }

        // Fill next month days
        for ($i = 1; count($week) < 7; $i++) {
            $week[] = new CalendarDay(
                $i,
                $month + 1 > 12 ? 1 : $month + 1,
                $month === 12 ? $year + 1 : $year,
                false
            );
        }
        $weeks[] = new CalendarWeek($week);

        return $weeks;
    }

    /**
     * Get the names of the days.
     *
     * @return string[] The names of the days.
     */
    public function daysNames(): array
    {
        return $this->getDaysNames();
    }

    /**
     * Get events for a specific day.
     *
     * @param int $day The day of the month.
     * @return Event[] An array of Event objects.
     */
    public function events(CalendarDay $date): array
    {
        $filteredEvents = [];
        foreach ($this->_events as $event) {
            if ($date->time == strtotime($event->date)) {
                $filteredEvents[] = $event;
            }
        }
        return $filteredEvents;
    }

    protected function template(): void
    {
        // Build iframe-specific controls
        $isIframeMode = isset($_GET['iframe']) && $_GET['iframe'] === '1';

        $logoutButton = (User::loggedIn() && $isIframeMode)
            ? new IconActionButton(
                "/logout",
                __('nav.logout'),
                Color::Primary,
                'fa-sign-out',
                confirmMessage: '',
                style: 'padding: 6px 10px; font-size: 0.9rem;',
                title_inline: true
            )
            : '';

        $loginButton = (!User::loggedIn() && $isIframeMode)
            ? new IconButton(
                title: __('auth.login'),
                onClick: "window.location.href='" . Url::build('/login') . "'",
                icon: 'fa-sign-in',
                type: 'button',
                color: Color::Primary,
                style: 'padding: 6px 10px; font-size: 0.9rem;',
                title_inline: true
            )
            : '';

        // Preserve iframe parameter in navigation
        $isIframeMode = isset($_GET['iframe']) && $_GET['iframe'] === '1';
        $iframeParam = $isIframeMode ? '&iframe=1' : '';

        echo new Div(
            class: 'calendar',
            content: new Card(
                title: [
                    new IconButton(
                        title: __('calendar.previous_month'),
                        onClick: "window.location.href='./?date={$this->prevMonth()}{$iframeParam}'",
                        icon: 'fa-chevron-left',
                        type: 'button',
                        color: Color::None,
                    ),
                    "<span>{$this->formattedDate()}</span>",
                    new IconButton(
                        title: __('calendar.next_month'),
                        onClick: "window.location.href='./?date={$this->nextMonth()}{$iframeParam}'",
                        icon: 'fa-chevron-right',
                        type: 'button',
                        color: Color::None,
                    ),
                    $logoutButton,
                    $loginButton
                ],
                content: new Div(
                    class: 'view',
                    content: function () {
                        yield new Div(
                            class: 'day_names',
                            content: function () {
                                foreach ($this->daysNames() as $day) {
                                    yield "<div class='day_name'>$day</div>";
                                }
                            }
                        );
                        $weeks = $this->weeks();
                        $weeks_ctn = count($weeks);
                        yield new Div(
                            class: 'days',
                            style: [
                                'grid-template-columns: repeat(7, 1fr);',
                                "grid-template-rows: repeat({$weeks_ctn}, 1fr);",
                                'height: 100%;'
                            ],
                            content: function () use ($weeks) {
                                foreach ($weeks as $week) {
                                    foreach ($week->days as $day) {
                                        $events = $this->events($day);
                                        yield new Div(
                                            class: function () use ($events, $day) {
                                                yield 'day_num';
                                                if (count($events) == 0)
                                                    yield 'empty';
                                                if ($day->active)
                                                    yield 'active';
                                            },
                                            content: function () use ($day, $events) {
                                                yield new Div(
                                                    content: $day->day . '.' . $day->month . '.' . $day->year,
                                                    class: 'day_date'
                                                );

                                                yield "<span>{$day->day}</span>";

                                                foreach ($events as $event) {
                                                    yield ($this->eventRenderer)($event);
                                                }

                                                if (User::admin() && $day->active)
                                                    yield new IconButton(
                                                        title: __('events.add'),
                                                        onClick: "window.location.href='/events/new?date={$day->str}&b={$day->year}-{$day->month}-1'",
                                                        icon: 'fa-plus',
                                                        type: 'button',
                                                        color: Color::None,
                                                        class: 'add'
                                                    );
                                            }
                                        );
                                    }
                                }
                            }
                        );
                    }
                )
            )
        );
    }
}

class CalendarDay
{
    public readonly int $time;
    public readonly string $str;

    public function __construct(
        public readonly int $day,
        public readonly int $month,
        public readonly int $year,
        public readonly bool $active
    ) {
        $this->time = strtotime("$year-$month-$day");
        $this->str = $this->year . '-' . $this->month . '-' . $this->day;
    }
}

class CalendarWeek
{
    /** @param CalendarDay[] $days */
    public function __construct(public array $days)
    {
    }
}
