import { Component, computed, inject, input, resource, signal } from '@angular/core';
import { FormField, form, required, submit } from '@angular/forms/signals';
import { Router, RouterLink } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { HlmCardImports } from '@ui/card';
import { HlmInputImports } from '@ui/input';
import { HlmLabelImports } from '@ui/label';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient, ApiError } from '../../core/api/api-client';
import { asApiError } from '../../core/api/api-error';
import { eventsApi } from '../../core/api/generated';
import { SessionStore } from '../../core/auth/session.store';
import { Notify } from '../../core/feedback/notify';
import { formatDate, formatDateTime } from '../../core/format/dates';
import { I18n } from '../../core/i18n/i18n';
import { TranslatePipe } from '../../core/i18n/translate.pipe';
import { SELECT_CLASSES } from '../social-events/social-event-detail.page';
import { translateFieldErrors } from './field-errors';

@Component({
  selector: 'app-guest-registration-page',
  imports: [FormField, RouterLink, HlmButtonImports, HlmCardImports, HlmInputImports, HlmLabelImports, HlmSpinner, TranslatePipe],
  template: `
    @if (info.hasValue()) {
      @let e = info.value();
      <div class="mx-auto flex max-w-2xl flex-col gap-4">
        <header>
          <p class="text-sm text-muted-foreground">{{ formatDate(e.date) }}</p>
          <h1 class="text-2xl font-semibold">{{ e.name }}</h1>
        </header>

        @if (e.description || e.priceMembers || e.priceGuests || e.registrationClose) {
          <section hlmCard>
            <dl hlmCardContent class="flex flex-col gap-3">
              @if (e.description) {
                <div>
                  <dt class="text-sm text-muted-foreground">{{ 'events.description' | t }}</dt>
                  <dd class="whitespace-pre-line">{{ e.description }}</dd>
                </div>
              }
              @if (e.priceMembers) {
                <div>
                  <dt class="text-sm text-muted-foreground">{{ 'events.price_members' | t }}</dt>
                  <dd>{{ e.priceMembers }}</dd>
                </div>
              }
              @if (e.priceGuests) {
                <div>
                  <dt class="text-sm text-muted-foreground">{{ 'events.price_guests' | t }}</dt>
                  <dd>{{ e.priceGuests }}</dd>
                </div>
              }
              @if (e.registrationClose) {
                <div>
                  <dt class="text-sm text-muted-foreground">{{ 'events.registration_close' | t }}</dt>
                  <dd>{{ formatDateTime(e.registrationClose) }}</dd>
                </div>
              }
            </dl>
          </section>
        }

        @if (e.isLocked && !session.isAdmin()) {
          <p class="text-sm text-muted-foreground">{{ 'events.locked_message' | t }}</p>
        } @else {
          <section hlmCard>
            <form hlmCardContent class="flex flex-col gap-4" novalidate (submit)="$event.preventDefault(); register()">
              <div class="flex flex-col gap-2">
                <label hlmLabel for="male">{{ 'users.salutation' | t }} *</label>
                <select id="male" [class]="selectClasses" [formField]="guestForm.male">
                  <option value=""></option>
                  <option value="true">{{ 'users.mr' | t }}</option>
                  <option value="false">{{ 'users.mrs' | t }}</option>
                </select>
                @if (fieldError('male', guestForm.male(), 'users.salutation'); as message) {
                  <p id="male-error" class="text-sm text-destructive">{{ message }}</p>
                }
              </div>
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
              <div class="flex flex-col gap-2">
                <label hlmLabel for="email">{{ 'guests.email' | t }}@if (!session.isAdmin()) { * }</label>
                <input hlmInput id="email" type="email" autocomplete="email" [formField]="guestForm.email" />
                @if (fieldError('email', guestForm.email(), 'guests.email'); as message) {
                  <p id="email-error" class="text-sm text-destructive">{{ message }}</p>
                }
              </div>
              <div class="flex flex-col gap-2">
                <label hlmLabel for="handicap">{{ 'guests.handicap' | t }}@if (!session.isAdmin()) { * }</label>
                <input hlmInput id="handicap" inputmode="decimal" [formField]="guestForm.handicap" />
                @if (fieldError('handicap', guestForm.handicap(), 'guests.handicap'); as message) {
                  <p id="handicap-error" class="text-sm text-destructive">{{ message }}</p>
                }
              </div>
              <div class="flex flex-col gap-2">
                <label hlmLabel for="comment">{{ 'guests.comment' | t }}</label>
                <textarea hlmInput id="comment" rows="3" class="h-auto" [formField]="guestForm.comment"></textarea>
                @if (serverErrors()['comment']; as message) {
                  <p id="comment-error" class="text-sm text-destructive">{{ message }}</p>
                }
              </div>
              <button hlmBtn type="submit" class="w-full sm:w-auto sm:self-start" [disabled]="busy()">
                {{ 'guests.register' | t }}
              </button>
            </form>
          </section>
        }
      </div>
    } @else if (info.error()) {
      <div class="mx-auto max-w-2xl">
        <p class="text-lg">{{ errorKey() | t }}</p>
        <a hlmBtn class="mt-4" routerLink="/">{{ 'navigation.home' | t }}</a>
      </div>
    } @else {
      <div class="flex justify-center p-8"><hlm-spinner /></div>
    }
  `,
})
export class GuestRegistrationPage {
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
    loader: ({ params, abortSignal }) => eventsApi(this.api).guestInfo(params, { signal: abortSignal }),
  });

  protected readonly busy = signal(false);
  protected readonly serverErrors = signal<Record<string, string>>({});
  protected readonly model = signal({ male: '', firstName: '', lastName: '', email: '', handicap: '', comment: '' });
  protected readonly guestForm = form(this.model, (path) => {
    required(path.male);
    required(path.firstName);
    required(path.lastName);
    required(path.email, { when: () => !this.session.isAdmin() });
    required(path.handicap, { when: () => !this.session.isAdmin() });
  });

  protected readonly errorKey = computed(() => {
    const error = asApiError(this.info.error());
    return error?.status === 404 ? 'events.not_found' : (error?.error ?? 'errors.general');
  });

  protected formatDate(value: string): string {
    return formatDate(value, this.i18n.locale());
  }

  protected formatDateTime(value: string): string {
    return formatDateTime(value, this.i18n.locale());
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
        await eventsApi(this.api).addGuest(this.id(), {
          male: m.male === 'true',
          firstName: m.firstName,
          lastName: m.lastName,
          email: m.email || null,
          handicap: m.handicap || null,
          comment: m.comment || null,
        });
        this.notify.success('guests.register_success');
        await this.router.navigateByUrl(this.session.isAdmin() ? `/events/${this.id()}/admin` : '/');
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
