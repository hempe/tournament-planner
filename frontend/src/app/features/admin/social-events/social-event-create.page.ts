import { Component, computed, inject, input, resource, signal } from '@angular/core';
import { Router } from '@angular/router';
import { HlmCardImports } from '@ui/card';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { SocialEventRequest, eventAdminApi, socialEventAdminApi } from '../../../core/api/generated';
import { Notify } from '../../../core/feedback/notify';
import { I18n } from '../../../core/i18n/i18n';
import { TranslatePipe } from '../../../core/i18n/translate.pipe';
import { SocialEventForm } from './social-event-form';
import { BackLink } from '../../../shared/ui/back-link';
import { Page, PageHeader } from '../../../shared/ui/page';

@Component({
  selector: 'app-social-event-create-page',
  imports: [SocialEventForm, TranslatePipe, HlmCardImports, BackLink, Page, PageHeader],
  template: `
    <div appPage>
      <app-back-link [link]="linkedId() === null ? '/events' : ['/events', linkedId()!, 'admin']" />
      <app-page-header [title]="'social_events.new' | t" [subtitle]="linkedLine()" />
      @if (!tournament.isLoading()) {
        <section hlmCard>
          <div hlmCardContent>
            <app-social-event-form [initial]="initial()" [tournamentId]="linkedId()" [error]="error()" [busy]="busy()" (save)="create($event)" />
          </div>
        </section>
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
  private readonly i18n = inject(I18n);

  protected readonly tournament = resource({
    params: () => this.tournamentId(),
    loader: ({ params, abortSignal }) => eventAdminApi(this.api).show(params, { signal: abortSignal }),
  });

  /** "Verknüpftes Turnier: …" above the title. */
  protected readonly linkedLine = computed(() =>
    this.tournament.hasValue() ? `${this.i18n.t('social_events.linked_tournament')}: ${this.tournament.value().event.name}` : null,
  );
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
