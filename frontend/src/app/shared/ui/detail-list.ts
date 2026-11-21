import { Directive } from '@angular/core';
import { classes } from '@ui/utils';

/**
 * Label/value list: `<dl appDetailList><div><dt>Label</dt><dd>Value</dd></div>…</dl>`.
 * From sm up the label is a 10rem column left of the value; on phones label and value stack.
 */
@Directive({
  selector: 'dl[appDetailList]',
  host: { 'data-slot': 'detail-list' },
})
export class DetailList {
  constructor() {
    classes(
      () =>
        'flex flex-col gap-3 sm:gap-2 [&>div]:flex [&>div]:flex-col [&>div]:gap-0.5 sm:[&>div]:grid sm:[&>div]:grid-cols-[10rem_minmax(0,1fr)] sm:[&>div]:gap-4 [&_dt]:text-muted-foreground [&_dd]:break-words',
    );
  }
}
