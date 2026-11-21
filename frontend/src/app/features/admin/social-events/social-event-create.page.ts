import { Component, computed, inject, input, resource, signal } from '@angular/core';
import { Router } from '@angular/router';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { SocialEventRequest, eventAdminApi, socialEventAdminApi } from '../../../core/api/generated';
import { Notify } from '../../../core/feedback/notify';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { SocialEventForm } from './social-event-form';

@Component({
  selector: 'app-social-event-create-page',
  imports: [SocialEventForm, TranslatePipe],
  template: `
    <div class="mx-auto flex max-w-2xl flex-col gap-4">
      <h1 class="text-2xl font-semibold">{{ 'social_events.new' | t }}</h1>
      @if (tournament.hasValue()) {
        @let t = tournament.value();
        <p class="text-sm text-muted-foreground">{{ 'social_events.linked_tournament' | t }}: {{ t.event.name }}</p>
      }
      @if (!tournament.isLoading()) {
        <app-social-event-form [initial]="initial()" [tournamentId]="linkedId()" [error]="error()" [busy]="busy()" (save)="create($event)" />
      }
    </div>
  `,
})
export class SocialEventCreatePage {
  /** ?tournamentId= (component input binding). */
  readonly tournamentId = input<string>();

  private readonly api = inject(ApiClient);
  private readonly router = inject(Router);
  private readonly notify = inject(Notify);

  protected readonly tournament = resource({
    params: () => this.tournamentId(),
    loader: ({ params, abortSignal }) => eventAdminApi(this.api).show(params, { signal: abortSignal }),
  });

  protected readonly linkedId = computed(() => (this.tournament.hasValue() ? this.tournament.value().event.id : null));
  protected readonly initial = computed(() => (this.tournament.hasValue() ? { date: this.tournament.value().event.date } : {}));
  protected readonly error = signal<ApiError | null>(null);
  protected readonly busy = signal(false);

  protected async create(body: SocialEventRequest): Promise<void> {
    this.error.set(null);
    this.busy.set(true);
    try {
      const created = await socialEventAdminApi(this.api).create(body);
      this.notify.success('events.update_success');
      await this.router.navigateByUrl(`/social-events/${created.event.id}/admin`);
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
