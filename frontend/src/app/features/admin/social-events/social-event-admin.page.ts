import { LoadError } from '../../../shared/ui/load-error';
import { Component, ErrorHandler, computed, inject, input, resource, signal, untracked } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucidePlus, lucideUserMinus } from '@ng-icons/lucide';
import { HlmButtonImports } from '@ui/button';
import { HlmCardImports } from '@ui/card';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { AdminSocialRegistrationDto, SocialEventAdminDto, SocialEventRequest, socialEventAdminApi } from '../../../core/api/generated';
import { ConfirmService } from '../../../core/feedback/confirm.service';
import { Notify } from '../../../core/feedback/notify';
import { formatDate } from '../../../core/format/dates';
import { I18n } from '../../../core/i18n/i18n';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { BackLink } from '../../../shared/ui/back-link';
import { Badge } from '../../../shared/ui/badge';
import { Page, PageHeader } from '../../../shared/ui/page';
import { SocialEventForm } from './social-event-form';

@Component({
  selector: 'app-social-event-admin-page',
  imports: [LoadError, RouterLink, NgIcon, HlmButtonImports, HlmCardImports, HlmSpinner, TranslatePipe, SocialEventForm, BackLink, Badge, Page, PageHeader],
  providers: [provideIcons({ lucidePlus, lucideUserMinus })],
  template: `
    @if (detail.hasValue()) {
      @let d = detail.value();
      <div appPage>
        <app-back-link [link]="backUrl()" />
        <app-page-header [title]="d.event.name" [subtitle]="formatDate(d.event.date)">
          <div meta class="flex flex-wrap gap-2 empty:hidden">
            @if (d.event.isLocked) {
              <span appBadge variant="secondary">{{ 'events.locked' | t }}</span>
            }
          </div>
          <button hlmBtn variant="outline" size="sm" type="button" [disabled]="busy()" (click)="toggleLock(d)">
            {{ (d.event.isLocked ? 'social_events.unlock' : 'social_events.lock') | t }}
          </button>
          <button hlmBtn variant="destructive" size="sm" type="button" [disabled]="busy()" (click)="deleteEvent(d)">
            {{ 'social_events.delete' | t }}
          </button>
        </app-page-header>

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

        <section hlmCard data-participants>
          <div hlmCardHeader>
            <h2 hlmCardTitle>{{ 'social_events.participants' | t }} ({{ d.registrations.length }})</h2>
            <a hlmCardAction hlmBtn variant="outline" size="sm" [routerLink]="['/social-events', d.event.id, 'guests', 'new']">
              <ng-icon name="lucidePlus" />{{ 'social_events.add_guest' | t }}
            </a>
          </div>
          <div hlmCardContent>
            @if (d.registrations.length === 0) {
              <p class="text-muted-foreground">{{ 'events.no_participants' | t }}</p>
            } @else {
              <ul class="divide-y">
                @for (r of d.registrations; track r.id) {
                  <li data-participant class="flex items-center gap-2 py-1.5">
                    <div class="flex min-w-0 flex-1 flex-col pt-[0.3em]">
                      <div class="flex flex-wrap items-center gap-x-2">
                        <span class="font-medium">{{ r.name }}</span>
                        @if (r.isGuest) {
                          <span appBadge variant="outline">{{ 'guests.guest' | t }}</span>
                        }
                      </div>
                      <span class="text-sm text-muted-foreground">
                        {{ r.tableNumber === null ? ('social_events.libero' | t) : ('social_events.table_number' | t: { number: r.tableNumber }) }}@if (r.menuName) { · {{ r.menuName }} }
                      </span>
                    </div>
                    <button
                      hlmBtn
                      variant="ghost"
                      size="icon-sm"
                      type="button"
                      class="shrink-0 text-destructive hover:text-destructive"
                      [attr.aria-label]="'social_events.unregister' | t"
                      [attr.title]="'social_events.unregister' | t"
                      [disabled]="busy()"
                      (click)="removeRegistration(d, r)"
                    >
                      <ng-icon name="lucideUserMinus" />
                    </button>
                  </li>
                }
              </ul>
            }
          </div>
        </section>
      </div>
    } @else if (detail.error()) {
      <div appPage>
        <app-load-error [error]="detail.error()" notFoundKey="social_events.not_found" [showHome]="true" (retry)="detail.reload()" />
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
