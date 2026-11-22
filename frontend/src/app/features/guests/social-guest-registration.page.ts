import { LoadError } from '../../shared/ui/load-error';
import { Component, computed, inject, input, resource, signal } from '@angular/core';
import { FormField, form, required, submit } from '@angular/forms/signals';
import { Router } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { HlmCardImports } from '@ui/card';
import { HlmInputImports } from '@ui/input';
import { HlmLabelImports } from '@ui/label';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient, ApiError } from '../../core/api/api-client';
import { socialEventsApi } from '../../core/api/generated';
import { SessionStore } from '../../core/auth/session.store';
import { Notify } from '../../core/feedback/notify';
import { formatDate } from '../../core/format/dates';
import { I18n } from '../../core/i18n/i18n';
import { TranslatePipe } from '../../core/i18n/translate.pipe';
import { BackLink } from '../../shared/ui/back-link';
import { Page, PageHeader } from '../../shared/ui/page';
import { translateFieldErrors } from '../../shared/forms/field-errors';
import { SELECT_CLASSES } from '../../shared/forms/select';

@Component({
  selector: 'app-social-guest-registration-page',
  imports: [LoadError, FormField, HlmButtonImports, HlmCardImports, HlmInputImports, HlmLabelImports, HlmSpinner, TranslatePipe, BackLink, Page, PageHeader],
  template: `
    @if (info.hasValue()) {
      @let d = info.value();
      <div appPage>
        <app-back-link [link]="backLink()" [queryParams]="session.isAdmin() ? null : { month: d.event.date.slice(0, 7) }" />
        <app-page-header [title]="d.event.name" [subtitle]="formatDate(d.event.date)" />

        @if (d.description) {
          <section hlmCard>
            <p hlmCardContent class="whitespace-pre-line">{{ d.description }}</p>
          </section>
        }

        @if (d.event.isLocked && !session.isAdmin()) {
          <p class="text-sm text-muted-foreground">{{ 'social_events.locked_message' | t }}</p>
        } @else {
          <section hlmCard>
            <form hlmCardContent class="flex flex-col gap-4" novalidate (submit)="$event.preventDefault(); register()">
              <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-2">
                  <label hlmLabel for="firstName">{{ 'guests.first_name' | t }} *</label>
                  <input hlmInput id="firstName" autocomplete="given-name" [formField]="guestForm.firstName" />
                  @if (fieldError('firstName', guestForm.firstName(), 'guests.first_name'); as message) {
                    <p id="firstName-error" class="text-sm text-destructive">{{ message }}</p>
                  }
                </div>
                <div class="flex flex-col gap-2">
                  <label hlmLabel for="lastName">{{ 'guests.last_name' | t }} *</label>
                  <input hlmInput id="lastName" autocomplete="family-name" [formField]="guestForm.lastName" />
                  @if (fieldError('lastName', guestForm.lastName(), 'guests.last_name'); as message) {
                    <p id="lastName-error" class="text-sm text-destructive">{{ message }}</p>
                  }
                </div>
              </div>
              <div class="flex flex-col gap-2">
                <label hlmLabel for="email">{{ 'guests.email' | t }}@if (!session.isAdmin()) { * }</label>
                <input hlmInput id="email" type="email" autocomplete="email" [formField]="guestForm.email" />
                @if (fieldError('email', guestForm.email(), 'guests.email'); as message) {
                  <p id="email-error" class="text-sm text-destructive">{{ message }}</p>
                }
              </div>
              @if (d.menus.length > 0) {
                <div class="flex flex-col gap-2">
                  <label hlmLabel for="menu">{{ 'social_events.menu' | t }} *</label>
                  <select id="menu" [class]="selectClasses" [formField]="guestForm.menuId">
                    <option value="">{{ 'social_events.select_menu' | t }}</option>
                    @for (m of d.menus; track m.id) {
                      <option [value]="m.id">{{ m.name }}</option>
                    }
                  </select>
                  @if (fieldError('menuId', guestForm.menuId(), 'social_events.menu'); as message) {
                    <p id="menuId-error" class="text-sm text-destructive">{{ message }}</p>
                  }
                </div>
              }
              @if (d.tables.length > 0) {
                <div class="flex flex-col gap-2">
                  <label hlmLabel for="table">{{ 'social_events.table' | t }}</label>
                  <select id="table" [class]="selectClasses" [formField]="guestForm.tableId">
                    <option value="">{{ 'social_events.libero' | t }}</option>
                    @for (tbl of d.tables; track tbl.id) {
                      <option [value]="tbl.id" [disabled]="tbl.available === 0">
                        {{ 'social_events.table_number' | t: { number: tbl.number } }}
                      </option>
                    }
                  </select>
                  @if (serverErrors()['tableId']; as message) {
                    <p id="tableId-error" class="text-sm text-destructive">{{ message }}</p>
                  }
                </div>
              }
              <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                <label hlmLabel for="website">{{ 'guests.website' | t }}</label>
                <input hlmInput id="website" tabindex="-1" autocomplete="off" [formField]="guestForm.website" />
              </div>
              <button hlmBtn type="submit" class="w-full sm:w-auto sm:self-start" [disabled]="busy()">
                {{ 'guests.register' | t }}
              </button>
            </form>
          </section>
        }
      </div>
    } @else if (info.error()) {
      <div appPage>
        <app-load-error [error]="info.error()" notFoundKey="social_events.not_found" [showHome]="true" (retry)="info.reload()" />
      </div>
    } @else {
      <div class="flex justify-center p-8"><hlm-spinner /></div>
    }
  `,
})
export class SocialGuestRegistrationPage {
  /** From the route (withComponentInputBinding). */
  readonly id = input.required<string>();

  protected readonly session = inject(SessionStore);
  private readonly api = inject(ApiClient);
  private readonly router = inject(Router);
  private readonly notify = inject(Notify);
  private readonly i18n = inject(I18n);
  protected readonly selectClasses = SELECT_CLASSES;

  protected readonly info = resource({
    params: () => this.id(),
    loader: ({ params, abortSignal }) => socialEventsApi(this.api).guestInfo(params, { signal: abortSignal }),
  });

  private readonly current = computed(() => (this.info.hasValue() ? this.info.value() : undefined));
  protected readonly busy = signal(false);
  protected readonly serverErrors = signal<Record<string, string>>({});
  protected readonly model = signal({ firstName: '', lastName: '', email: '', menuId: '', tableId: '', website: '' });
  protected readonly guestForm = form(this.model, (path) => {
    required(path.firstName);
    required(path.lastName);
    required(path.email, { when: () => !this.session.isAdmin() });
    required(path.menuId, { when: () => (this.current()?.menus.length ?? 0) > 0 });
  });

  protected readonly backLink = computed(() => (this.session.isAdmin() ? `/social-events/${this.id()}/admin` : '/'));

  protected formatDate(value: string): string {
    return formatDate(value, this.i18n.locale());
  }

  /** Required message once touched, otherwise the server's message for that field. */
  protected fieldError(name: string, field: { touched(): boolean; invalid(): boolean }, labelKey: string): string | null {
    if (field.touched() && field.invalid()) {
      return this.i18n.t('validation.required', { field: this.i18n.t(labelKey) });
    }
    return this.serverErrors()[name] ?? null;
  }

  protected async register(): Promise<void> {
    this.serverErrors.set({});
    await submit(this.guestForm, async () => {
      const m = this.model();
      this.busy.set(true);
      try {
        await socialEventsApi(this.api).addGuest(this.id(), {
          firstName: m.firstName,
          lastName: m.lastName,
          email: m.email || null,
          menuId: m.menuId === '' ? null : Number(m.menuId),
          tableId: m.tableId === '' ? null : Number(m.tableId),
          website: m.website,
        });
        this.notify.success('social_events.guest_register_success');
        await this.router.navigateByUrl(this.session.isAdmin() ? `/social-events/${this.id()}/admin` : '/');
      } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
          this.serverErrors.set(translateFieldErrors(error, this.i18n));
          return undefined;
        }
        throw error;
      } finally {
        this.busy.set(false);
      }
      return undefined;
    });
  }
}
