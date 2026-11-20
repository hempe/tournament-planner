import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { SessionStore } from './session.store';

// The session is loaded before the first navigation (core/init.ts), so the guards can be synchronous.
// They only shape the UX; the server enforces access.

export const authGuard: CanActivateFn = (_route, state) => {
  if (inject(SessionStore).loggedIn()) {
    return true;
  }
  return inject(Router).createUrlTree(['/login'], { queryParams: { returnUrl: state.url } });
};

export const adminGuard: CanActivateFn = (_route, state) => {
  const session = inject(SessionStore);
  const router = inject(Router);
  if (!session.loggedIn()) {
    return router.createUrlTree(['/login'], { queryParams: { returnUrl: state.url } });
  }
  return session.isAdmin() ? true : router.createUrlTree(['/']);
};

export const guestGuard: CanActivateFn = () =>
  inject(SessionStore).loggedIn() ? inject(Router).createUrlTree(['/']) : true;
