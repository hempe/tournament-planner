import { Component, Directive, input } from '@angular/core';
import { classes } from '@ui/utils';

export type PageWidth = 'wide' | 'medium' | 'narrow';

const WIDTHS: Record<PageWidth, string> = {
  /** Calendar and admin lists. */
  wide: 'max-w-5xl',
  /** Detail pages, admin detail pages and forms. */
  medium: 'max-w-3xl',
  /** Login. */
  narrow: 'max-w-sm',
};

/** Page container: `<div appPage width="wide">` — centred column with the standard gap between blocks. */
@Directive({
  selector: '[appPage]',
  host: { 'data-slot': 'page' },
})
export class Page {
  readonly width = input<PageWidth>('medium');

  constructor() {
    classes(() => ['mx-auto flex w-full min-w-0 flex-col gap-4', WIDTHS[this.width()]]);
  }
}

/**
 * Page heading: optional date line above the title, optional badges below it (`[meta]` content),
 * actions (remaining content) right-aligned from sm up and below the title on phones.
 */
@Component({
  selector: 'app-page-header',
  host: { class: 'block' },
  template: `
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div class="flex min-w-0 flex-col gap-1">
        @if (subtitle()) {
          <p data-subtitle class="text-sm text-muted-foreground">{{ subtitle() }}</p>
        }
        <h1 class="text-2xl leading-tight font-semibold break-words">{{ title() }}</h1>
        <ng-content select="[meta]" />
      </div>
      <div class="flex flex-wrap gap-2 empty:hidden sm:shrink-0 sm:justify-end"><ng-content /></div>
    </header>
  `,
})
export class PageHeader {
  readonly title = input.required<string>();
  readonly subtitle = input<string | null>(null);
}
