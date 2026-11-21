import { ErrorHandler, Injectable, inject } from '@angular/core';
import { ApiError } from '../api/api-client';
import { Notify } from '../feedback/notify';

/** Last line of defence: unhandled errors become a translated toast. */
@Injectable()
export class AppErrorHandler implements ErrorHandler {
  private readonly notify = inject(Notify);

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
  }
}
