import { Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideChevronLeft } from '@ng-icons/lucide';
import { TranslatePipe } from '../../core/i18n/translate.pipe';

/**
 * "← Zurück" above a page heading. The chevron sits flush with the content's left edge
 * (no button padding), so it lines up with the title below.
 */
@Component({
  selector: 'app-back-link',
  imports: [RouterLink, NgIcon, TranslatePipe],
  providers: [provideIcons({ lucideChevronLeft })],
  host: { class: 'block' },
  template: `
    <a
      data-back-link
      [routerLink]="link()"
      [queryParams]="queryParams()"
      class="-ml-1 inline-flex h-8 items-center gap-0.5 rounded-md pr-2 text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
    >
      <ng-icon name="lucideChevronLeft" class="shrink-0 text-base" aria-hidden="true" />
      <span class="pt-[0.3em]">{{ 'nav.back' | t }}</span>
    </a>
  `,
})
export class BackLink {
  readonly link = input.required<string | readonly (string | number)[]>();
  readonly queryParams = input<Record<string, string> | null>(null);
}
