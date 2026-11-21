import { Directive } from '@angular/core';
import { BrnFieldControlDescribedBy } from '@spartan-ng/brain/field';
import { BrnInput } from '@spartan-ng/brain/input';
import { classes } from '@ui/utils';

@Directive({
  selector: '[hlmInput]',
  exportAs: 'hlmInput',
  hostDirectives: [
    { directive: BrnInput, inputs: ['id', 'forceInvalid', 'aria-invalid'] },
    { directive: BrnFieldControlDescribedBy, inputs: ['aria-describedby'] },
  ],
  host: { 'data-slot': 'input' },
})
// Hind Guntur sits high in its line box (see hlm-button): the extra top padding centres the text.
export class HlmInput {
  constructor() {
    classes(
      () =>
        'dark:bg-input/30 border-input focus-visible:border-ring focus-visible:ring-ring/50 data-[matches-spartan-invalid=true]:ring-destructive/20 dark:data-[matches-spartan-invalid=true]:ring-destructive/40 data-[matches-spartan-invalid=true]:border-destructive dark:data-[matches-spartan-invalid=true]:border-destructive/50 h-9 rounded-md border bg-transparent px-2.5 pt-[calc(0.25rem+0.3em)] pb-1 text-base shadow-xs transition-[color,box-shadow] file:h-7 file:text-sm file:font-medium focus-visible:ring-3 data-[matches-spartan-invalid=true]:ring-3 md:text-sm file:text-foreground placeholder:text-muted-foreground w-full min-w-0 outline-none file:inline-flex file:border-0 file:bg-transparent disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
    );
  }
}
