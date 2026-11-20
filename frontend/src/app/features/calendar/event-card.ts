import { NgTemplateOutlet } from '@angular/common';
import { Component, computed, inject, input, output, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { EventSummaryDto } from '../../core/api/generated';
import { parseApiDate } from '../../core/format/dates';
import { TranslatePipe } from '../../core/i18n/translate.pipe';
import { RegistrationFlow } from '../events/registration-flow';

@Component({
  selector: 'app-event-card',
  imports: [NgTemplateOutlet, RouterLink, HlmButtonImports, TranslatePipe],
  template: `
    <ng-template #body>
      <span class="block font-medium">{{ event().name }}</span>
      <span class="block text-xs" [class.text-muted-foreground]="!highlighted()">{{ status().key | t: status().params }}</span>
    </ng-template>
    <div
      data-card
      class="rounded-md border bg-card text-sm"
      [class.border-primary]="highlighted()"
      [class.border-2]="highlighted()"
      [class.opacity-60]="closed()"
    >
      @if (link(); as target) {
        <a [routerLink]="target" class="block p-2"><ng-container *ngTemplateOutlet="body" /></a>
      } @else {
        <div class="p-2"><ng-container *ngTemplateOutlet="body" /></div>
      }
      @if (loggedIn() && canRegister()) {
        <div class="px-2 pb-2">
          <button hlmBtn size="sm" variant="outline" type="button" class="w-full" [disabled]="busy()" (click)="register()">
            {{ (event().available <= 0 ? 'events.waitlist' : 'events.register') | t }}
          </button>
        </div>
      }
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
  protected readonly highlighted = computed(() => this.event().userState !== 'none');
  protected readonly canRegister = computed(() => this.event().userState === 'none' && !this.closed());

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
