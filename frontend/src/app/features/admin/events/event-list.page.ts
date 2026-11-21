import { Component, computed, inject, resource } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient } from '../../../core/api/api-client';
import { asApiError } from '../../../core/api/api-error';
import { EventSummaryDto, eventAdminApi } from '../../../core/api/generated';
import { ConfirmService } from '../../../core/feedback/confirm.service';
import { Notify } from '../../../core/feedback/notify';
import { formatDate } from '../../../core/format/dates';
import { I18n } from '../../../core/i18n/i18n';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';

@Component({
  selector: 'app-event-list-page',
  imports: [RouterLink, HlmButtonImports, HlmSpinner, TranslatePipe],
  template: `
    <div class="mx-auto flex max-w-4xl flex-col gap-4">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-semibold">{{ 'events.title' | t }}</h1>
        <div class="flex flex-col gap-2 sm:flex-row">
          <a hlmBtn routerLink="/events/new" class="w-full sm:w-auto">{{ 'events.add' | t }}</a>
          <a hlmBtn variant="outline" routerLink="/events/bulk/new" class="w-full sm:w-auto">{{ 'events.bulk_create' | t }}</a>
        </div>
      </div>

      @if (events.hasValue()) {
        <!-- below sm every row is a stacked card -->
        <table class="block w-full text-sm sm:table">
          <thead class="hidden sm:table-header-group">
            <tr class="border-b text-left text-muted-foreground">
              <th class="p-2 font-medium">{{ 'events.date' | t }}</th>
              <th class="p-2 font-medium">{{ 'events.name' | t }}</th>
              <th class="p-2 font-medium">{{ 'events.max_participants' | t }}</th>
              <th class="p-2 font-medium">{{ 'events.registered' | t }}</th>
              <th class="p-2 font-medium">{{ 'events.waitlist' | t }}</th>
              <th class="p-2"></th>
            </tr>
          </thead>
          <tbody class="flex flex-col gap-2 sm:table-row-group">
            @for (e of events.value(); track e.id) {
              <tr
                class="block cursor-pointer rounded-md border bg-card p-3 hover:bg-accent sm:table-row sm:rounded-none sm:border-0 sm:border-b sm:bg-transparent sm:p-0"
                (click)="open(e)"
              >
                <td class="block text-muted-foreground sm:table-cell sm:p-2">{{ formatDate(e.date) }}</td>
                <td class="block font-medium sm:table-cell sm:p-2">
                  <a [routerLink]="['/events', e.id, 'admin']" (click)="$event.stopPropagation()">{{ e.name }}</a>
                </td>
                <td class="block sm:table-cell sm:p-2">
                  <span class="text-muted-foreground sm:hidden">{{ 'events.max_participants' | t }}: </span>{{ e.capacity }}
                </td>
                <td class="block sm:table-cell sm:p-2">
                  <span class="text-muted-foreground sm:hidden">{{ 'events.registered' | t }}: </span>{{ e.joined }}
                </td>
                <td class="block sm:table-cell sm:p-2">
                  <span class="text-muted-foreground sm:hidden">{{ 'events.waitlist' | t }}: </span>{{ e.waitlist }}
                </td>
                <td class="mt-2 block sm:mt-0 sm:table-cell sm:p-2 sm:text-right">
                  <button hlmBtn variant="destructive" size="sm" type="button" class="w-full sm:w-auto" (click)="remove($event, e)">
                    {{ 'events.delete' | t }}
                  </button>
                </td>
              </tr>
            }
          </tbody>
        </table>
      } @else if (events.error()) {
        <p role="alert" class="text-destructive">{{ errorKey() | t }}</p>
      } @else {
        <div class="flex justify-center p-8"><hlm-spinner /></div>
      }
    </div>
  `,
})
export class EventListPage {
  private readonly api = inject(ApiClient);
  private readonly router = inject(Router);
  private readonly confirm = inject(ConfirmService);
  private readonly notify = inject(Notify);
  private readonly i18n = inject(I18n);

  protected readonly events = resource({
    loader: ({ abortSignal }) => eventAdminApi(this.api).index({ signal: abortSignal }),
  });
  protected readonly errorKey = computed(() => asApiError(this.events.error())?.error ?? 'errors.general');

  protected formatDate(value: string): string {
    return formatDate(value, this.i18n.locale());
  }

  protected open(event: EventSummaryDto): void {
    void this.router.navigate(['/events', event.id, 'admin']);
  }

  protected async remove(click: Event, event: EventSummaryDto): Promise<void> {
    click.stopPropagation();
    if (!(await this.confirm.ask(this.i18n.t('events.delete_confirm', { name: event.name }), { danger: true }))) {
      return;
    }
    await eventAdminApi(this.api).delete(event.id);
    this.notify.success('events.delete_success');
    this.events.reload();
  }
}
