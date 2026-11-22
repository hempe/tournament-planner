import { NgTemplateOutlet } from '@angular/common';
import { LoadError } from '../../shared/ui/load-error';
import { Component, computed, effect, inject, input, resource, signal } from '@angular/core';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideCalendarDays, lucideChevronLeft, lucideChevronRight, lucideList, lucidePlus } from '@ng-icons/lucide';
import { Router, RouterLink } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient } from '../../core/api/api-client';
import { CalendarDto, EventSummaryDto, SocialEventSummaryDto, calendarApi } from '../../core/api/generated';
import { SessionStore } from '../../core/auth/session.store';
import { formatDate, formatMonth, parseApiDate } from '../../core/format/dates';
import { I18n } from '../../core/i18n/i18n';
import { TranslatePipe } from '../../core/i18n/translate.pipe';
import { EventCard } from './event-card';
import { monthGrid, monthOf, resolveMonth, shiftMonth } from './month';
import { SocialEventCard } from './social-event-card';
import { Page } from '../../shared/ui/page';

type DayItem =
  | { type: 'event'; event: EventSummaryDto }
  | { type: 'social'; social: SocialEventSummaryDto };

export type CalendarView = 'list' | 'month';
const VIEW_STORAGE_KEY = 'tp.calendar-view';

/** Today as 'YYYY-MM-DD' (local time). */
function isoToday(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

@Component({
  selector: 'app-calendar-page',
  imports: [LoadError, NgTemplateOutlet, NgIcon, RouterLink, HlmButtonImports, HlmSpinner, TranslatePipe, EventCard, SocialEventCard, Page],
  providers: [provideIcons({ lucideChevronLeft, lucideChevronRight, lucidePlus, lucideList, lucideCalendarDays })],
  template: `
    <div appPage width="wide">
      <div class="flex items-center justify-between gap-2">
        <button hlmBtn variant="outline" size="icon" type="button" [attr.aria-label]="'calendar.previous_month' | t" (click)="go(-1)">
          <ng-icon name="lucideChevronLeft" />
        </button>
        <h1 class="text-xl font-semibold">{{ title() }}</h1>
        <div class="flex items-center gap-2">
          <!-- desktop: list / month switch (remembered per browser) -->
          <div class="hidden rounded-md border border-border bg-background p-0.5 md:flex" role="group">
            <button
              type="button"
              data-view="list"
              class="flex items-center gap-1.5 rounded px-2.5 py-1 text-sm"
              [class]="view() === 'list' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
              [attr.aria-pressed]="view() === 'list'"
              (click)="setView('list')"
            >
              <ng-icon name="lucideList" size="16" aria-hidden="true" /><span class="pt-[0.3em]">{{ 'calendar.view_list' | t }}</span>
            </button>
            <button
              type="button"
              data-view="month"
              class="flex items-center gap-1.5 rounded px-2.5 py-1 text-sm"
              [class]="view() === 'month' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
              [attr.aria-pressed]="view() === 'month'"
              (click)="setView('month')"
            >
              <ng-icon name="lucideCalendarDays" size="16" aria-hidden="true" /><span class="pt-[0.3em]">{{ 'calendar.view_month' | t }}</span>
            </button>
          </div>
          <button hlmBtn variant="outline" size="icon" type="button" [attr.aria-label]="'calendar.next_month' | t" (click)="go(1)">
            <ng-icon name="lucideChevronRight" />
          </button>
        </div>
      </div>

      @if (calendar.hasValue()) {
        <ng-template #items let-day let-detailed="detailed">
          <div class="flex flex-col gap-1" [class.gap-2]="detailed">
            @for (item of itemsOf(day); track $index) {
              @if (item.type === 'event') {
                <app-event-card [event]="item.event" [loggedIn]="session.loggedIn()" [detailed]="detailed" (registered)="reload()" />
              } @else {
                <app-social-event-card [event]="item.social" />
              }
            }
          </div>
        </ng-template>

        <!-- small screens: day-grouped list (unchanged) -->
        <div class="flex flex-col gap-4 md:hidden">
          @for (day of activeDays(); track day) {
            <section>
              <h2 class="mb-1 text-sm font-medium text-muted-foreground">{{ dayHeading(day) }}</h2>
              <ng-container *ngTemplateOutlet="items; context: { $implicit: day }" />
            </section>
          } @empty {
            <p data-empty class="py-8 text-center text-muted-foreground">{{ 'calendar.no_events' | t }}</p>
          }
        </div>

        @if (view() === 'list') {
          <!-- md and up, list view: agenda + sidebar -->
          <div data-agenda class="hidden gap-6 md:grid lg:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="flex flex-col gap-3">
              @for (day of activeDays(); track day) {
                <section
                  [id]="'day-' + day"
                  [attr.data-selected]="day === selected() ? '' : null"
                  class="flex gap-4 rounded-lg border bg-background p-3 shadow-xs transition-shadow scroll-mt-4"
                  [class]="day === selected() ? 'border-primary ring-2 ring-primary/40' : 'border-border'"
                >
                  <div
                    class="flex w-16 shrink-0 flex-col items-center justify-center rounded-md py-2"
                    [class]="day === today ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground'"
                  >
                    <span class="text-xs uppercase opacity-80">{{ weekdayShort(day) }}</span>
                    <span class="text-2xl leading-none font-semibold">{{ dayNumber(day) }}</span>
                    <span class="text-xs uppercase opacity-80">{{ monthShort(day) }}</span>
                  </div>
                  <div class="min-w-0 flex-1">
                    <ng-container *ngTemplateOutlet="items; context: { $implicit: day, detailed: true }" />
                  </div>
                </section>
              } @empty {
                <p data-empty class="rounded-lg border border-border bg-background py-12 text-center text-muted-foreground">
                  {{ 'calendar.no_events' | t }}
                </p>
              }
            </div>

            <aside class="flex flex-col gap-4 lg:sticky lg:top-4 lg:self-start">
              <!-- mini month -->
              <section class="rounded-lg border border-border bg-background p-3 shadow-xs">
                <div class="grid grid-cols-7 gap-y-1 text-center text-xs">
                  @for (name of weekdaysNarrow(); track $index) {
                    <span class="pb-1 font-medium text-muted-foreground">{{ name }}</span>
                  }
                  @for (week of grid(); track $index) {
                    @for (day of week; track $index) {
                      @if (day) {
                        <button
                          type="button"
                          data-mini-day
                          class="mx-auto flex size-8 flex-col items-center justify-center rounded-full text-sm"
                          [class]="miniDayClasses(day)"
                          [attr.aria-label]="dayHeading(day)"
                          (click)="pick(day)"
                        >
                          <span class="pt-[0.3em] leading-none">{{ dayNumber(day) }}</span>
                          <span class="mt-0.5 flex h-1 gap-0.5">
                            @for (dot of dotsOf(day); track $index) {
                              <span class="size-1 rounded-full" [class]="dot"></span>
                            }
                          </span>
                        </button>
                      } @else {
                        <span></span>
                      }
                    }
                  }
                </div>
                @if (session.isAdmin() && selected(); as day) {
                  <a
                    hlmBtn
                    variant="outline"
                    size="sm"
                    class="mt-3 w-full"
                    routerLink="/events/new"
                    [queryParams]="{ date: day }"
                    data-add-event
                  >
                    <ng-icon name="lucidePlus" size="16" aria-hidden="true" />{{ 'calendar.add_event_on' | t: { date: dayShort(day) } }}
                  </a>
                }
              </section>

              @if (session.loggedIn()) {
                <section data-my-registrations class="rounded-lg border border-border bg-background p-3 shadow-xs">
                  <h2 class="mb-2 text-sm font-semibold">{{ 'calendar.my_registrations' | t }}</h2>
                  @for (entry of myRegistrations(); track entry.key) {
                    <a
                      [routerLink]="entry.link"
                      class="flex items-center gap-3 rounded-md px-1 py-1.5 hover:bg-muted"
                    >
                      <span class="size-2.5 shrink-0 rounded-full" [class]="entry.dot"></span>
                      <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm">{{ entry.name }}</span>
                        <span class="block text-xs text-muted-foreground">{{ dayShort(entry.date) }} · {{ entry.statusKey | t }}</span>
                      </span>
                    </a>
                  } @empty {
                    <p class="text-sm text-muted-foreground">{{ 'calendar.no_registrations' | t }}</p>
                  }
                </section>
              }

              <section data-legend class="rounded-lg border border-border bg-background p-3 text-xs shadow-xs">
                <h2 class="sr-only">{{ 'calendar.legend' | t }}</h2>
                <ul class="grid grid-cols-2 gap-x-3 gap-y-1.5">
                  @if (session.loggedIn()) {
                    <!-- guests can't be registered (they use the guest form), so no "registered" hint for them -->
                    <li class="flex items-center gap-2"><span class="size-3 rounded-sm bg-[var(--primary-light)]"></span>{{ 'calendar.legend_registered' | t }}</li>
                  }
                  <li class="flex items-center gap-2"><span class="size-3 rounded-sm bg-primary"></span>{{ 'calendar.legend_open' | t }}</li>
                  <li class="flex items-center gap-2"><span class="size-3 rounded-sm bg-muted-foreground/30"></span>{{ 'calendar.legend_closed' | t }}</li>
                  @if (session.loggedIn()) {
                    <li class="flex items-center gap-2"><span class="size-3 rounded-sm bg-[var(--social)]"></span>{{ 'calendar.legend_social' | t }}</li>
                  }
                </ul>
              </section>
            </aside>
          </div>
        } @else {
          <!-- md and up, month view -->
          <div data-month-grid class="hidden grid-cols-7 gap-1 rounded-lg border border-border bg-background p-2 shadow-xs md:grid">
            @for (name of weekdays(); track $index) {
              <div class="px-1 pb-1 text-center text-xs font-medium text-muted-foreground">{{ name }}</div>
            }
            @for (week of grid(); track $index) {
              @for (day of week; track $index) {
                <div class="min-h-24 rounded-md border border-border p-1" [class.bg-muted]="day === null" [class.border-transparent]="day === null">
                  @if (day) {
                    <div class="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                      <span
                        class="inline-flex size-6 items-center justify-center rounded-full"
                        [class]="day === today ? 'bg-primary font-semibold text-primary-foreground' : ''"
                        ><span class="pt-[0.3em]">{{ dayNumber(day) }}</span></span
                      >
                      @if (session.isAdmin()) {
                        <a
                          routerLink="/events/new"
                          [queryParams]="{ date: day }"
                          [attr.aria-label]="'events.new_for_date' | t: { date: dayHeading(day) }"
                          class="inline-flex size-5 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                        ><ng-icon name="lucidePlus" class="text-sm" aria-hidden="true" /></a>
                      }
                    </div>
                    <ng-container *ngTemplateOutlet="items; context: { $implicit: day }" />
                  }
                </div>
              }
            }
          </div>
        }
      } @else if (calendar.error()) {
        <app-load-error [error]="calendar.error()" (retry)="calendar.reload()" />
      } @else {
        <div class="flex justify-center p-8"><hlm-spinner /></div>
      }
    </div>
  `,
})
export class CalendarPage {
  /** ?month=YYYY-MM (component input binding). */
  readonly month = input<string>();
  /** Legacy ?date=YYYY-MM-D from old bookmarks. */
  readonly date = input<string>();
  /** ?view=list|month — desktop layout; falls back to the last choice in this browser, then "month". */
  readonly viewParam = input<string | undefined>(undefined, { alias: 'view' });

  protected readonly session = inject(SessionStore);
  private readonly api = inject(ApiClient);
  private readonly router = inject(Router);
  private readonly i18n = inject(I18n);

  protected readonly today = isoToday();
  protected readonly view = computed<CalendarView>(() => {
    const fromUrl = this.viewParam();
    return fromUrl === 'list' || fromUrl === 'month' ? fromUrl : readView();
  });
  /** Day picked in the mini month (admins get an "add event" action for it). */
  protected readonly selected = signal<string | null>(null);

  protected readonly current = computed(() => resolveMonth(this.month(), this.date(), new Date()));
  protected readonly title = computed(() => formatMonth(this.current(), this.i18n.locale()));
  protected readonly grid = computed(() => monthGrid(this.current()));

  protected readonly calendar = resource({
    params: () => ({ month: this.current() }),
    loader: ({ params, abortSignal }) => calendarApi(this.api).month(params.month, { signal: abortSignal }),
  });

  /** Next month too, for "Meine nächsten Anmeldungen" (logged-in only). */
  private readonly nextMonth = resource({
    params: () => (this.session.loggedIn() ? { month: shiftMonth(monthOf(new Date()), 1) } : undefined),
    loader: ({ params, abortSignal }) => calendarApi(this.api).month(params.month, { signal: abortSignal }),
  });
  private readonly thisMonth = resource({
    params: () => (this.session.loggedIn() ? { month: monthOf(new Date()) } : undefined),
    loader: ({ params, abortSignal }) => calendarApi(this.api).month(params.month, { signal: abortSignal }),
  });

  constructor() {
    effect(() => {
      this.current();
      this.selected.set(null);
    });
  }

  protected readonly weekdays = computed(() => weekdayNames(this.i18n.locale(), 'short'));
  protected readonly weekdaysNarrow = computed(() => weekdayNames(this.i18n.locale(), 'narrow'));

  private readonly byDay = computed(() => {
    const days = new Map<string, DayItem[]>();
    if (!this.calendar.hasValue()) {
      return days;
    }
    const { events, socialEvents } = this.calendar.value();
    const add = (date: string, item: DayItem) => days.set(date, [...(days.get(date) ?? []), item]);
    [...events].sort((a, b) => a.name.localeCompare(b.name)).forEach((event) => add(event.date, { type: 'event', event }));
    if (this.session.loggedIn()) {
      [...socialEvents].sort((a, b) => a.name.localeCompare(b.name)).forEach((social) => add(social.date, { type: 'social', social }));
    }
    return days;
  });

  protected readonly activeDays = computed(() => [...this.byDay().keys()].sort());

  protected readonly myRegistrations = computed(() => {
    const months: CalendarDto[] = [this.thisMonth, this.nextMonth].filter((r) => r.hasValue()).map((r) => r.value()!);
    const entries: { key: string; date: string; name: string; link: string[]; statusKey: string; dot: string }[] = [];
    for (const month of months) {
      for (const e of month.events) {
        if (e.userState !== 'none' && e.date >= this.today) {
          entries.push({
            key: `e${e.id}`,
            date: e.date,
            name: e.name,
            link: ['/events', String(e.id)],
            statusKey: e.userState === 'waitlist' ? 'events.on_waitlist' : 'events.registered',
            dot: 'bg-primary',
          });
        }
      }
      for (const s of month.socialEvents) {
        if (s.userRegistered && s.date >= this.today) {
          entries.push({ key: `s${s.id}`, date: s.date, name: s.name, link: ['/social-events', String(s.id)], statusKey: 'social_events.registered', dot: 'bg-[var(--social)]' });
        }
      }
    }
    return entries.sort((a, b) => a.date.localeCompare(b.date)).slice(0, 5);
  });

  protected itemsOf(day: string): DayItem[] {
    return this.byDay().get(day) ?? [];
  }

  /** One dot per item in the mini month, coloured by state. */
  protected dotsOf(day: string): string[] {
    return this.itemsOf(day).map((item) => {
      if (item.type === 'social') {
        return item.social.userRegistered ? 'bg-[var(--social-light)] ring-1 ring-[var(--social)]' : 'bg-[var(--social)]';
      }
      const e = item.event;
      if (e.userState !== 'none') {
        return 'bg-[var(--primary-light)] ring-1 ring-primary';
      }
      return e.isLocked || day < this.today ? 'bg-muted-foreground/40' : 'bg-primary';
    });
  }

  protected miniDayClasses(day: string): string {
    if (day === this.today) {
      return 'bg-primary font-semibold text-primary-foreground';
    }
    if (day === this.selected()) {
      return 'bg-muted ring-1 ring-primary';
    }
    return this.itemsOf(day).length > 0 ? 'font-medium hover:bg-muted' : 'text-muted-foreground hover:bg-muted';
  }

  protected pick(day: string): void {
    this.selected.set(day);
    document.getElementById('day-' + day)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  protected reload(): void {
    this.calendar.reload();
    this.thisMonth.reload();
    this.nextMonth.reload();
  }

  protected dayNumber(day: string): number {
    return parseApiDate(day).getDate();
  }

  protected dayHeading(day: string): string {
    return formatDate(day, this.i18n.locale());
  }

  protected dayShort(day: string): string {
    return new Intl.DateTimeFormat(this.i18n.locale(), { weekday: 'short', day: 'numeric', month: 'short' }).format(parseApiDate(day));
  }

  protected weekdayShort(day: string): string {
    return new Intl.DateTimeFormat(this.i18n.locale(), { weekday: 'short' }).format(parseApiDate(day)).replace('.', '');
  }

  protected monthShort(day: string): string {
    return new Intl.DateTimeFormat(this.i18n.locale(), { month: 'short' }).format(parseApiDate(day)).replace('.', '');
  }

  protected go(delta: number): void {
    void this.router.navigate([], {
      queryParams: { month: shiftMonth(this.current(), delta), date: null },
      queryParamsHandling: 'merge',
    });
  }

  protected setView(view: CalendarView): void {
    try {
      localStorage.setItem(VIEW_STORAGE_KEY, view);
    } catch {
      // storage unavailable (private mode): the URL still carries the choice
    }
    void this.router.navigate([], { queryParams: { view }, queryParamsHandling: 'merge', replaceUrl: true });
  }
}

function readView(): CalendarView {
  try {
    return localStorage.getItem(VIEW_STORAGE_KEY) === 'list' ? 'list' : 'month';
  } catch {
    return 'month';
  }
}

function weekdayNames(locale: string, style: 'short' | 'narrow'): string[] {
  const format = new Intl.DateTimeFormat(locale, { weekday: style });
  return Array.from({ length: 7 }, (_, i) => format.format(new Date(2024, 0, 1 + i))); // 2024-01-01 is a Monday
}
