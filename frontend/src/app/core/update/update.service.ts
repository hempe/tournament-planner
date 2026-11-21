import { DOCUMENT } from '@angular/common';
import { Injectable, InjectionToken, effect, inject } from '@angular/core';
import { SwUpdate } from '@angular/service-worker';
import { Notify } from '../feedback/notify';
import { VersionService } from '../version';

/** Reloads the page; a token so tests can stub it. */
export const RELOAD = new InjectionToken<() => void>('RELOAD', {
  factory: () => {
    const document = inject(DOCUMENT);
    return () => document.location.reload();
  },
});

/** Tells the user when a new release is available and reloads into it on request. */
@Injectable({ providedIn: 'root' })
export class UpdateService {
  private readonly swUpdate = inject(SwUpdate);
  private readonly notify = inject(Notify);
  private readonly version = inject(VersionService);
  private readonly document = inject(DOCUMENT);
  private readonly reload = inject(RELOAD);
  private shown = false;

  constructor() {
    effect(() => {
      if (this.version.outdated() && !this.swUpdate.isEnabled) {
        this.show(async () => this.reload());
      }
    });
  }

  /** Initial check plus a re-check whenever the tab becomes visible again. */
  start(): void {
    void this.check();
    this.document.addEventListener('visibilitychange', () => {
      if (this.document.visibilityState === 'visible') {
        void this.check();
      }
    });
  }

  async check(): Promise<void> {
    if (!this.swUpdate.isEnabled) {
      return;
    }
    try {
      if (await this.swUpdate.checkForUpdate()) {
        this.show(async () => {
          await this.swUpdate.activateUpdate();
          this.reload();
        });
      }
    } catch {
      // Offline or the worker is unavailable: try again on the next trigger.
    }
  }

  private show(onAction: () => Promise<void>): void {
    if (this.shown) {
      return;
    }
    this.shown = true;
    this.notify.persistent('update.available', 'update.reload', onAction);
  }
}
