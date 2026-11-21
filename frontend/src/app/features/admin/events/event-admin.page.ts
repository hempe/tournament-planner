import { Component, computed, inject, input, resource, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { HlmCardImports } from '@ui/card';
import { HlmInputImports } from '@ui/input';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { asApiError } from '../../../core/api/api-error';
import { AdminGuestDto, AdminRegistrationDto, EventAdminDto, EventRequest, EventUpdateRequest, eventAdminApi } from '../../../core/api/generated';
import { ConfirmService } from '../../../core/feedback/confirm.service';
import { Notify } from '../../../core/feedback/notify';
import { formatDate, formatDateTime } from '../../../core/format/dates';
import { I18n } from '../../../core/i18n/i18n';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { EventForm } from './event-form';

interface RegistrationGroup {
  /** Heading key. */
  key: string;
  /** Appends " – Warteliste" to the heading. */
  waitlist: boolean;
  rows: AdminRegistrationDto[];
}

interface GuestGroup {
  /** Heading key, null when the event is mixed (one list). */
  key: string | null;
  rows: AdminGuestDto[];
}

@Component({
  selector: 'app-event-admin-page',
  imports: [RouterLink, HlmButtonImports, HlmCardImports, HlmInputImports, HlmSpinner, TranslatePipe, EventForm],
  template: `
    @if (detail.hasValue()) {
      @let d = detail.value();
      <div class="mx-auto flex max-w-3xl flex-col gap-4">
        <div>
          <a hlmBtn variant="ghost" size="sm" [routerLink]="['/events', d.event.id]">{{ 'nav.back' | t }}</a>
        </div>
        <header>
          <p class="text-sm text-muted-foreground">{{ formatDate(d.event.date) }}</p>
          <h1 class="text-2xl font-semibold">{{ d.event.name }}</h1>
        </header>

        <div class="flex flex-col gap-2 sm:flex-row">
          <button hlmBtn variant="outline" type="button" class="w-full sm:w-auto" [disabled]="busy()" (click)="toggleLock(d)">
            {{ (d.event.isLocked ? 'events.unlock' : 'events.lock') | t }}
          </button>
          <a hlmBtn variant="outline" class="w-full sm:w-auto" [href]="exportUrl()" download>{{ 'events.export' | t }}</a>
          <button hlmBtn variant="destructive" type="button" class="w-full sm:w-auto" [disabled]="busy()" (click)="deleteEvent()">
            {{ 'events.delete' | t }}
          </button>
        </div>

        <section hlmCard>
          <div hlmCardContent>
            <app-event-form
              mode="edit"
              [initial]="{
                name: d.event.name,
                capacity: d.event.capacity,
                mixed: d.mixed,
                description: d.description,
                priceMembers: d.priceMembers,
                priceGuests: d.priceGuests,
                registrationClose: d.registrationClose
              }"
              [error]="formError()"
              [busy]="busy()"
              (save)="saveEvent($event)"
            />
          </div>
        </section>

        @for (g of groups(); track g.key + g.waitlist) {
          @if (g.rows.length > 0) {
            <section hlmCard data-group>
              <div hlmCardHeader>
                <h2 hlmCardTitle>{{ g.key | t }}@if (g.waitlist) { – {{ 'events.waitlist' | t }} }</h2>
              </div>
              <ul hlmCardContent class="flex flex-col gap-3">
                @for (r of g.rows; track r.userId) {
                  <li data-row class="flex flex-col gap-2 border-b pb-3 last:border-b-0 last:pb-0">
                    <div>
                      <span data-name class="font-medium">{{ r.name }}</span>
                      <span class="block text-xs text-muted-foreground">{{ formatDateTime(r.registeredAt) }}</span>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                      <input
                        hlmInput
                        class="sm:flex-1"
                        [attr.aria-label]="'events.comment' | t"
                        [attr.placeholder]="'events.comment' | t"
                        [value]="draft('r' + r.userId, r.comment)"
                        (input)="setDraft('r' + r.userId, $any($event.target).value)"
                      />
                      <button hlmBtn size="sm" type="button" class="w-full sm:w-auto" [disabled]="busy()" (click)="saveComment(r)">
                        {{ 'events.save' | t }}
                      </button>
                      <button hlmBtn size="sm" variant="destructive" type="button" class="w-full sm:w-auto" [disabled]="busy()" (click)="unregister(r)">
                        {{ 'events.unregister' | t }}
                      </button>
                    </div>
                  </li>
                }
              </ul>
            </section>
          }
        }

        @if (d.availableUsers.length > 0) {
          <section hlmCard>
            <div hlmCardHeader><h2 hlmCardTitle>{{ 'auth.not_registered' | t }}</h2></div>
            <ul hlmCardContent class="flex flex-col gap-3">
              @for (u of d.availableUsers; track u.id) {
                <li data-row class="flex flex-col gap-2 sm:flex-row sm:items-center">
                  <span class="font-medium sm:w-48">{{ u.displayName }}</span>
                  <input
                    hlmInput
                    class="sm:flex-1"
                    [attr.aria-label]="'events.comment' | t"
                    [attr.placeholder]="'events.comment' | t"
                    [value]="draft('u' + u.id, '')"
                    (input)="setDraft('u' + u.id, $any($event.target).value)"
                  />
                  <button hlmBtn size="sm" type="button" class="w-full sm:w-auto" [disabled]="busy()" (click)="registerUser(d, u.id, u.displayName)">
                    {{ 'events.register' | t }}
                  </button>
                </li>
              }
            </ul>
          </section>
        }

        <section hlmCard>
          <div hlmCardHeader><h2 hlmCardTitle>{{ 'guests.title' | t }}</h2></div>
          <div hlmCardContent class="flex flex-col gap-4">
            @for (g of guestGroups(); track g.key) {
              <div data-guest-group class="flex flex-col gap-3">
                @if (g.key) {
                  <h3 class="text-sm font-medium text-muted-foreground">{{ g.key | t }}</h3>
                }
                @for (guest of g.rows; track guest.id) {
                  <div data-guest class="flex flex-col gap-2 border-b pb-3 last:border-b-0 last:pb-0">
                    <div>
                      <span class="font-medium">{{ guest.firstName }} {{ guest.lastName }}</span>
                      <span class="block text-xs text-muted-foreground">{{ formatDateTime(guest.registeredAt) }}</span>
                    </div>
                    <dl class="text-sm text-muted-foreground">
                      @if (guest.email) { <dd>{{ guest.email }}</dd> }
                      @if (guest.handicap !== null) { <dd>{{ 'guests.handicap' | t }}: {{ guest.handicap }}</dd> }
                      @if (guest.comment) { <dd>{{ guest.comment }}</dd> }
                    </dl>
                    <div class="flex flex-col gap-2 sm:flex-row">
                      <a hlmBtn size="sm" variant="outline" class="w-full sm:w-auto" [routerLink]="['/events', d.event.id, 'guests', guest.id, 'edit']">
                        {{ 'guests.edit' | t }}
                      </a>
                      <button hlmBtn size="sm" variant="destructive" type="button" class="w-full sm:w-auto" [disabled]="busy()" (click)="deleteGuest(guest)">
                        {{ 'events.delete' | t }}
                      </button>
                    </div>
                  </div>
                }
              </div>
            }
            <a hlmBtn variant="outline" class="w-full sm:w-auto sm:self-start" [routerLink]="['/events', d.event.id, 'guests', 'new']">
              {{ 'guests.add' | t }}
            </a>
          </div>
        </section>

        @if (d.socialEvent; as social) {
          <a
            [routerLink]="['/social-events', social.id, 'admin']"
            class="rounded-xl border-2 border-[var(--social)] p-4 font-medium text-[var(--social)]"
          >
            {{ social.name }} – {{ 'social_events.edit' | t }}
          </a>
        } @else {
          <a hlmBtn variant="outline" routerLink="/social-events/new" [queryParams]="{ tournamentId: d.event.id }" class="w-full sm:w-auto sm:self-start">
            {{ 'social_events.new' | t }}
          </a>
        }
      </div>
    } @else if (detail.error()) {
      <div class="mx-auto max-w-3xl">
        <p class="text-lg">{{ errorKey() | t }}</p>
        <a hlmBtn class="mt-4" routerLink="/">{{ 'navigation.home' | t }}</a>
      </div>
    } @else {
      <div class="flex justify-center p-8"><hlm-spinner /></div>
    }
  `,
})
export class EventAdminPage {
  /** From the route (withComponentInputBinding). */
  readonly id = input.required<string>();

  private readonly api = inject(ApiClient);
  private readonly router = inject(Router);
  private readonly confirm = inject(ConfirmService);
  private readonly notify = inject(Notify);
  private readonly i18n = inject(I18n);

  protected readonly detail = resource({
    params: () => this.id(),
    loader: ({ params, abortSignal }) => eventAdminApi(this.api).show(params, { signal: abortSignal }),
  });

  protected readonly busy = signal(false);
  protected readonly formError = signal<ApiError | null>(null);
  /** Unsaved comment inputs: 'r<userId>' for registrations, 'u<userId>' for users to add. */
  private readonly drafts = signal<Record<string, string>>({});

  protected readonly exportUrl = computed(() => `/api/events/${this.id()}/export`);
  protected readonly errorKey = computed(() => {
    const error = asApiError(this.detail.error());
    return error?.status === 404 ? 'events.not_found' : (error?.error ?? 'errors.general');
  });

  protected readonly groups = computed<RegistrationGroup[]>(() => {
    if (!this.detail.hasValue()) {
      return [];
    }
    const d = this.detail.value();
    const of = (state: string, male?: boolean) => d.registrations.filter((r) => r.state === state && (male === undefined || r.male === male));
    if (d.mixed) {
      return [
        { key: 'events.participants', waitlist: false, rows: of('registered') },
        { key: 'events.waitlist', waitlist: false, rows: of('waitlist') },
      ];
    }
    return [
      { key: 'events.male', waitlist: false, rows: of('registered', true) },
      { key: 'events.female', waitlist: false, rows: of('registered', false) },
      { key: 'events.male', waitlist: true, rows: of('waitlist', true) },
      { key: 'events.female', waitlist: true, rows: of('waitlist', false) },
    ];
  });

  protected readonly guestGroups = computed<GuestGroup[]>(() => {
    if (!this.detail.hasValue()) {
      return [];
    }
    const d = this.detail.value();
    if (d.mixed) {
      return [{ key: null, rows: d.guests }];
    }
    return [
      { key: 'events.male', rows: d.guests.filter((g) => g.male) },
      { key: 'events.female', rows: d.guests.filter((g) => !g.male) },
    ];
  });

  protected formatDate(value: string): string {
    return formatDate(value, this.i18n.locale());
  }

  protected formatDateTime(value: string): string {
    return formatDateTime(value, this.i18n.locale());
  }

  protected draft(key: string, saved: string): string {
    return this.drafts()[key] ?? saved;
  }

  protected setDraft(key: string, value: string): void {
    this.drafts.update((d) => ({ ...d, [key]: value }));
  }

  private clearDraft(key: string): void {
    this.drafts.update((d) => Object.fromEntries(Object.entries(d).filter(([k]) => k !== key)));
  }

  protected async saveEvent(body: EventRequest | EventUpdateRequest): Promise<void> {
    this.formError.set(null);
    await this.run(
      async () => {
        const fresh = await eventAdminApi(this.api).update(this.id(), body as EventUpdateRequest);
        this.notify.success('events.update_success');
        return fresh;
      },
      (error) => this.formError.set(error),
    );
  }

  protected async toggleLock(d: EventAdminDto): Promise<void> {
    const locked = d.event.isLocked;
    if (!(await this.confirm.ask(this.i18n.t(locked ? 'events.unlock_confirm' : 'events.lock_confirm')))) {
      return;
    }
    await this.run(async () => {
      const api = eventAdminApi(this.api);
      const fresh = await (locked ? api.unlock(this.id()) : api.lock(this.id()));
      this.notify.success(locked ? 'events.unlock_success' : 'events.lock_success');
      return fresh;
    });
  }

  protected async deleteEvent(): Promise<void> {
    if (!(await this.confirm.ask(this.i18n.t('events.delete_confirm_short'), { danger: true }))) {
      return;
    }
    this.busy.set(true);
    try {
      await eventAdminApi(this.api).delete(this.id());
      this.notify.success('events.delete_success');
      await this.router.navigateByUrl('/');
    } finally {
      this.busy.set(false);
    }
  }

  protected async registerUser(d: EventAdminDto, userId: number, name: string): Promise<void> {
    const key = d.event.available > 0 ? 'auth.register_user' : 'auth.register_user_waitlist';
    if (!(await this.confirm.ask(this.i18n.t(key, { username: name })))) {
      return;
    }
    await this.run(async () => {
      const fresh = await eventAdminApi(this.api).register(this.id(), userId, { comment: this.draft(`u${userId}`, '') });
      this.clearDraft(`u${userId}`);
      this.notify.success('events.registration_success');
      return fresh;
    });
  }

  protected async unregister(r: AdminRegistrationDto): Promise<void> {
    if (!(await this.confirm.ask(this.i18n.t('events.unregister_user_confirm', { name: r.name }), { danger: true }))) {
      return;
    }
    await this.run(async () => {
      const fresh = await eventAdminApi(this.api).unregister(this.id(), r.userId);
      this.notify.success('events.unregistration_success');
      return fresh;
    });
  }

  protected async saveComment(r: AdminRegistrationDto): Promise<void> {
    await this.run(async () => {
      const fresh = await eventAdminApi(this.api).updateComment(this.id(), r.userId, { comment: this.draft(`r${r.userId}`, r.comment) });
      this.clearDraft(`r${r.userId}`);
      this.notify.success('events.comment_update_success');
      return fresh;
    });
  }

  protected async deleteGuest(guest: AdminGuestDto): Promise<void> {
    if (!(await this.confirm.ask(this.i18n.t('guests.delete_confirm'), { danger: true }))) {
      return;
    }
    this.busy.set(true);
    try {
      await eventAdminApi(this.api).deleteGuest(this.id(), guest.id);
      this.notify.success('guests.delete_success');
      this.detail.reload();
    } finally {
      this.busy.set(false);
    }
  }

  /** Runs a mutation and writes the returned DTO back; 422s go to `onInvalid` when given. */
  private async run(action: () => Promise<EventAdminDto>, onInvalid?: (error: ApiError) => void): Promise<void> {
    this.busy.set(true);
    try {
      this.detail.value.set(await action());
    } catch (error) {
      if (onInvalid && error instanceof ApiError && error.status === 422) {
        onInvalid(error);
        return;
      }
      throw error;
    } finally {
      this.busy.set(false);
    }
  }
}
