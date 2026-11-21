import { Directive, input } from '@angular/core';
import { classes } from '@ui/utils';

export type BadgeVariant = 'primary' | 'secondary' | 'outline' | 'social';

const VARIANTS: Record<BadgeVariant, string> = {
  primary: 'border-transparent bg-primary text-primary-foreground',
  secondary: 'border-transparent bg-secondary text-secondary-foreground',
  outline: 'border-border text-muted-foreground',
  social: 'border-transparent bg-[var(--social)]/10 text-[var(--social)]',
};

/**
 * Small status pill: `<span appBadge variant="secondary">…</span>`.
 * Hind Guntur places its glyphs high in the line box, so the text gets the same
 * top nudge as buttons (pt-[0.3em]) to look vertically centred.
 */
@Directive({
  selector: '[appBadge]',
  host: { 'data-slot': 'badge' },
})
export class Badge {
  readonly variant = input<BadgeVariant>('primary');

  constructor() {
    classes(() => [
      'inline-flex h-5 shrink-0 items-center gap-1 rounded-full border px-2 pt-[0.3em] text-xs leading-none font-medium whitespace-nowrap align-middle',
      VARIANTS[this.variant()],
    ]);
  }
}
