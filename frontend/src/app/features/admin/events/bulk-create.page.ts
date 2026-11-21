import { Component, computed, inject, linkedSignal, signal } from '@angular/core';
import { FormField, form, min, required, submit } from '@angular/forms/signals';
import { Router } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { HlmInputImports } from '@ui/input';
import { HlmLabelImports } from '@ui/label';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { BulkEventDto, BulkRequest, eventAdminApi } from '../../../core/api/generated';
import { Notify } from '../../../core/feedback/notify';
import { formatDate, formatDateTime } from '../../../core/format/dates';
import { I18n } from '../../../core/i18n/i18n';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { FieldError } from '../../../shared/forms/field-error';
import { SELECT_CLASSES } from '../../../shared/forms/select';

/** Monday to Sunday, as `dayOfWeek` values (0 = Sunday). */
const WEEKDAYS = [1, 2, 3, 4, 5, 6, 0];

interface FormModel {
  startDate: string;
  endDate: string;
  dayOfWeek: string;
  name: string;
  capacity: number | null;
  mixed: boolean;
  description: string;
  priceMembers: string;
  priceGuests: string;
  registrationCloseDays: number | null;
  registrationCloseTime: string;
}

@Component({
  selector: 'app-bulk-create-page',
  imports: [FormField, HlmButtonImports, HlmInputImports, HlmLabelImports, TranslatePipe, FieldError],
  template: `
    <div class="mx-auto flex max-w-2xl flex-col gap-4">
      @if (preview(); as events) {
        <h1 class="text-2xl font-semibold">{{ 'events.bulk_preview_title' | t: { count: events.length } }}</h1>
        @if (events.length === 0) {
          <p class="text-muted-foreground">{{ 'events.bulk_no_events_message' | t }}</p>
        } @else {
          <ul class="flex flex-col gap-2">
            @for (e of events; track e.date) {
              <li data-preview-row class="flex flex-col gap-1 rounded-md border bg-card p-3 sm:flex-row sm:items-center sm:gap-4">
                <span class="text-muted-foreground sm:w-56">{{ formatDate(e.date) }}</span>
                <span class="font-medium sm:flex-1">{{ e.name }}</span>
                <span class="text-sm sm:w-16">{{ e.capacity }}</span>
                <span class="text-sm text-muted-foreground sm:w-64">{{ e.registrationClose ? formatDateTime(e.registrationClose) : '–' }}</span>
              </li>
            }
          </ul>
        }
        <div class="flex flex-col gap-2 sm:flex-row">
          <button hlmBtn variant="outline" type="button" class="w-full sm:w-auto" [disabled]="busy()" (click)="back()">{{ 'nav.back' | t }}</button>
          @if (events.length > 0) {
            <button hlmBtn type="button" class="w-full sm:w-auto" [disabled]="busy()" (click)="createAll(events)">
              {{ 'events.bulk_create_all' | t }}
            </button>
          }
        </div>
      } @else {
        <h1 class="text-2xl font-semibold">{{ 'events.bulk_create' | t }}</h1>
        <form class="flex flex-col gap-4" novalidate (submit)="$event.preventDefault(); send()">
          <div class="flex flex-col gap-2">
            <label hlmLabel for="startDate">{{ 'events.bulk_start_date' | t }} *</label>
            <input hlmInput id="startDate" type="date" [formField]="bulkForm.startDate" />
            <app-field-error field="startDate" label="events.bulk_start_date" [error]="error()" [control]="bulkForm.startDate()" />
          </div>
          <div class="flex flex-col gap-2">
            <label hlmLabel for="endDate">{{ 'events.bulk_end_date' | t }} *</label>
            <input hlmInput id="endDate" type="date" [formField]="bulkForm.endDate" />
            <app-field-error field="endDate" label="events.bulk_end_date" [error]="error()" [control]="bulkForm.endDate()" />
          </div>
          <div class="flex flex-col gap-2">
            <label hlmLabel for="dayOfWeek">{{ 'events.bulk_day_of_week' | t }} *</label>
            <select id="dayOfWeek" [class]="selectClasses" [formField]="bulkForm.dayOfWeek">
              <option value="">{{ 'events.bulk_select_day' | t }}</option>
              @for (day of weekdays(); track day.value) {
                <option [value]="day.value">{{ day.label }}</option>
              }
            </select>
            <app-field-error field="dayOfWeek" label="events.bulk_day_of_week" [error]="error()" [control]="bulkForm.dayOfWeek()" />
          </div>
          <div class="flex flex-col gap-2">
            <label hlmLabel for="name">{{ 'events.name' | t }} *</label>
            <input hlmInput id="name" [formField]="bulkForm.name" />
            <app-field-error field="name" label="events.name" [error]="error()" [control]="bulkForm.name()" />
          </div>
          <div class="flex flex-col gap-2">
            <label hlmLabel for="capacity">{{ 'events.max_participants' | t }} *</label>
            <input hlmInput id="capacity" type="number" inputmode="numeric" [formField]="bulkForm.capacity" />
            <app-field-error field="capacity" label="events.max_participants" [error]="error()" [control]="bulkForm.capacity()" />
          </div>
          <label class="flex items-center gap-2 text-sm">
            <input id="mixed" type="checkbox" class="size-4" [formField]="bulkForm.mixed" />
            {{ 'events.play_together' | t }}
          </label>
          <div class="flex flex-col gap-2">
            <label hlmLabel for="description">{{ 'events.description' | t }}</label>
            <textarea hlmInput id="description" rows="3" class="h-auto" [formField]="bulkForm.description"></textarea>
            <app-field-error field="description" label="events.description" [error]="error()" />
          </div>
          <div class="flex flex-col gap-2">
            <label hlmLabel for="priceMembers">{{ 'events.price_members' | t }}</label>
            <input hlmInput id="priceMembers" [formField]="bulkForm.priceMembers" />
            <app-field-error field="priceMembers" label="events.price_members" [error]="error()" />
          </div>
          <div class="flex flex-col gap-2">
            <label hlmLabel for="priceGuests">{{ 'events.price_guests' | t }}</label>
            <input hlmInput id="priceGuests" [formField]="bulkForm.priceGuests" />
            <app-field-error field="priceGuests" label="events.price_guests" [error]="error()" />
          </div>
          <div class="flex flex-col gap-2">
            <label hlmLabel for="registrationCloseDays">{{ 'events.bulk_registration_close_days' | t }}</label>
            <input hlmInput id="registrationCloseDays" type="number" inputmode="numeric" [formField]="bulkForm.registrationCloseDays" />
            <app-field-error field="registrationCloseDays" label="events.bulk_registration_close_days" [error]="error()" />
          </div>
          <div class="flex flex-col gap-2">
            <label hlmLabel for="registrationCloseTime">{{ 'events.bulk_registration_close_time' | t }}</label>
            <input hlmInput id="registrationCloseTime" type="time" [formField]="bulkForm.registrationCloseTime" />
            <app-field-error field="registrationCloseTime" label="events.bulk_registration_close_time" [error]="error()" />
          </div>
          <button hlmBtn type="submit" class="w-full sm:w-auto sm:self-start" [disabled]="busy()">{{ 'events.bulk_preview' | t }}</button>
        </form>
      }
    </div>
  `,
})
export class BulkCreatePage {
  private readonly api = inject(ApiClient);
  private readonly router = inject(Router);
  private readonly notify = inject(Notify);
  private readonly i18n = inject(I18n);

  protected readonly selectClasses = SELECT_CLASSES;
  protected readonly error = signal<ApiError | null>(null);
  protected readonly busy = signal(false);
  /** The previewed events; null while the form is shown. The form model survives the preview step. */
  protected readonly preview = signal<BulkEventDto[] | null>(null);

  protected readonly weekdays = computed(() => {
    const formatter = new Intl.DateTimeFormat(this.i18n.locale(), { weekday: 'long' });
    // 2024-01-01 is a Monday.
    return WEEKDAYS.map((value) => ({ value, label: formatter.format(new Date(2024, 0, value === 0 ? 7 : value)) }));
  });

  private readonly model = linkedSignal<FormModel>(() => ({
    startDate: '',
    endDate: '',
    dayOfWeek: '',
    name: '',
    capacity: null,
    mixed: false,
    description: '',
    priceMembers: '',
    priceGuests: '',
    registrationCloseDays: null,
    registrationCloseTime: '',
  }));

  protected readonly bulkForm = form(this.model, (path) => {
    required(path.startDate);
    required(path.endDate);
    required(path.dayOfWeek);
    required(path.name);
    required(path.capacity);
    min(path.capacity, 1);
  });

  protected formatDate(value: string): string {
    return formatDate(value, this.i18n.locale());
  }

  protected formatDateTime(value: string): string {
    return formatDateTime(value, this.i18n.locale());
  }

  protected back(): void {
    this.preview.set(null);
  }

  protected async send(): Promise<void> {
    await submit(this.bulkForm, async () => {
      const m = this.model();
      const body: BulkRequest = {
        startDate: m.startDate,
        endDate: m.endDate,
        dayOfWeek: Number(m.dayOfWeek),
        name: m.name,
        capacity: Number(m.capacity),
        mixed: m.mixed,
        description: m.description || null,
        priceMembers: m.priceMembers || null,
        priceGuests: m.priceGuests || null,
        registrationCloseDays: m.registrationCloseDays === null ? null : Number(m.registrationCloseDays),
        registrationCloseTime: m.registrationCloseTime || null,
      };
      this.error.set(null);
      this.busy.set(true);
      try {
        this.preview.set((await eventAdminApi(this.api).bulkPreview(body)).events);
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

  protected async createAll(events: BulkEventDto[]): Promise<void> {
    this.busy.set(true);
    try {
      const result = await eventAdminApi(this.api).bulkCreate({ events });
      this.notify.success('events.bulk_create_success', { count: result.created });
      await this.router.navigateByUrl('/events');
    } finally {
      this.busy.set(false);
    }
  }
}
