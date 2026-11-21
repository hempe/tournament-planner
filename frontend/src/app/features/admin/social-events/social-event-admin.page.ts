import { Component, ErrorHandler, computed, inject, input, resource, signal, untracked } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { HlmCardImports } from '@ui/card';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { asApiError } from '../../../core/api/api-error';
import { AdminSocialRegistrationDto, SocialEventAdminDto, SocialEventRequest, socialEventAdminApi } from '../../../core/api/generated';
import { ConfirmService } from '../../../core/feedback/confirm.service';
import { Notify } from '../../../core/feedback/notify';
import { formatDate } from '../../../core/format/dates';
import { I18n } from '../../../core/i18n/i18n';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { SocialEventForm } from './social-event-form';

@Component({
  selector: 'app-social-event-admin-page',
  imports: [RouterLink, HlmButtonImports, HlmCardImports, HlmSpinner, TranslatePipe, SocialEventForm],
  template: `
    @if (detail.hasValue()) {
      @let d = detail.value();
      <div class="mx-auto flex max-w-3xl flex-col gap-4">
        <div>
          <a hlmBtn variant="ghost" size="sm" [routerLink]="backUrl()">{{ 'nav.back' | t }}</a>
        </div>
        <header>
          <p class="text-sm text-muted-foreground">{{ formatDate(d.event.date) }}</p>
          <h1 class="text-2xl font-semibold">{{ d.event.name }}</h1>
        </header>

        <div class="flex flex-col gap-2 sm:flex-row">
          <button hlmBtn variant="outline" type="button" class="w-full sm:w-auto" [disabled]="busy()" (click)="toggleLock(d)">
            {{ (d.event.isLocked ? 'social_events.unlock' : 'social_events.lock') | t }}
          </button>
          <button hlmBtn variant="destructive" type="button" class="w-full sm:w-auto" [disabled]="busy()" (click)="deleteEvent(d)">
            {{ 'social_events.delete' | t }}
          </button>
        </div>

        <section hlmCard>
          <div hlmCardContent>
            <app-social-event-form
              [initial]="formInitial()"
              [tournamentId]="d.event.tournamentId"
              [error]="formError()"
              [busy]="busy()"
              (save)="saveEvent($event)"
            />
          </div>
        </section>

        <section hlmCard>
          <div hlmCardHeader><h2 hlmCardTitle>{{ 'social_events.participants' | t }}</h2></div>
          <div hlmCardContent class="flex flex-col gap-4">
            @for (r of d.registrations; track r.id) {
              <div data-participant class="flex flex-col gap-2 border-b pb-3 last:border-b-0 last:pb-0 sm:flex-row sm:items-center">
                <div class="sm:flex-1">
                  <span class="font-medium">{{ r.name }}</span>
                  @if (r.isGuest) {
                    <span class="ml-2 rounded-full border px-2 py-0.5 text-xs text-muted-foreground">{{ 'guests.guest' | t }}</span>
                  }
                  <span class="block text-sm text-muted-foreground">
                    @if (r.tableNumber !== null) { {{ 'social_events.table_number' | t: { number: r.tableNumber } }} }
                    @if (r.tableNumber !== null && r.menuName) { · }
                    @if (r.menuName) { {{ r.menuName }} }
                  </span>
                </div>
                <button hlmBtn size="sm" variant="destructive" type="button" class="w-full sm:w-auto" [disabled]="busy()" (click)="removeRegistration(d, r)">
                  {{ 'social_events.unregister' | t }}
                </button>
              </div>
            }
            <a hlmBtn variant="outline" class="w-full sm:w-auto sm:self-start" [routerLink]="['/social-events', d.event.id, 'guests', 'new']">
              {{ 'social_events.add_guest' | t }}
            </a>
          </div>
        </section>
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
export class SocialEventAdminPage {
  /** From the route (withComponentInputBinding). */
  readonly id = input.required<string>();

  private readonly api = inject(ApiClient);
  private readonly router = inject(Router);
  private readonly confirm = inject(ConfirmService);
  private readonly notify = inject(Notify);
  private readonly i18n = inject(I18n);
  private readonly errorHandler = inject(ErrorHandler);

  protected readonly detail = resource({
    params: () => this.id(),
    loader: ({ params, abortSignal }) => socialEventAdminApi(this.api).show(params, { signal: abortSignal }),
  });

  protected readonly busy = signal(false);
  protected readonly formError = signal<ApiError | null>(null);

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
      date: d.event.date,
      description: d.description,
      registrationClose: d.registrationClose,
      menus: d.menus,
      tables: d.tables,
    };
  });

  protected readonly backUrl = computed(() => {
    const tournamentId = this.detail.hasValue() ? this.detail.value().event.tournamentId : null;
    return tournamentId === null ? '/' : `/events/${tournamentId}/admin`;
  });

  protected readonly errorKey = computed(() => {
    const error = asApiError(this.detail.error());
    return error?.status === 404 ? 'social_events.not_found' : (error?.error ?? 'errors.general');
  });

  protected formatDate(value: string): string {
    return formatDate(value, this.i18n.locale());
  }

  protected async saveEvent(body: SocialEventRequest): Promise<void> {
    this.formError.set(null);
    await this.run(
      async () => {
        const fresh = await socialEventAdminApi(this.api).update(this.id(), body);
        this.notify.success('events.update_success');
        this.seedVersion.update((v) => v + 1);
        return fresh;
      },
      (error) => this.formError.set(error),
    );
  }

  protected async toggleLock(d: SocialEventAdminDto): Promise<void> {
    const locked = d.event.isLocked;
    if (!(await this.confirm.ask(this.i18n.t(locked ? 'social_events.unlock_confirm' : 'social_events.lock_confirm')))) {
      return;
    }
    await this.run(async () => {
      const api = socialEventAdminApi(this.api);
      const fresh = await (locked ? api.unlock(this.id()) : api.lock(this.id()));
      this.notify.success(locked ? 'events.unlock_success' : 'events.lock_success');
      return fresh;
    });
  }

  protected async deleteEvent(d: SocialEventAdminDto): Promise<void> {
    if (!(await this.confirm.ask(this.i18n.t('social_events.delete_confirm'), { danger: true }))) {
      return;
    }
    this.busy.set(true);
    try {
      await socialEventAdminApi(this.api).delete(this.id());
      this.notify.success('events.delete_success');
      await this.router.navigateByUrl(d.event.tournamentId === null ? '/' : `/events/${d.event.tournamentId}/admin`);
    } catch (error) {
      this.errorHandler.handleError(error);
    } finally {
      this.busy.set(false);
    }
  }

  protected async removeRegistration(d: SocialEventAdminDto, r: AdminSocialRegistrationDto): Promise<void> {
    if (!(await this.confirm.ask(this.i18n.t('events.unregister_user_confirm', { name: r.name }), { danger: true }))) {
      return;
    }
    await this.run(async () => {
      const fresh = await socialEventAdminApi(this.api).deleteRegistration(d.event.id, r.id);
      this.notify.success('events.unregistration_success');
      return fresh;
    });
  }

  /**
   * Runs a mutation and writes the returned DTO back. 422s go to `onInvalid` when given; every other error
   * (e.g. 409 with params) goes to the global error handler, which shows it as a toast.
   */
  private async run(action: () => Promise<SocialEventAdminDto>, onInvalid?: (error: ApiError) => void): Promise<void> {
    this.busy.set(true);
    try {
      this.detail.value.set(await action());
    } catch (error) {
      if (onInvalid && error instanceof ApiError && error.status === 422) {
        onInvalid(error);
        return;
      }
      this.errorHandler.handleError(error);
    } finally {
      this.busy.set(false);
    }
  }
}
