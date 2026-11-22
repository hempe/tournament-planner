import { NgTemplateOutlet } from '@angular/common';
import { Component, computed, inject, input, output, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideHourglass, lucideLock, lucideUserCheck, lucideUserPlus } from '@ng-icons/lucide';
import { HlmButtonImports } from '@ui/button';
import { EventSummaryDto } from '../../core/api/generated';
import { parseApiDate } from '../../core/format/dates';
import { TranslatePipe } from '../../core/i18n/translate.pipe';
import { RegistrationFlow } from '../events/registration-flow';

@Component({
  selector: 'app-event-card',
  imports: [NgTemplateOutlet, RouterLink, NgIcon, HlmButtonImports, TranslatePipe],
  providers: [provideIcons({ lucideUserCheck, lucideHourglass, lucideUserPlus, lucideLock })],
  template: `
    <ng-template #body>
      <span class="block font-medium [overflow-wrap:anywhere] hyphens-auto">{{ event().name }}</span>
      <span class="block text-xs opacity-80">{{ status().key | t: status().params }}</span>
    </ng-template>
    <!--
      Colours as on the previous site: light green = registered / waitlist,
      dark green = open for registration, grey = not open (locked, deadline or date passed).
    -->
    <div
      data-card
      [attr.data-state]="state()"
      class="flex items-start gap-1 rounded-md text-sm"
      [class]="stateClasses()"
    >
      @if (link(); as target) {
        <a [routerLink]="target" class="min-w-0 flex-1 p-2"><ng-container *ngTemplateOutlet="body" /></a>
      } @else {
        <div class="min-w-0 flex-1 p-2"><ng-container *ngTemplateOutlet="body" /></div>
      }
      <span class="flex shrink-0 items-center p-1.5">
        @switch (state()) {
          @case ('registered') {
            <ng-icon name="lucideUserCheck" size="18" [attr.aria-label]="'events.registered' | t" />
            @if (event().isLocked) {
              <ng-icon name="lucideLock" size="16" class="ml-1" [attr.aria-label]="'events.locked' | t" />
            }
          }
          @case ('waitlist') {
            <ng-icon name="lucideHourglass" size="18" [attr.aria-label]="'events.on_waitlist' | t" />
            @if (event().isLocked) {
              <ng-icon name="lucideLock" size="16" class="ml-1" [attr.aria-label]="'events.locked' | t" />
            }
          }
          @case ('closed') {
            @if (event().isLocked) {
              <ng-icon name="lucideLock" size="16" [attr.aria-label]="'events.locked' | t" />
            }
          }
          @case ('open') {
            @if (loggedIn()) {
              <button
                type="button"
                class="flex size-8 items-center justify-center rounded-md bg-white/20 hover:bg-white/35 disabled:opacity-50"
                [attr.aria-label]="(event().available <= 0 ? 'events.waitlist' : 'events.register') | t"
                [title]="(event().available <= 0 ? 'events.waitlist' : 'events.register') | t"
                [disabled]="busy()"
                (click)="register()"
              >
                <ng-icon name="lucideUserPlus" size="18" />
              </button>
            } @else {
              <ng-icon name="lucideUserPlus" size="18" aria-hidden="true" />
            }
          }
        }
      </span>
    </div>
  `,
})
export class EventCard {
  readonly event = input.required<EventSummaryDto>();
  readonly loggedIn = input(false);
  /** Emitted after a successful quick registration so the calendar can reload. */
  readonly registered = output<void>();

  private readonly flow = inject(RegistrationFlow);
  protected readonly busy = signal(false);

  private readonly past = computed(() => {
    const today = new Date();
    return parseApiDate(this.event().date) < new Date(today.getFullYear(), today.getMonth(), today.getDate());
  });
  protected readonly closed = computed(() => this.event().isLocked || this.past());
  /** registered / waitlist (light), open (dark, can register), closed (grey). Guests can only join with free spots. */
  protected readonly state = computed<'registered' | 'waitlist' | 'open' | 'closed'>(() => {
    const e = this.event();
    if (e.userState === 'registered') {
      return 'registered';
    }
    if (e.userState === 'waitlist') {
      return 'waitlist';
    }
    if (this.closed() || (!this.loggedIn() && e.available <= 0)) {
      return 'closed';
    }
    return 'open';
  });
  protected readonly stateClasses = computed(() => {
    switch (this.state()) {
      case 'registered':
      case 'waitlist':
        return 'bg-[var(--primary-light)] text-foreground';
      case 'open':
        return 'bg-primary text-primary-foreground';
      default:
        return 'bg-muted text-muted-foreground';
    }
  });

  protected readonly status = computed<{ key: string; params?: { count: number } }>(() => {
    const e = this.event();
    if (e.userState === 'registered') {
      return { key: 'events.registered' };
    }
    if (e.userState === 'waitlist') {
      return { key: 'events.on_waitlist' };
    }
    if (this.closed()) {
      return { key: 'events.locked' };
    }
    if (e.available <= 0) {
      return { key: 'events.waitlist_available' };
    }
    return { key: e.available === 1 ? 'events.spot_available' : 'events.spots_available', params: { count: e.available } };
  });

  protected readonly link = computed<string[] | null>(() => {
    const e = this.event();
    if (this.loggedIn()) {
      return ['/events', String(e.id)];
    }
    return !this.closed() && e.available > 0 ? ['/events', String(e.id), 'guests', 'new'] : null;
  });

  protected async register(): Promise<void> {
    const e = this.event();
    this.busy.set(true);
    try {
      if (await this.flow.register(e.id, '', { available: e.available, isLocked: e.isLocked })) {
        this.registered.emit();
      }
    } finally {
      this.busy.set(false);
    }
  }
}
