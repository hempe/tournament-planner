import { Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideCheck, lucideLock, lucidePlus } from '@ng-icons/lucide';
import { SocialEventSummaryDto } from '../../core/api/generated';
import { parseApiDate } from '../../core/format/dates';
import { ChoicePipe } from '../../core/i18n/choice.pipe';
import { TranslatePipe } from '../../core/i18n/translate.pipe';
import { socialStatus } from '../social-events/social-status';

/** Colours as on the previous site: light blue = registered, dark blue = open, grey = not open / full. */
@Component({
  selector: 'app-social-event-card',
  imports: [RouterLink, NgIcon, TranslatePipe, ChoicePipe],
  providers: [provideIcons({ lucideCheck, lucidePlus, lucideLock })],
  template: `
    <div data-card [attr.data-state]="state()" class="relative rounded-md text-sm" [class]="stateClasses()">
      <a [routerLink]="['/social-events', event().id]" class="block p-2">
        <span class="block font-medium [overflow-wrap:anywhere] hyphens-auto">{{ event().name }}</span>
        @if (status(); as s) {
          <span class="block pr-8 text-xs opacity-80">
            @if (s.count === undefined) {
              {{ s.key | t }}
            } @else {
              {{ s.key | tc: s.count }}
            }
          </span>
        } @else {
          <span class="block h-4"></span>
        }
        <span class="absolute right-2 bottom-1.5 flex items-center gap-1">
          @switch (state()) {
            @case ('registered') {
              <ng-icon name="lucideCheck" size="18" [attr.aria-label]="'social_events.registered' | t" />
              @if (event().isLocked) {
                <ng-icon name="lucideLock" size="16" [attr.aria-label]="'events.locked' | t" />
              }
            }
            @case ('open') {
              <ng-icon name="lucidePlus" size="18" [attr.aria-label]="'social_events.register' | t" />
            }
            @default {
              @if (event().isLocked) {
                <ng-icon name="lucideLock" size="16" [attr.aria-label]="'events.locked' | t" />
              }
            }
          }
        </span>
      </a>
    </div>
  `,
})
export class SocialEventCard {
  readonly event = input.required<SocialEventSummaryDto>();

  protected readonly status = computed(() => socialStatus(this.event()));

  protected readonly state = computed<'registered' | 'open' | 'closed'>(() => {
    const e = this.event();
    if (e.userRegistered) {
      return 'registered';
    }
    const today = new Date();
    const past = parseApiDate(e.date) < new Date(today.getFullYear(), today.getMonth(), today.getDate());
    const full = e.available !== null && e.available <= 0;
    return e.isLocked || past || full ? 'closed' : 'open';
  });

  protected readonly stateClasses = computed(() => {
    switch (this.state()) {
      case 'registered':
        return 'bg-[var(--social-light)] text-[var(--social)]';
      case 'open':
        return 'bg-[var(--social)] text-[var(--social-foreground)]';
      default:
        return 'bg-muted text-muted-foreground';
    }
  });
}
