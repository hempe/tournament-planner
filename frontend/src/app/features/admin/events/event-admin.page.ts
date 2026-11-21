import { Component, computed, inject, input, resource, signal, untracked } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucidePencil, lucidePlus, lucideSearch, lucideTrash2, lucideUserMinus, lucideX } from '@ng-icons/lucide';
import { HlmButtonImports } from '@ui/button';
import { HlmCardImports } from '@ui/card';
import { HlmInputImports } from '@ui/input';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { asApiError } from '../../../core/api/api-error';
import {
  AdminGuestDto,
  AdminRegistrationDto,
  EventAdminDto,
  EventRequest,
  EventUpdateRequest,
  UserOptionDto,
  eventAdminApi,
} from '../../../core/api/generated';
import { ConfirmService } from '../../../core/feedback/confirm.service';
import { Notify } from '../../../core/feedback/notify';
import { formatDate, formatDateTime } from '../../../core/format/dates';
import { I18n } from '../../../core/i18n/i18n';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { BackLink } from '../../../shared/ui/back-link';
import { Badge } from '../../../shared/ui/badge';
import { Page, PageHeader } from '../../../shared/ui/page';
import { SocialEventRow } from '../../social-events/social-event-row';
import { EventForm } from './event-form';
import { filterMembers } from './member-search';

/** Matches shown below the member search. */
const MAX_MATCHES = 8;

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
  imports: [
    RouterLink,
    NgIcon,
    HlmButtonImports,
    HlmCardImports,
    HlmInputImports,
    HlmSpinner,
    TranslatePipe,
    EventForm,
    BackLink,
    Badge,
    Page,
    PageHeader,
    SocialEventRow,
  ],
  providers: [provideIcons({ lucidePencil, lucidePlus, lucideSearch, lucideTrash2, lucideUserMinus, lucideX })],
  template: `
    @if (detail.hasValue()) {
      @let d = detail.value();
      <div appPage>
        <app-back-link [link]="['/events', d.event.id]" />
        <app-page-header [title]="d.event.name" [subtitle]="formatDate(d.event.date)">
          <div meta class="flex flex-wrap gap-2 empty:hidden">
            @if (d.event.isLocked) {
              <span appBadge variant="secondary">{{ 'events.locked' | t }}</span>
            }
          </div>
          <button hlmBtn variant="outline" size="sm" type="button" [disabled]="busy()" (click)="toggleLock(d)">
            {{ (d.event.isLocked ? 'events.unlock' : 'events.lock') | t }}
          </button>
          <a hlmBtn variant="outline" size="sm" [href]="exportUrl()" download>{{ 'events.export' | t }}</a>
          <button hlmBtn variant="destructive" size="sm" type="button" [disabled]="busy()" (click)="deleteEvent()">
            {{ 'events.delete' | t }}
          </button>
        </app-page-header>

        <section hlmCard>
          <div hlmCardContent>
            <app-event-form mode="edit" [initial]="formInitial()" [error]="formError()" [busy]="busy()" (save)="saveEvent($event)" />
          </div>
        </section>

        @if (d.socialEvent; as social) {
          <app-social-event-row [event]="social" [link]="['/social-events', social.id, 'admin']" note="social_events.edit" />
        } @else {
          <a hlmBtn variant="outline" size="sm" class="self-start" routerLink="/social-events/new" [queryParams]="{ tournamentId: d.event.id }">
            {{ 'social_events.new' | t }}
          </a>
        }

        <section hlmCard data-registrations>
          <div hlmCardHeader>
            <h2 hlmCardTitle>{{ 'events.registrations' | t }} ({{ d.event.joined }}/{{ d.event.capacity }})</h2>
          </div>
          <div hlmCardContent class="flex flex-col gap-4">
            @for (g of groups(); track g.key + g.waitlist) {
              @if (g.rows.length > 0) {
                <div data-group>
                  <h3 class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {{ g.key | t }}@if (g.waitlist) { – {{ 'events.waitlist' | t }} }<span data-count> ({{ g.rows.length }})</span>
                  </h3>
                  <ul class="divide-y">
                    @for (r of g.rows; track r.userId) {
                      <li data-row class="flex items-center gap-2 py-1.5">
                        <div class="flex min-w-0 flex-1 flex-col pt-[0.3em]">
                          <div class="flex flex-wrap items-baseline gap-x-2">
                            <span data-name class="font-medium">{{ r.name }}</span>
                            <span class="text-xs text-muted-foreground">{{ formatDateTime(r.registeredAt) }}</span>
                          </div>
                          @if (editing() === r.userId) {
                            <div class="mt-1 flex items-center gap-2">
                              <input
                                hlmInput
                                class="h-8 flex-1"
                                [attr.aria-label]="'events.comment' | t"
                                [attr.placeholder]="'events.comment' | t"
                                [value]="draft('r' + r.userId, r.comment)"
                                (input)="setDraft('r' + r.userId, $any($event.target).value)"
                                (keydown.enter)="saveComment(r)"
                                (keydown.escape)="cancelEdit(r)"
                              />
                              <button hlmBtn size="sm" type="button" [disabled]="busy()" (click)="saveComment(r)">{{ 'events.save' | t }}</button>
                              <button hlmBtn variant="ghost" size="icon-sm" type="button" [attr.aria-label]="'actions.cancel' | t" (click)="cancelEdit(r)">
                                <ng-icon name="lucideX" />
                              </button>
                            </div>
                          } @else if (r.comment) {
                            <span data-comment class="text-sm break-words text-muted-foreground">{{ r.comment }}</span>
                          }
                        </div>
                        <div class="flex shrink-0 gap-0.5">
                          @if (editing() !== r.userId) {
                            <button
                              hlmBtn
                              variant="ghost"
                              size="icon-sm"
                              type="button"
                              [attr.aria-label]="'events.edit_comment' | t"
                              [attr.title]="'events.edit_comment' | t"
                              (click)="editing.set(r.userId)"
                            >
                              <ng-icon name="lucidePencil" />
                            </button>
                          }
                          <button
                            hlmBtn
                            variant="ghost"
                            size="icon-sm"
                            type="button"
                            class="text-destructive hover:text-destructive"
                            [attr.aria-label]="'events.unregister' | t"
                            [attr.title]="'events.unregister' | t"
                            [disabled]="busy()"
                            (click)="unregister(r)"
                          >
                            <ng-icon name="lucideUserMinus" />
                          </button>
                        </div>
                      </li>
                    }
                  </ul>
                </div>
              }
            }
            @if (d.registrations.length === 0) {
              <p class="text-muted-foreground">{{ 'events.no_participants' | t }}</p>
            }
          </div>
        </section>

        <section hlmCard data-add-member>
          <div hlmCardHeader><h2 hlmCardTitle>{{ 'events.add_member' | t }}</h2></div>
          <div hlmCardContent class="flex flex-col gap-2">
            @if (d.availableUsers.length === 0) {
              <p class="text-muted-foreground">{{ 'events.all_members_registered' | t }}</p>
            } @else if (selected(); as user) {
              <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <span data-selected class="inline-flex h-9 items-center gap-1 rounded-md border bg-muted/50 pr-1 pl-2.5 text-sm sm:max-w-[16rem]">
                  <span class="truncate pt-[0.3em] font-medium">{{ user.displayName }}</span>
                  <button hlmBtn variant="ghost" size="icon-xs" type="button" class="ml-auto" [attr.aria-label]="'actions.cancel' | t" (click)="clearSelection()">
                    <ng-icon name="lucideX" />
                  </button>
                </span>
                <input
                  hlmInput
                  class="sm:flex-1"
                  [attr.aria-label]="'events.comment' | t"
                  [attr.placeholder]="'events.comment' | t"
                  [value]="addComment()"
                  (input)="addComment.set($any($event.target).value)"
                />
                <button hlmBtn type="button" [disabled]="busy()" (click)="registerUser(d, user.id, user.displayName)">
                  {{ (d.event.available > 0 ? 'events.register' : 'events.waitlist') | t }}
                </button>
              </div>
            } @else {
              <div class="relative">
                <ng-icon name="lucideSearch" class="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                <input
                  hlmInput
                  class="pl-8"
                  data-member-search
                  autocomplete="off"
                  [attr.aria-label]="'events.search_member' | t"
                  [attr.placeholder]="'events.search_member' | t"
                  [value]="query()"
                  (input)="query.set($any($event.target).value)"
                  (keydown.enter)="selectFirstMatch()"
                />
              </div>
              @if (query().trim()) {
                @let found = matches();
                @if (found.users.length === 0) {
                  <p class="px-2.5 text-sm text-muted-foreground">{{ 'events.no_member_found' | t }}</p>
                } @else {
                  <ul data-matches class="flex flex-col">
                    @for (u of found.users; track u.id) {
                      <li>
                        <button type="button" data-match class="w-full rounded-md px-2.5 pt-[calc(0.375rem+0.2em)] pb-1.5 text-left text-sm hover:bg-muted focus-visible:bg-muted focus-visible:outline-none" (click)="select(u)">
                          {{ u.displayName }}
                        </button>
                      </li>
                    }
                  </ul>
                  @if (found.more > 0) {
                    <p class="px-2.5 text-xs text-muted-foreground">{{ 'events.more_matches' | t: { count: found.more } }}</p>
                  }
                }
              }
            }
          </div>
        </section>

        <section hlmCard data-guests>
          <div hlmCardHeader>
            <h2 hlmCardTitle>{{ 'guests.title' | t }} ({{ d.guests.length }})</h2>
            <a hlmCardAction hlmBtn variant="outline" size="sm" [routerLink]="['/events', d.event.id, 'guests', 'new']">
              <ng-icon name="lucidePlus" />{{ 'guests.add' | t }}
            </a>
          </div>
          <div hlmCardContent class="flex flex-col gap-4">
            @for (g of guestGroups(); track g.key) {
              @if (g.rows.length > 0) {
                <div data-guest-group>
                  @if (g.key) {
                    <h3 class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">{{ g.key | t }} ({{ g.rows.length }})</h3>
                  }
                  <ul class="divide-y">
                    @for (guest of g.rows; track guest.id) {
                      <li data-guest class="flex items-center gap-2 py-1.5">
                        <div class="flex min-w-0 flex-1 flex-col pt-[0.3em]">
                          <div class="flex flex-wrap items-baseline gap-x-2">
                            <span class="font-medium">{{ guest.firstName }} {{ guest.lastName }}</span>
                            <span class="text-xs text-muted-foreground">{{ formatDateTime(guest.registeredAt) }}</span>
                          </div>
                          <span class="text-sm break-words text-muted-foreground">
                            {{ guestDetails(guest) }}
                          </span>
                        </div>
                        <div class="flex shrink-0 gap-0.5">
                          <a
                            hlmBtn
                            variant="ghost"
                            size="icon-sm"
                            [attr.aria-label]="'guests.edit' | t"
                            [attr.title]="'guests.edit' | t"
                            [routerLink]="['/events', d.event.id, 'guests', guest.id, 'edit']"
                          >
                            <ng-icon name="lucidePencil" />
                          </a>
                          <button
                            hlmBtn
                            variant="ghost"
                            size="icon-sm"
                            type="button"
                            class="text-destructive hover:text-destructive"
                            [attr.aria-label]="'events.delete' | t"
                            [attr.title]="'events.delete' | t"
                            [disabled]="busy()"
                            (click)="deleteGuest(guest)"
                          >
                            <ng-icon name="lucideTrash2" />
                          </button>
                        </div>
                      </li>
                    }
                  </ul>
                </div>
              }
            }
            @if (d.guests.length === 0) {
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
  /** Unsaved registration comments, keyed 'r<userId>'. */
  private readonly drafts = signal<Record<string, string>>({});
  /** User id of the registration whose comment is being edited. */
  protected readonly editing = signal<number | null>(null);

  /** "Mitglied hinzufügen": search text, the picked member and the comment for the registration. */
  protected readonly query = signal('');
  protected readonly selected = signal<UserOptionDto | null>(null);
  protected readonly addComment = signal('');
  protected readonly matches = computed(() =>
    filterMembers(this.detail.hasValue() ? this.detail.value().availableUsers : [], this.query(), MAX_MATCHES),
  );

  /** Bumped after the form's own save so the form is re-seeded only then (and on load), never by other actions. */
  private readonly seedVersion = signal(0);
  protected readonly formInitial = computed(() => {
    this.seedVersion();
    const d = this.detail.hasValue() ? untracked(() => this.detail.value()) : undefined;
    if (!d) {
      return {};
    }
    return {
      name: d.event.name,
      capacity: d.event.capacity,
      mixed: d.mixed,
      description: d.description,
      priceMembers: d.priceMembers,
      priceGuests: d.priceGuests,
      registrationClose: d.registrationClose,
    };
  });

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
        this.seedVersion.update((v) => v + 1);
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

  protected select(user: UserOptionDto): void {
    this.selected.set(user);
  }

  protected selectFirstMatch(): void {
    const first = this.matches().users[0];
    if (first) {
      this.select(first);
    }
  }

  protected clearSelection(): void {
    this.selected.set(null);
    this.addComment.set('');
  }

  protected async registerUser(d: EventAdminDto, userId: number, name: string): Promise<void> {
    const key = d.event.available > 0 ? 'auth.register_user' : 'auth.register_user_waitlist';
    if (!(await this.confirm.ask(this.i18n.t(key, { username: name })))) {
      return;
    }
    await this.run(async () => {
      const fresh = await eventAdminApi(this.api).register(this.id(), userId, { comment: this.addComment() });
      this.clearSelection();
      this.query.set('');
      this.notify.success('events.registration_success');
      return fresh;
    });
  }

  protected cancelEdit(r: AdminRegistrationDto): void {
    this.clearDraft(`r${r.userId}`);
    this.editing.set(null);
  }

  protected guestDetails(guest: AdminGuestDto): string {
    return [
      guest.email,
      guest.handicap === null ? null : `${this.i18n.t('guests.handicap')}: ${guest.handicap}`,
      guest.comment,
    ]
      .filter((part) => part)
      .join(' · ');
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
      this.editing.set(null);
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
