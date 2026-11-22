import { Component, computed, input, output } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideRefreshCw } from '@ng-icons/lucide';
import { HlmButtonImports } from '@ui/button';
import { asApiError } from '../../core/api/api-error';
import { TranslatePipe } from '../../core/i18n/translate.pipe';

/**
 * Shown when a page's data could not be loaded. Offers "Nochmal versuchen" for errors a retry can
 * fix (no connection, server error) — not for 404/401/403.
 */
@Component({
  selector: 'app-load-error',
  imports: [RouterLink, NgIcon, HlmButtonImports, TranslatePipe],
  providers: [provideIcons({ lucideRefreshCw })],
  template: `
    <div role="alert" class="flex flex-col items-start gap-3 rounded-md border border-destructive/30 bg-destructive/5 p-4">
      <p class="text-destructive">{{ messageKey() | t }}</p>
      @if (canRetry() || showHome()) {
        <div class="flex flex-wrap gap-2">
          @if (canRetry()) {
            <button hlmBtn size="sm" type="button" data-retry (click)="retry.emit()">
              <ng-icon name="lucideRefreshCw" size="16" aria-hidden="true" />{{ 'navigation.try_again' | t }}
            </button>
          }
          @if (showHome()) {
            <a hlmBtn variant="outline" size="sm" routerLink="/">{{ 'navigation.home' | t }}</a>
          }
        </div>
      }
    </div>
  `,
})
export class LoadError {
  /** The resource's error (`resource.error()`). */
  readonly error = input<unknown>();
  /** Translation key shown for 404. */
  readonly notFoundKey = input('errors.not_found');
  readonly showHome = input(false);
  readonly retry = output<void>();

  private readonly apiError = computed(() => asApiError(this.error()));

  protected readonly canRetry = computed(() => {
    const status = this.apiError()?.status;
    return status === undefined || ![401, 403, 404].includes(status);
  });

  protected readonly messageKey = computed(() => {
    const error = this.apiError();
    if (error?.status === 404) {
      return this.notFoundKey();
    }
    return error?.error ?? 'errors.general';
  });
}
