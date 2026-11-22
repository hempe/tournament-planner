import { ErrorHandler, Injectable, inject } from '@angular/core';
import { ApiClient, ApiError } from '../api/api-client';
import { clientErrorApi } from '../api/generated';
import { Notify } from '../feedback/notify';
import { APP_VERSION } from '../version';

/** At most this many reports per page load. */
const MAX_REPORTS = 5;

/** Last line of defence: unhandled errors become a translated toast; unexpected ones are also reported to the server. */
@Injectable()
export class AppErrorHandler implements ErrorHandler {
  private readonly notify = inject(Notify);
  private readonly api = inject(ApiClient);
  private readonly appVersion = inject(APP_VERSION);
  private readonly reported = new Set<string>();

  handleError(error: unknown): void {
    if ((error as Error)?.name === 'AbortError') {
      return;
    }
    if (error instanceof ApiError) {
      // 401 is handled by the API hooks (redirect to /login).
      if (error.status !== 401) {
        this.notify.error(error.error, error.params);
      }
      return;
    }
    this.notify.error('errors.general');
    console.error(error);
    this.report(error);
  }

  /** Fire and forget: reporting must never throw or recurse into this handler. */
  private report(error: unknown): void {
    try {
      const message = (error instanceof Error ? error.message : String(error)) || 'unknown error';
      if (this.reported.size >= MAX_REPORTS || this.reported.has(message)) {
        return;
      }
      this.reported.add(message);
      const stack = error instanceof Error && error.stack ? error.stack : null;
      clientErrorApi(this.api)
        .report({ message, stack, url: window.location.href, appVersion: this.appVersion })
        .catch(() => undefined);
    } catch {
      // swallowed on purpose
    }
  }
}
