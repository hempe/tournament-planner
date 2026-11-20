import { authGuard, guestGuard } from './core/auth/guards';
import { routes } from './app.routes';

describe('routes', () => {
  it('lists the member and guest screens in order with the right guards', () => {
    expect(routes.map((r) => [r.path, r.canActivate])).toEqual([
      ['', undefined],
      ['login', [guestGuard]],
      ['events/:id/guests/new', undefined],
      ['events/:id', [authGuard]],
      ['social-events/:id/guests/new', undefined],
      ['social-events/:id', [authGuard]],
      ['**', undefined],
    ]);
  });

  it('lazy-loads every route', () => {
    expect(routes.every((r) => typeof r.loadComponent === 'function')).toBe(true);
  });
});
