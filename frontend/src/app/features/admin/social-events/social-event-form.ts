import { Component, input, linkedSignal, output } from '@angular/core';
import { FormField, form, required, submit } from '@angular/forms/signals';
import { HlmButtonImports } from '@ui/button';
import { HlmInputImports } from '@ui/input';
import { HlmLabelImports } from '@ui/label';
import { ApiError } from '../../../core/api/api-client';
import { SocialEventRequest } from '../../../core/api/generated';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { FieldError } from '../../../shared/forms/field-error';

export interface SocialEventFormValue {
  name: string;
  date: string;
  description: string | null;
  /** 'YYYY-MM-DDTHH:MM[:SS]' */
  registrationClose: string | null;
  menus: string;
  tables: string;
}

interface FormModel {
  name: string;
  date: string;
  description: string;
  registrationClose: string;
  menus: string;
  tables: string;
}

/** Presentational form shared by the social event create and administration pages. Emits `save` with the request body. */
@Component({
  selector: 'app-social-event-form',
  imports: [FormField, HlmButtonImports, HlmInputImports, HlmLabelImports, TranslatePipe, FieldError],
  template: `
    <form class="flex flex-col gap-4" novalidate (submit)="$event.preventDefault(); send()">
      <div class="flex flex-col gap-2">
        <label hlmLabel for="name">{{ 'social_events.name' | t }} *</label>
        <input hlmInput id="name" [formField]="socialForm.name" />
        <app-field-error field="name" label="social_events.name" [error]="error()" [control]="socialForm.name()" />
      </div>
      <div class="flex flex-col gap-2">
        <label hlmLabel for="date">{{ 'social_events.date' | t }} *</label>
        <input hlmInput id="date" type="date" [formField]="socialForm.date" />
        <app-field-error field="date" label="social_events.date" [error]="error()" [control]="socialForm.date()" />
      </div>
      <div class="flex flex-col gap-2">
        <label hlmLabel for="description">{{ 'social_events.description' | t }}</label>
        <textarea hlmInput id="description" rows="3" class="h-auto" [formField]="socialForm.description"></textarea>
        <app-field-error field="description" label="social_events.description" [error]="error()" />
      </div>
      <div class="flex flex-col gap-2">
        <label hlmLabel for="registrationClose">{{ 'social_events.registration_close' | t }}</label>
        <input hlmInput id="registrationClose" type="datetime-local" [formField]="socialForm.registrationClose" />
        <app-field-error field="registrationClose" label="social_events.registration_close" [error]="error()" />
      </div>
      <div class="flex flex-col gap-2">
        <label hlmLabel for="menus">{{ 'social_events.menus' | t }}</label>
        <input hlmInput id="menus" [formField]="socialForm.menus" aria-describedby="menus-hint" />
        <p id="menus-hint" class="text-xs text-muted-foreground">{{ 'social_events.menus_hint' | t }}</p>
        <app-field-error field="menus" label="social_events.menus" [error]="error()" />
      </div>
      <div class="flex flex-col gap-2">
        <label hlmLabel for="tables">{{ 'social_events.tables' | t }}</label>
        <input hlmInput id="tables" [formField]="socialForm.tables" aria-describedby="tables-hint" />
        <p id="tables-hint" class="text-xs text-muted-foreground">{{ 'social_events.tables_hint' | t }}</p>
        <app-field-error field="tables" label="social_events.tables" [error]="error()" />
      </div>
      <button hlmBtn type="submit" class="w-full sm:w-auto sm:self-start" [disabled]="busy()">
        {{ 'social_events.save' | t }}
      </button>
    </form>
  `,
})
export class SocialEventForm {
  readonly initial = input<Partial<SocialEventFormValue>>({});
  /** Tournament the event is linked to; sent through unchanged. */
  readonly tournamentId = input<number | null>(null);
  /** 422 from the last save. */
  readonly error = input<ApiError | null>(null);
  readonly busy = input(false);
  readonly save = output<SocialEventRequest>();

  /** Re-seeded whenever the initial value changes. */
  protected readonly model = linkedSignal<FormModel>(() => {
    const i = this.initial();
    return {
      name: i.name ?? '',
      date: i.date ?? '',
      description: i.description ?? '',
      registrationClose: i.registrationClose?.slice(0, 16) ?? '',
      menus: i.menus ?? '',
      tables: i.tables ?? '',
    };
  });

  protected readonly socialForm = form(this.model, (path) => {
    required(path.name);
    required(path.date);
  });

  protected async send(): Promise<void> {
    await submit(this.socialForm, async () => {
      const m = this.model();
      this.save.emit({
        name: m.name,
        date: m.date,
        tournamentId: this.tournamentId(),
        description: m.description || null,
        registrationClose: m.registrationClose || null,
        menus: m.menus,
        tables: m.tables,
      });
      return undefined;
    });
  }
}
