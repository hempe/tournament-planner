import { Component, inject, input, signal } from '@angular/core';
import { Router } from '@angular/router';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { EventRequest, EventUpdateRequest, eventAdminApi } from '../../../core/api/generated';
import { Notify } from '../../../core/feedback/notify';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { EventForm } from './event-form';

@Component({
  selector: 'app-event-create-page',
  imports: [EventForm, TranslatePipe],
  template: `
    <div class="mx-auto flex max-w-2xl flex-col gap-4">
      <h1 class="text-2xl font-semibold">{{ 'events.new' | t }}</h1>
      <app-event-form mode="create" [initial]="{ date: date() ?? '' }" [error]="error()" [busy]="busy()" (save)="create($event)" />
    </div>
  `,
})
export class EventCreatePage {
  /** ?date=YYYY-MM-DD (component input binding). */
  readonly date = input<string>();

  private readonly api = inject(ApiClient);
  private readonly router = inject(Router);
  private readonly notify = inject(Notify);

  protected readonly error = signal<ApiError | null>(null);
  protected readonly busy = signal(false);

  protected async create(body: EventRequest | EventUpdateRequest): Promise<void> {
    this.error.set(null);
    this.busy.set(true);
    try {
      const created = await eventAdminApi(this.api).create(body as EventRequest);
      this.notify.success('events.update_success');
      await this.router.navigateByUrl(`/events/${created.event.id}/admin`);
    } catch (e) {
      if (e instanceof ApiError && e.status === 422) {
        this.error.set(e);
        return;
      }
      throw e;
    } finally {
      this.busy.set(false);
    }
  }
}
