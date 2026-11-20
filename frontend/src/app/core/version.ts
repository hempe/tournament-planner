import { Injectable, InjectionToken, computed, inject, signal } from '@angular/core';

/** Release of this frontend build ("dev" when served by ng serve). Plan 5 injects the real value. */
export const APP_VERSION = new InjectionToken<string>('APP_VERSION', { factory: () => 'dev' });

@Injectable({ providedIn: 'root' })
export class VersionService {
  readonly clientVersion = inject(APP_VERSION);
  private readonly _serverVersion = signal<string | null>(null);
  readonly serverVersion = this._serverVersion.asReadonly();

  /** True when the server runs a different release than this app (drives the update prompt in plan 5). */
  readonly outdated = computed(() => {
    const server = this._serverVersion();
    return server !== null && server !== 'dev' && this.clientVersion !== 'dev' && server !== this.clientVersion;
  });

  reportServerVersion(version: string): void {
    this._serverVersion.set(version);
  }
}
