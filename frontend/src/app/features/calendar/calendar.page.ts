import { NgTemplateOutlet } from '@angular/common';
import { Component, computed, inject, input, resource } from '@angular/core';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideChevronLeft, lucideChevronRight, lucidePlus } from '@ng-icons/lucide';
import { Router, RouterLink } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient } from '../../core/api/api-client';
import { asApiError } from '../../core/api/api-error';
import { EventSummaryDto, SocialEventSummaryDto, calendarApi } from '../../core/api/generated';
import { SessionStore } from '../../core/auth/session.store';
import { formatDate, formatMonth, parseApiDate } from '../../core/format/dates';
import { I18n } from '../../core/i18n/i18n';
import { TranslatePipe } from '../../core/i18n/translate.pipe';
import { EventCard } from './event-card';
import { monthGrid, resolveMonth, shiftMonth } from './month';
import { SocialEventCard } from './social-event-card';
import { Page } from '../../shared/ui/page';

type DayItem =
  | { type: 'event'; event: EventSummaryDto }
  | { type: 'social'; social: SocialEventSummaryDto };

@Component({
  selector: 'app-calendar-page',
  imports: [NgTemplateOutlet, NgIcon, RouterLink, HlmButtonImports, HlmSpinner, TranslatePipe, EventCard, SocialEventCard, Page],
  providers: [provideIcons({ lucideChevronLeft, lucideChevronRight, lucidePlus })],
  template: `
    <div appPage width="wide">
      <div class="flex items-center justify-between gap-2">
        <button hlmBtn variant="outline" size="icon" type="button" [attr.aria-label]="'calendar.previous_month' | t" (click)="go(-1)">
          <ng-icon name="lucideChevronLeft" />
        </button>
        <h1 class="text-xl font-semibold">{{ title() }}</h1>
        <button hlmBtn variant="outline" size="icon" type="button" [attr.aria-label]="'calendar.next_month' | t" (click)="go(1)">
          <ng-icon name="lucideChevronRight" />
        </button>
      </div>

      @if (calendar.hasValue()) {
        <ng-template #items let-day>
          <div class="flex flex-col gap-1">
            @for (item of itemsOf(day); track $index) {
              @if (item.type === 'event') {
                <app-event-card [event]="item.event" [loggedIn]="session.loggedIn()" (registered)="calendar.reload()" />
              } @else {
                <app-social-event-card [event]="item.social" />
              }
            }
          </div>
        </ng-template>

        <!-- small screens: day-grouped list -->
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

        <!-- md and up: month grid -->
        <div class="hidden grid-cols-7 gap-1 md:grid">
          @for (name of weekdays(); track $index) {
            <div class="px-1 text-center text-xs font-medium text-muted-foreground">{{ name }}</div>
          }
          @for (week of grid(); track $index) {
            @for (day of week; track $index) {
              <div class="min-h-24 rounded-md border border-border p-1" [class.bg-muted]="day === null" [class.border-transparent]="day === null">
                @if (day) {
                  <div class="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                    <span>{{ dayNumber(day) }}</span>
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
      } @else if (calendar.error()) {
        <p role="alert" class="text-destructive">{{ errorKey() | t }}</p>
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

  protected readonly session = inject(SessionStore);
  private readonly api = inject(ApiClient);
  private readonly router = inject(Router);
  private readonly i18n = inject(I18n);

  protected readonly current = computed(() => resolveMonth(this.month(), this.date(), new Date()));
  protected readonly title = computed(() => formatMonth(this.current(), this.i18n.locale()));
  protected readonly grid = computed(() => monthGrid(this.current()));

  protected readonly calendar = resource({
    params: () => ({ month: this.current() }),
    loader: ({ params, abortSignal }) => calendarApi(this.api).month(params.month, { signal: abortSignal }),
  });

  protected readonly errorKey = computed(() => asApiError(this.calendar.error())?.error ?? 'errors.general');

  protected readonly weekdays = computed(() => {
    const format = new Intl.DateTimeFormat(this.i18n.locale(), { weekday: 'short' });
    return Array.from({ length: 7 }, (_, i) => format.format(new Date(2024, 0, 1 + i))); // 2024-01-01 is a Monday
  });

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

  protected itemsOf(day: string): DayItem[] {
    return this.byDay().get(day) ?? [];
  }

  protected dayNumber(day: string): number {
    return parseApiDate(day).getDate();
  }

  protected dayHeading(day: string): string {
    return formatDate(day, this.i18n.locale());
  }

  protected go(delta: number): void {
    void this.router.navigate([], { queryParams: { month: shiftMonth(this.current(), delta) } });
  }
}
