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
import { BackLink } from '../../shared/ui/back-link';
import { Badge } from '../../shared/ui/badge';
import { DetailList } from '../../shared/ui/detail-list';
import { Page, PageHeader } from '../../shared/ui/page';
import { SocialEventRow } from '../social-events/social-event-row';
import { RegistrationFlow } from './registration-flow';

@Component({
  selector: 'app-event-detail-page',
  imports: [
    RouterLink,
    HlmButtonImports,
    HlmCardImports,
    HlmInputImports,
    HlmSpinner,
    TranslatePipe,
    BackLink,
    Badge,
    DetailList,
    Page,
    PageHeader,
    SocialEventRow,
  ],
  template: `
    @if (detail.hasValue()) {
      @let d = detail.value();
      <div appPage>
        <app-back-link link="/" [queryParams]="{ month: d.event.date.slice(0, 7) }" />
        <app-page-header [title]="d.event.name" [subtitle]="formatDate(d.event.date)">
          <div meta class="flex flex-wrap gap-2 empty:hidden">
            @if (d.event.userState === 'registered') {
              <span appBadge>{{ 'events.registered' | t }}</span>
            } @else if (d.event.userState === 'waitlist') {
              <span appBadge variant="secondary">{{ 'events.on_waitlist' | t }}</span>
            }
          </div>
          @if (session.isAdmin()) {
            <a hlmBtn variant="outline" size="sm" [routerLink]="['/events', d.event.id, 'admin']">{{ 'events.edit' | t }}</a>
          }
        </app-page-header>

        @if (d.description || d.priceMembers || d.priceGuests || d.registrationClose) {
          <section hlmCard>
            <dl hlmCardContent appDetailList>
              @if (d.description) {
                <div>
                  <dt>{{ 'events.description' | t }}</dt>
                  <dd class="whitespace-pre-line">{{ d.description }}</dd>
                </div>
              }
              @if (d.priceMembers) {
                <div>
                  <dt>{{ 'events.price_members' | t }}</dt>
                  <dd>{{ d.priceMembers }}</dd>
                </div>
              }
              @if (d.priceGuests) {
                <div>
                  <dt>{{ 'events.price_guests' | t }}</dt>
                  <dd>{{ d.priceGuests }}</dd>
                </div>
              }
              @if (d.registrationClose) {
                <div>
                  <dt>{{ 'events.registration_close' | t }}</dt>
                  <dd>{{ formatDateTime(d.registrationClose) }}</dd>
                </div>
              }
            </dl>
          </section>
        }

        <section hlmCard data-registration>
          @if (registered()) {
            <div hlmCardHeader><h2 hlmCardTitle>{{ 'events.your_registration' | t }}</h2></div>
          }
          <div hlmCardContent class="flex flex-col gap-3">
            @if (d.event.isLocked) {
              <p class="text-sm text-muted-foreground">{{ 'events.locked_message' | t }}</p>
            }
            @if (registered() || !d.event.isLocked) {
              <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input
                  hlmInput
                  class="sm:flex-1"
                  [attr.placeholder]="'events.comment' | t"
                  [attr.aria-label]="'events.comment' | t"
                  [value]="comment()"
                  [disabled]="d.event.isLocked || busy()"
                  (input)="comment.set($any($event.target).value)"
                />
                @if (registered()) {
                  <div class="grid grid-cols-2 gap-2 sm:flex">
                    <button hlmBtn variant="outline" type="button" [disabled]="d.event.isLocked || busy() || !changed()" (click)="saveComment(d)">
                      {{ 'events.save' | t }}
                    </button>
                    <button hlmBtn variant="destructive" type="button" [disabled]="d.event.isLocked || busy()" (click)="unregister(d)">
                      {{ 'events.unregister' | t }}
                    </button>
                  </div>
                } @else {
                  <button hlmBtn type="button" [disabled]="busy()" (click)="register(d)">
                    {{ (d.event.available <= 0 ? 'events.waitlist' : 'events.register') | t }}
                  </button>
                }
              </div>
            }
          </div>
        </section>

        @if (d.socialEvent; as social) {
          <app-social-event-row [event]="social" [link]="['/social-events', social.id]" />
        }

        <section hlmCard data-participants>
          <div hlmCardHeader><h2 hlmCardTitle>{{ 'events.participants' | t }}</h2></div>
          <div hlmCardContent class="flex flex-col gap-4">
            @for (g of participantGroups(); track g.key) {
              @if (g.names.length > 0) {
                <div data-participant-group>
                  <h3 class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">{{ g.key | t }} ({{ g.names.length }})</h3>
                  <ul class="gap-x-6 sm:columns-2">
                    @for (name of g.names; track $index) {
                      <li
                        class="-mx-2 break-inside-avoid rounded-md px-2 py-0.5 data-own:bg-primary/10 data-own:font-medium"
                        [attr.data-own]="name === ownName() ? '' : null"
                      >{{ name }}</li>
                    }
                  </ul>
                </div>
              }
            }
            @if (participantCount() === 0) {
              <p class="text-muted-foreground">{{ 'events.no_participants' | t }}</p>
            }
          </div>
        </section>
      </div>
    } @else if (detail.error()) {
      <div appPage>
        <p class="text-lg">{{ errorKey() | t }}</p>
        <a hlmBtn class="self-start" routerLink="/">{{ 'navigation.home' | t }}</a>
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
  /** The logged-in member's own entry is highlighted in the participant list. */
  protected readonly ownName = computed(() => this.session.user()?.displayName ?? null);
  protected readonly participantGroups = computed(() => {
    const d = this.current();
    if (!d) {
      return [];
    }
    return [
      { key: 'events.registered', names: d.participants.filter((p) => p.state === 'registered').map((p) => p.name) },
      { key: 'events.waitlist', names: d.participants.filter((p) => p.state === 'waitlist').map((p) => p.name) },
      { key: 'guests.title', names: d.guests.map((g) => `${g.firstName} ${g.lastName}`) },
    ];
  });
  protected readonly participantCount = computed(() => this.participantGroups().reduce((sum, g) => sum + g.names.length, 0));

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
