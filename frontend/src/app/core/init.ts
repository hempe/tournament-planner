import { Injector, inject } from '@angular/core';
import { Router } from '@angular/router';
import { ApiHooks } from './api/api-client';
import { SessionStore } from './auth/session.store';
import { I18n, negotiateLocale } from './i18n/i18n';
import { VersionService } from './version';

/** Runs before the first navigation: language first, then the session. */
export async function initializeApp(): Promise<void> {
  const i18n = inject(I18n);
  const session = inject(SessionStore);

  const locale = negotiateLocale(navigator.languages);
  document.documentElement.lang = locale;
  await i18n.load(locale);
  await session.refresh();
}

/** Wiring for ApiClient. SessionStore is resolved lazily: it depends on ApiClient itself. */
export function apiHooks(): ApiHooks {
  const injector = inject(Injector);
  const router = inject(Router);
  const version = inject(VersionService);

  return {
    onServerVersion: (serverVersion) => version.reportServerVersion(serverVersion),
    onUnauthorized: () => {
      injector.get(SessionStore).clear();
      if (!router.url.startsWith('/login')) {
        void router.navigate(['/login'], { queryParams: { returnUrl: router.url } });
      }
    },
  };
}
