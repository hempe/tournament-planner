import { Pipe, PipeTransform, inject } from '@angular/core';
import { I18n, Params } from './i18n';

/** `{{ 'events.unregister' | t }}` — pure: the locale is fixed once the app has started. */
@Pipe({ name: 't' })
export class TranslatePipe implements PipeTransform {
  private readonly i18n = inject(I18n);

  transform(key: string, params?: Params): string {
    return this.i18n.t(key, params);
  }
}
