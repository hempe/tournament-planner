import { Component, computed, inject, input, linkedSignal, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { HlmCardImports } from '@ui/card';
import { HlmInputImports } from '@ui/input';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient } from '../../core/api/api-client';
import { asApiError } from '../../core/api/api-error';
import { EventDetailDto, eventsApi } from '../../core/api/generated';
import { SessionStore } from '../../core/auth/session.store';
import { formatDate, formatDateTime } from '../../core/format/dates';
import { I18n } from '../../core/i18n/i18n';
import { TranslatePipe } from '../../core/i18n/translate.pipe';
import { RegistrationFlow } from './registration-flow';

@Component({
  selector: 'app-event-detail-page',
  imports: [RouterLink, HlmButtonImports, HlmCardImports, HlmInputImports, HlmSpinner, TranslatePipe],
  template: `
    @if (detail.hasValue()) {
      @let d = detail.value();
      <div class="mx-auto flex max-w-2xl flex-col gap-4">
        <div class="flex items-center justify-between gap-2">
          <a hlmBtn variant="ghost" size="sm" routerLink="/" [queryParams]="{ month: d.event.date.slice(0, 7) }">
            {{ 'nav.back' | t }}
          </a>
          @if (session.isAdmin()) {
            <a hlmBtn variant="outline" size="sm" [routerLink]="['/events', d.event.id, 'admin']">
              {{ 'events.edit' | t }}
            </a>
          }
        </div>

        <header>
          <p class="text-sm text-muted-foreground">{{ formatDate(d.event.date) }}</p>
          <h1 class="text-2xl font-semibold">{{ d.event.name }}</h1>
          @if (d.event.userState === 'registered') {
            <span class="mt-1 inline-block rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground">
              {{ 'events.registered' | t }}
            </span>
          } @else if (d.event.userState === 'waitlist') {
            <span class="mt-1 inline-block rounded-full bg-secondary px-2 py-0.5 text-xs text-secondary-foreground">
              {{ 'events.on_waitlist' | t }}
            </span>
          }
        </header>

        @if (d.description || d.priceMembers || d.priceGuests || d.registrationClose) {
          <section hlmCard>
            <dl hlmCardContent class="flex flex-col gap-3">
              @if (d.description) {
                <div>
                  <dt class="text-sm text-muted-foreground">{{ 'events.description' | t }}</dt>
                  <dd class="whitespace-pre-line">{{ d.description }}</dd>
                </div>
              }
              @if (d.priceMembers) {
                <div>
                  <dt class="text-sm text-muted-foreground">{{ 'events.price_members' | t }}</dt>
                  <dd>{{ d.priceMembers }}</dd>
                </div>
              }
              @if (d.priceGuests) {
                <div>
                  <dt class="text-sm text-muted-foreground">{{ 'events.price_guests' | t }}</dt>
                  <dd>{{ d.priceGuests }}</dd>
                </div>
              }
              @if (d.registrationClose) {
                <div>
                  <dt class="text-sm text-muted-foreground">{{ 'events.registration_close' | t }}</dt>
                  <dd>{{ formatDateTime(d.registrationClose) }}</dd>
                </div>
              }
            </dl>
          </section>
        }

        <section hlmCard>
          <div hlmCardContent class="flex flex-col gap-3">
            @if (d.event.isLocked) {
              <p class="text-sm text-muted-foreground">{{ 'events.locked_message' | t }}</p>
            }
            @if (registered() || !d.event.isLocked) {
              <input
                hlmInput
                [attr.placeholder]="'events.comment' | t"
                [attr.aria-label]="'events.comment' | t"
                [value]="comment()"
                [disabled]="d.event.isLocked || busy()"
                (input)="comment.set($any($event.target).value)"
              />
            }
            @if (registered()) {
              <div class="flex flex-col gap-2 sm:flex-row">
                <button
                  hlmBtn
                  type="button"
                  class="w-full sm:w-auto"
                  [disabled]="d.event.isLocked || busy() || !changed()"
                  (click)="saveComment(d)"
                >
                  {{ 'events.save' | t }}
                </button>
                <button
                  hlmBtn
                  variant="destructive"
                  type="button"
                  class="w-full sm:w-auto"
                  [disabled]="d.event.isLocked || busy()"
                  (click)="unregister(d)"
                >
                  {{ 'events.unregister' | t }}
                </button>
              </div>
            } @else if (!d.event.isLocked) {
              <button hlmBtn type="button" class="w-full sm:w-auto sm:self-start" [disabled]="busy()" (click)="register(d)">
                {{ (d.event.available <= 0 ? 'events.waitlist' : 'events.register') | t }}
              </button>
            }
          </div>
        </section>

        @if (d.socialEvent; as social) {
          <a
            [routerLink]="['/social-events', social.id]"
            class="rounded-xl border-2 border-[var(--social)] p-4 font-medium text-[var(--social)]"
          >
            {{ social.name }}
          </a>
        }

        <section hlmCard>
          <div hlmCardHeader><h2 hlmCardTitle>{{ 'events.registered' | t }}</h2></div>
          <ul hlmCardContent class="flex flex-col gap-1">
            @for (p of d.participants; track $index) {
              <li>{{ p.name }}@if (p.state === 'waitlist') { ({{ 'events.waitlist' | t }})}</li>
            }
          </ul>
        </section>

        @if (d.guests.length > 0) {
          <section hlmCard>
            <div hlmCardHeader><h2 hlmCardTitle>{{ 'guests.title' | t }}</h2></div>
            <ul hlmCardContent class="flex flex-col gap-1">
              @for (g of d.guests; track $index) {
                <li>{{ g.firstName }} {{ g.lastName }}</li>
              }
            </ul>
          </section>
        }
      </div>
    } @else if (detail.error()) {
      <div class="mx-auto max-w-2xl">
        <p class="text-lg">{{ (errorKey() | t) }}</p>
        <a hlmBtn class="mt-4" routerLink="/">{{ 'navigation.home' | t }}</a>
      </div>
    } @else {
      <div class="flex justify-center p-8"><hlm-spinner /></div>
    }
  `,
})
export class EventDetailPage {
  /** From the route (withComponentInputBinding). */
  readonly id = input.required<string>();

  protected readonly session = inject(SessionStore);
  private readonly api = inject(ApiClient);
  private readonly flow = inject(RegistrationFlow);
  private readonly i18n = inject(I18n);

  protected readonly detail = resource({
    params: () => this.id(),
    loader: ({ params, abortSignal }) => eventsApi(this.api).show(params, { signal: abortSignal }),
  });

  private readonly current = computed(() => (this.detail.hasValue() ? this.detail.value() : undefined));
  protected readonly comment = linkedSignal(() => this.current()?.myRegistration?.comment ?? '');
  protected readonly busy = signal(false);
  protected readonly registered = computed(() => (this.current()?.event.userState ?? 'none') !== 'none');
  protected readonly changed = computed(() => this.comment() !== (this.current()?.myRegistration?.comment ?? ''));
  protected readonly errorKey = computed(() => {
    const error = asApiError(this.detail.error());
    return error?.status === 404 ? 'events.not_found' : (error?.error ?? 'errors.general');
  });

  protected formatDate(value: string): string {
    return formatDate(value, this.i18n.locale());
  }

  protected formatDateTime(value: string): string {
    return formatDateTime(value, this.i18n.locale());
  }

  protected register(d: EventDetailDto): Promise<void> {
    return this.run(() => this.flow.register(d.event.id, this.comment(), { available: d.event.available, isLocked: d.event.isLocked }));
  }

  protected unregister(d: EventDetailDto): Promise<void> {
    return this.run(() => this.flow.unregister(d.event.id, d.socialEvent));
  }

  protected saveComment(d: EventDetailDto): Promise<void> {
    return this.run(() => this.flow.updateComment(d.event.id, this.comment()));
  }

  private async run(action: () => Promise<EventDetailDto | null>): Promise<void> {
    this.busy.set(true);
    try {
      const fresh = await action();
      if (fresh) {
        this.detail.value.set(fresh);
      }
    } finally {
      this.busy.set(false);
    }
  }
}
