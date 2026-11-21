import { Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideChevronRight, lucideUtensils } from '@ng-icons/lucide';
import { SocialEventSummaryDto } from '../../core/api/generated';
import { ChoicePipe } from '../../core/i18n/choice.pipe';
import { TranslatePipe } from '../../core/i18n/translate.pipe';
import { socialStatus } from './social-status';

/** Compact link to the social event of a tournament: icon, name, status (or a custom note) and a chevron. */
@Component({
  selector: 'app-social-event-row',
  imports: [RouterLink, NgIcon, TranslatePipe, ChoicePipe],
  providers: [provideIcons({ lucideUtensils, lucideChevronRight })],
  host: { class: 'block' },
  template: `
    <a
      data-social-row
      [routerLink]="link()"
      class="flex items-center gap-3 rounded-xl bg-card p-3 text-sm shadow-xs ring-1 ring-foreground/10 transition-colors hover:bg-[var(--social)]/5 sm:px-4"
    >
      <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[var(--social)]/10 text-[var(--social)]">
        <ng-icon name="lucideUtensils" class="text-base" aria-hidden="true" />
      </span>
      <span class="flex min-w-0 flex-1 flex-col">
        <span class="truncate font-medium text-[var(--social)]">{{ event().name }}</span>
        @if (note(); as text) {
          <span class="text-xs text-muted-foreground">{{ text | t }}</span>
        } @else if (status(); as s) {
          <span data-status class="text-xs" [class.text-primary]="s.tone === 'registered'" [class.text-muted-foreground]="s.tone !== 'registered'">
            @if (s.count === undefined) {
              {{ s.key | t }}
            } @else {
              {{ s.key | tc: s.count }}
            }
          </span>
        }
      </span>
      <ng-icon name="lucideChevronRight" class="shrink-0 text-base text-muted-foreground" aria-hidden="true" />
    </a>
  `,
})
export class SocialEventRow {
  readonly event = input.required<SocialEventSummaryDto>();
  readonly link = input.required<readonly (string | number)[]>();
  /** Translation key shown instead of the status (e.g. "Bearbeiten" on the admin page). */
  readonly note = input<string | null>(null);

  protected readonly status = computed(() => socialStatus(this.event()));
}
