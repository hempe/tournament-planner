import { Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SocialEventSummaryDto } from '../../core/api/generated';
import { TranslatePipe } from '../../core/i18n/translate.pipe';

@Component({
  selector: 'app-social-event-card',
  imports: [RouterLink, TranslatePipe],
  template: `
    <div data-card class="rounded-md border-l-4 border-[var(--social)] bg-[var(--social)]/10 text-sm">
      <a [routerLink]="['/social-events', event().id]" class="block p-2">
        <span class="block font-medium text-[var(--social)]">{{ event().name }}</span>
        @if (status(); as s) {
          <span class="block text-xs text-muted-foreground">{{ s.key | t: s.params }}</span>
        }
      </a>
    </div>
  `,
})
export class SocialEventCard {
  readonly event = input.required<SocialEventSummaryDto>();

  protected readonly status = computed<{ key: string; params?: { count: number } } | null>(() => {
    const e = this.event();
    if (e.userRegistered) {
      return { key: 'social_events.registered' };
    }
    if (e.available === null) {
      return null;
    }
    return e.available <= 0 ? { key: 'social_events.full' } : { key: 'social_events.available', params: { count: e.available } };
  });
}
