import { Directive } from '@angular/core';
import { classes } from '@ui/utils';

@Directive({
  selector: '[hlmCardTitle]',
  exportAs: 'hlmCardTitle',
  host: { 'data-slot': 'card-title' },
})
export class HlmCardTitle {
  constructor() {
    classes(() => 'text-base leading-tight font-semibold group-data-[size=sm]/card:text-sm');
  }
}
