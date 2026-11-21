import { Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SocialEventSummaryDto } from '../../core/api/generated';
import { ChoicePipe } from '../../core/i18n/choice.pipe';
import { TranslatePipe } from '../../core/i18n/translate.pipe';
import { socialStatus } from '../social-events/social-status';

@Component({
  selector: 'app-social-event-card',
  imports: [RouterLink, TranslatePipe, ChoicePipe],
  template: `
    <div data-card class="rounded-md border-l-4 border-[var(--social)] bg-[var(--social)]/10 text-sm">
      <a [routerLink]="['/social-events', event().id]" class="block p-2">
        <span class="block font-medium text-[var(--social)]">{{ event().name }}</span>
        @if (status(); as s) {
          <span class="block text-xs text-muted-foreground">
            @if (s.count === undefined) {
              {{ s.key | t }}
            } @else {
              {{ s.key | tc: s.count }}
            }
          </span>
        }
      </a>
    </div>
  `,
})
export class SocialEventCard {
  readonly event = input.required<SocialEventSummaryDto>();

  protected readonly status = computed(() => socialStatus(this.event()));
}
