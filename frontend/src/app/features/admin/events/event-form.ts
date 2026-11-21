import { Component, input, linkedSignal, output } from '@angular/core';
import { FormField, form, min, required, submit } from '@angular/forms/signals';
import { HlmButtonImports } from '@ui/button';
import { HlmInputImports } from '@ui/input';
import { HlmLabelImports } from '@ui/label';
import { ApiError } from '../../../core/api/api-client';
import { EventRequest, EventUpdateRequest } from '../../../core/api/generated';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { FieldError } from '../../../shared/forms/field-error';

export interface EventFormValue {
  name: string;
  date: string;
  capacity: number | null;
  mixed: boolean;
  description: string | null;
  priceMembers: string | null;
  priceGuests: string | null;
  /** 'YYYY-MM-DDTHH:MM[:SS]' */
  registrationClose: string | null;
}

interface FormModel {
  name: string;
  date: string;
  capacity: number | null;
  mixed: boolean;
  description: string;
  priceMembers: string;
  priceGuests: string;
  registrationClose: string;
}

/** Presentational form shared by the create and administration pages. Emits `save` with the request body. */
@Component({
  selector: 'app-event-form',
  imports: [FormField, HlmButtonImports, HlmInputImports, HlmLabelImports, TranslatePipe, FieldError],
  template: `
    <form class="flex flex-col gap-4" novalidate (submit)="$event.preventDefault(); send()">
      <div class="flex flex-col gap-2">
        <label hlmLabel for="name">{{ 'events.name' | t }} *</label>
        <input hlmInput id="name" [formField]="eventForm.name" />
        <app-field-error field="name" label="events.name" [error]="error()" [control]="eventForm.name()" />
      </div>
      @if (mode() === 'create') {
        <div class="flex flex-col gap-2">
          <label hlmLabel for="date">{{ 'events.date' | t }} *</label>
          <input hlmInput id="date" type="date" [formField]="eventForm.date" />
          <app-field-error field="date" label="events.date" [error]="error()" [control]="eventForm.date()" />
        </div>
      }
      <div class="flex flex-col gap-2">
        <label hlmLabel for="capacity">{{ 'events.max_participants' | t }} *</label>
        <input hlmInput id="capacity" type="number" inputmode="numeric" [formField]="eventForm.capacity" />
        <app-field-error field="capacity" label="events.max_participants" [error]="error()" [control]="eventForm.capacity()" />
      </div>
      <label class="flex items-center gap-2 text-sm">
        <input id="mixed" type="checkbox" class="size-4 accent-primary" [formField]="eventForm.mixed" />
        {{ 'events.play_together' | t }}
      </label>
      <div class="flex flex-col gap-2">
        <label hlmLabel for="description">{{ 'events.description' | t }}</label>
        <textarea hlmInput id="description" rows="3" class="h-auto" [formField]="eventForm.description"></textarea>
        <app-field-error field="description" label="events.description" [error]="error()" />
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
          <label hlmLabel for="priceMembers">{{ 'events.price_members' | t }}</label>
          <input hlmInput id="priceMembers" [formField]="eventForm.priceMembers" />
          <app-field-error field="priceMembers" label="events.price_members" [error]="error()" />
        </div>
        <div class="flex flex-col gap-2">
          <label hlmLabel for="priceGuests">{{ 'events.price_guests' | t }}</label>
          <input hlmInput id="priceGuests" [formField]="eventForm.priceGuests" />
          <app-field-error field="priceGuests" label="events.price_guests" [error]="error()" />
        </div>
      </div>
      <div class="flex flex-col gap-2">
        <label hlmLabel for="registrationClose">{{ 'events.registration_close' | t }}</label>
        <input hlmInput id="registrationClose" type="datetime-local" [formField]="eventForm.registrationClose" />
        <app-field-error field="registrationClose" label="events.registration_close" [error]="error()" />
      </div>
      <button hlmBtn type="submit" class="w-full sm:w-auto sm:self-start" [disabled]="busy()">
        {{ 'events.save' | t }}
      </button>
    </form>
  `,
})
export class EventForm {
  readonly initial = input<Partial<EventFormValue>>({});
  readonly mode = input<'create' | 'edit'>('create');
  /** 422 from the last save. */
  readonly error = input<ApiError | null>(null);
  readonly busy = input(false);
  readonly save = output<EventRequest | EventUpdateRequest>();

  /** Re-seeded whenever the initial value changes (e.g. after the server returned a fresh DTO). */
  protected readonly model = linkedSignal<FormModel>(() => {
    const i = this.initial();
    return {
      name: i.name ?? '',
      date: i.date ?? '',
      capacity: i.capacity ?? null,
      mixed: i.mixed ?? false,
      description: i.description ?? '',
      priceMembers: i.priceMembers ?? '',
      priceGuests: i.priceGuests ?? '',
      registrationClose: i.registrationClose?.slice(0, 16) ?? '',
    };
  });

  protected readonly eventForm = form(this.model, (path) => {
    required(path.name);
    required(path.date, { when: () => this.mode() === 'create' });
    required(path.capacity);
    min(path.capacity, 1);
  });

  protected async send(): Promise<void> {
    await submit(this.eventForm, async () => {
      const m = this.model();
      const body: EventUpdateRequest = {
        name: m.name,
        capacity: Number(m.capacity),
        mixed: m.mixed,
        description: m.description || null,
        priceMembers: m.priceMembers || null,
        priceGuests: m.priceGuests || null,
        registrationClose: m.registrationClose || null,
      };
      this.save.emit(this.mode() === 'create' ? { ...body, date: m.date } : body);
      return undefined;
    });
  }
}
