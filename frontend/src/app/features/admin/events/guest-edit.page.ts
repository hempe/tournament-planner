import { Component, computed, inject, input, linkedSignal, resource, signal } from '@angular/core';
import { FormField, form, required, submit } from '@angular/forms/signals';
import { Router, RouterLink } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { HlmCardImports } from '@ui/card';
import { HlmInputImports } from '@ui/input';
import { HlmLabelImports } from '@ui/label';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { asApiError } from '../../../core/api/api-error';
import { eventAdminApi } from '../../../core/api/generated';
import { ConfirmService } from '../../../core/feedback/confirm.service';
import { Notify } from '../../../core/feedback/notify';
import { I18n } from '../../../core/i18n/i18n';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { FieldError } from '../../../shared/forms/field-error';
import { SELECT_CLASSES } from '../../../shared/forms/select';
import { BackLink } from '../../../shared/ui/back-link';
import { Page, PageHeader } from '../../../shared/ui/page';

@Component({
  selector: 'app-guest-edit-page',
  imports: [FormField, RouterLink, HlmButtonImports, HlmCardImports, HlmInputImports, HlmLabelImports, HlmSpinner, TranslatePipe, FieldError, BackLink, Page, PageHeader],
  template: `
    @if (guest.hasValue()) {
      <div appPage>
        <app-back-link [link]="['/events', id(), 'admin']" />
        <app-page-header [title]="guest.value().firstName + ' ' + guest.value().lastName" [subtitle]="'guests.edit' | t" />
        <section hlmCard>
          <form hlmCardContent class="flex flex-col gap-4" novalidate (submit)="$event.preventDefault(); save()">
            <div class="flex flex-col gap-2">
              <label hlmLabel for="male">{{ 'users.salutation' | t }} *</label>
              <select id="male" [class]="selectClasses" [formField]="guestForm.male">
                <option value=""></option>
                <option value="true">{{ 'users.mr' | t }}</option>
                <option value="false">{{ 'users.mrs' | t }}</option>
              </select>
              <app-field-error field="male" label="users.salutation" [error]="error()" [control]="guestForm.male()" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="flex flex-col gap-2">
                <label hlmLabel for="firstName">{{ 'guests.first_name' | t }} *</label>
                <input hlmInput id="firstName" autocomplete="off" [formField]="guestForm.firstName" />
                <app-field-error field="firstName" label="guests.first_name" [error]="error()" [control]="guestForm.firstName()" />
              </div>
              <div class="flex flex-col gap-2">
                <label hlmLabel for="lastName">{{ 'guests.last_name' | t }} *</label>
                <input hlmInput id="lastName" autocomplete="off" [formField]="guestForm.lastName" />
                <app-field-error field="lastName" label="guests.last_name" [error]="error()" [control]="guestForm.lastName()" />
              </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="flex flex-col gap-2">
                <label hlmLabel for="email">{{ 'guests.email' | t }}</label>
                <input hlmInput id="email" type="email" autocomplete="off" [formField]="guestForm.email" />
                <app-field-error field="email" label="guests.email" [error]="error()" />
              </div>
              <div class="flex flex-col gap-2">
                <label hlmLabel for="handicap">{{ 'guests.handicap' | t }}</label>
                <input hlmInput id="handicap" inputmode="decimal" [formField]="guestForm.handicap" />
                <app-field-error field="handicap" label="guests.handicap" [error]="error()" />
              </div>
            </div>
            <div class="flex flex-col gap-2">
              <label hlmLabel for="comment">{{ 'guests.comment' | t }}</label>
              <textarea hlmInput id="comment" rows="3" class="h-auto" [formField]="guestForm.comment"></textarea>
              <app-field-error field="comment" label="guests.comment" [error]="error()" />
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
              <button hlmBtn type="submit" class="w-full sm:w-auto" [disabled]="busy()">{{ 'events.save' | t }}</button>
              <button hlmBtn variant="destructive" type="button" class="w-full sm:w-auto" [disabled]="busy()" (click)="remove()">
                {{ 'events.delete' | t }}
              </button>
            </div>
          </form>
        </section>
      </div>
    } @else if (guest.error()) {
      <div appPage>
        <p class="text-lg">{{ errorKey() | t }}</p>
        <a hlmBtn class="self-start" routerLink="/">{{ 'navigation.home' | t }}</a>
      </div>
    } @else {
      <div class="flex justify-center p-8"><hlm-spinner /></div>
    }
  `,
})
export class GuestEditPage {
  /** From the route (withComponentInputBinding). */
  readonly id = input.required<string>();
  readonly guestId = input.required<string>();

  private readonly api = inject(ApiClient);
  private readonly router = inject(Router);
  private readonly confirm = inject(ConfirmService);
  private readonly notify = inject(Notify);
  private readonly i18n = inject(I18n);
  protected readonly selectClasses = SELECT_CLASSES;

  protected readonly guest = resource({
    params: () => ({ id: this.id(), guestId: this.guestId() }),
    loader: ({ params, abortSignal }) => eventAdminApi(this.api).showGuest(params.id, params.guestId, { signal: abortSignal }),
  });

  protected readonly busy = signal(false);
  protected readonly error = signal<ApiError | null>(null);
  protected readonly model = linkedSignal(() => {
    const g = this.guest.hasValue() ? this.guest.value() : null;
    return {
      male: g ? String(g.male) : '',
      firstName: g?.firstName ?? '',
      lastName: g?.lastName ?? '',
      email: g?.email ?? '',
      handicap: g?.handicap === null || g === null ? '' : String(g.handicap),
      comment: g?.comment ?? '',
    };
  });
  protected readonly guestForm = form(this.model, (path) => {
    required(path.male);
    required(path.firstName);
    required(path.lastName);
  });

  protected readonly errorKey = computed(() => {
    const error = asApiError(this.guest.error());
    return error?.status === 404 ? 'guests.not_found' : (error?.error ?? 'errors.general');
  });

  private back(): Promise<boolean> {
    return this.router.navigateByUrl(`/events/${this.id()}/admin`);
  }

  protected async save(): Promise<void> {
    this.error.set(null);
    await submit(this.guestForm, async () => {
      const m = this.model();
      this.busy.set(true);
      try {
        await eventAdminApi(this.api).updateGuest(this.id(), this.guestId(), {
          male: m.male === 'true',
          firstName: m.firstName,
          lastName: m.lastName,
          email: m.email || null,
          handicap: m.handicap || null,
          comment: m.comment || null,
        });
        this.notify.success('guests.update_success');
        await this.back();
      } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
          this.error.set(e);
          return undefined;
        }
        throw e;
      } finally {
        this.busy.set(false);
      }
      return undefined;
    });
  }

  protected async remove(): Promise<void> {
    if (!(await this.confirm.ask(this.i18n.t('guests.delete_confirm'), { danger: true }))) {
      return;
    }
    this.busy.set(true);
    try {
      await eventAdminApi(this.api).deleteGuest(this.id(), this.guestId());
      this.notify.success('guests.delete_success');
      await this.back();
    } finally {
      this.busy.set(false);
    }
  }
}
