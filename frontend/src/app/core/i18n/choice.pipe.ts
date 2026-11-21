import { Pipe, PipeTransform, inject } from '@angular/core';
import { I18n, Params } from './i18n';

/** `{{ 'social_events.available' | tc: count }}` — plural forms via `I18n.choice` ("one|many"). */
@Pipe({ name: 'tc' })
export class ChoicePipe implements PipeTransform {
  private readonly i18n = inject(I18n);

  transform(key: string, count: number, params?: Params): string {
    return this.i18n.choice(key, count, params);
  }
}
