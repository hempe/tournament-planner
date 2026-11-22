import { LoadError } from '../../../shared/ui/load-error';
import { Component, computed, inject, resource } from '@angular/core';
import { NgTemplateOutlet } from '@angular/common';
import { Router, RouterLink } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideChevronRight, lucidePlus, lucideTrash2 } from '@ng-icons/lucide';
import { HlmButtonImports } from '@ui/button';
import { HlmSpinner } from '@ui/spinner';
import { ApiClient } from '../../../core/api/api-client';
import { EventSummaryDto, eventAdminApi } from '../../../core/api/generated';
import { ConfirmService } from '../../../core/feedback/confirm.service';
import { Notify } from '../../../core/feedback/notify';
import { formatDate, toApiDate } from '../../../core/format/dates';
import { I18n } from '../../../core/i18n/i18n';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { Page, PageHeader } from '../../../shared/ui/page';

@Component({
  selector: 'app-event-list-page',
  imports: [LoadError, NgTemplateOutlet, RouterLink, NgIcon, HlmButtonImports, HlmSpinner, TranslatePipe, Page, PageHeader],
  providers: [provideIcons({ lucideChevronRight, lucidePlus, lucideTrash2 })],
  template: `
    <div appPage width="wide">
      <app-page-header [title]="'events.title' | t">
        <a hlmBtn size="sm" routerLink="/events/new"><ng-icon name="lucidePlus" />{{ 'events.add' | t }}</a>
        <a hlmBtn variant="outline" size="sm" routerLink="/events/bulk/new">{{ 'events.bulk_create' | t }}</a>
      </app-page-header>

      @if (events.hasValue()) {
        <ng-template #list let-rows>
          <!-- below sm every row is a compact stacked card -->
          <table class="block w-full text-sm sm:table">
            <thead class="hidden sm:table-header-group">
              <tr class="border-b text-left text-xs text-muted-foreground">
                <th class="px-2 py-1.5 font-medium">{{ 'events.date' | t }}</th>
                <th class="px-2 py-1.5 font-medium">{{ 'events.name' | t }}</th>
                <th class="px-2 py-1.5 text-right font-medium">{{ 'events.max_participants' | t }}</th>
                <th class="px-2 py-1.5 text-right font-medium">{{ 'events.registered' | t }}</th>
                <th class="px-2 py-1.5 text-right font-medium">{{ 'events.waitlist' | t }}</th>
                <th class="w-10 px-2 py-1.5"><span class="sr-only">{{ 'events.delete' | t }}</span></th>
              </tr>
            </thead>
            <tbody class="flex flex-col gap-2 sm:table-row-group">
              @for (e of rows; track e.id) {
                <tr
                  data-event-row
                  class="relative block cursor-pointer rounded-lg border bg-card py-2 pr-11 pl-3 hover:bg-muted/50 sm:table-row sm:rounded-none sm:border-0 sm:border-b sm:bg-transparent sm:p-0"
                  (click)="open(e)"
                >
                  <td class="block text-xs text-muted-foreground sm:table-cell sm:px-2 sm:py-1.5 sm:text-sm sm:whitespace-nowrap">{{ formatDate(e.date) }}</td>
                  <td class="block font-medium sm:table-cell sm:px-2 sm:py-1.5">
                    <a class="hover:underline" [routerLink]="['/events', e.id, 'admin']" (click)="$event.stopPropagation()">{{ e.name }}</a>
                  </td>
                  <td class="inline text-xs text-muted-foreground tabular-nums sm:table-cell sm:px-2 sm:py-1.5 sm:text-right sm:text-sm sm:text-foreground">
                    <span class="sm:hidden">{{ 'events.max_participants' | t }}: </span>{{ e.capacity }}
                  </td>
                  <td class="inline text-xs text-muted-foreground tabular-nums sm:table-cell sm:px-2 sm:py-1.5 sm:text-right sm:text-sm sm:text-foreground">
                    <span class="sm:hidden"> · {{ 'events.registered' | t }}: </span>{{ e.joined }}
                  </td>
                  <td class="inline text-xs text-muted-foreground tabular-nums sm:table-cell sm:px-2 sm:py-1.5 sm:text-right sm:text-sm sm:text-foreground">
                    <span class="sm:hidden"> · {{ 'events.waitlist' | t }}: </span>{{ e.waitlist }}
                  </td>
                  <td class="absolute top-1/2 right-1.5 -translate-y-1/2 sm:static sm:table-cell sm:translate-y-0 sm:px-2 sm:py-1 sm:text-right">
                    <button
                      hlmBtn
                      variant="ghost"
                      size="icon-sm"
                      type="button"
                      class="text-muted-foreground hover:text-destructive"
                      [attr.aria-label]="'events.delete' | t"
                      [attr.title]="'events.delete' | t"
                      (click)="remove($event, e)"
                    >
                      <ng-icon name="lucideTrash2" />
                    </button>
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </ng-template>

        @if (upcoming().length > 0) {
          <section data-upcoming class="flex flex-col gap-2">
            <h2 class="text-sm font-medium text-muted-foreground">{{ 'events.upcoming_events' | t }} ({{ upcoming().length }})</h2>
            <ng-container *ngTemplateOutlet="list; context: { $implicit: upcoming() }" />
          </section>
        } @else {
          <p class="text-muted-foreground">{{ 'events.no_events' | t }}</p>
        }

        @if (past().length > 0) {
          <details data-past class="group flex flex-col gap-2">
            <summary class="flex cursor-pointer list-none items-center gap-1 text-sm font-medium text-muted-foreground select-none hover:text-foreground [&::-webkit-details-marker]:hidden">
              <ng-icon name="lucideChevronRight" class="transition-transform group-open:rotate-90" aria-hidden="true" />
              <span class="pt-[0.3em]">{{ 'events.past_events' | t }} ({{ past().length }})</span>
            </summary>
            <div class="mt-2">
              <ng-container *ngTemplateOutlet="list; context: { $implicit: past() }" />
            </div>
          </details>
        }
      } @else if (events.error()) {
        <app-load-error [error]="events.error()" (retry)="events.reload()" />
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

  /** Upcoming events (today included) oldest first, past events newest first. The API already sorts by date. */
  private readonly today = toApiDate(new Date());
  private readonly sorted = computed(() =>
    this.events.hasValue() ? [...this.events.value()].sort((a, b) => a.date.localeCompare(b.date)) : [],
  );
  protected readonly upcoming = computed(() => this.sorted().filter((e) => e.date >= this.today));
  protected readonly past = computed(() => this.sorted().filter((e) => e.date < this.today).reverse());

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
