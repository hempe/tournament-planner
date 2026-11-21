import { adminGuard, authGuard, guestGuard } from './core/auth/guards';
import { routes as allRoutes } from './app.routes';

// Legacy form-action redirects are tested in legacy-redirects.spec.ts.
const routes = allRoutes.filter((r) => r.redirectTo === undefined);

describe('routes', () => {
  it('lists the member, guest and admin screens in order with the right guards', () => {
    expect(routes.map((r) => [r.path, r.canActivate])).toEqual([
      ['', undefined],
      ['login', [guestGuard]],
      ['events', [adminGuard]],
      ['events/new', [adminGuard]],
      ['events/bulk/new', [adminGuard]],
      ['events/:id/admin', [adminGuard]],
      ['events/:id/guests/:guestId/edit', [adminGuard]],
      ['events/:id/guests/new', undefined],
      ['events/:id', [authGuard]],
      ['social-events/new', [adminGuard]],
      ['social-events/:id/admin', [adminGuard]],
      ['social-events/:id/guests/new', undefined],
      ['social-events/:id', [authGuard]],
      ['users', [adminGuard]],
      ['users/new', [adminGuard]],
      ['users/:id/edit', [adminGuard]],
      ['**', undefined],
    ]);
  });

  it('declares the literal admin routes before the :id routes they would otherwise match', () => {
    const paths = routes.map((r) => r.path);
    expect(paths.indexOf('events/new')).toBeLessThan(paths.indexOf('events/:id'));
    expect(paths.indexOf('events/bulk/new')).toBeLessThan(paths.indexOf('events/:id'));
    expect(paths.indexOf('social-events/new')).toBeLessThan(paths.indexOf('social-events/:id'));
  });

  it('lazy-loads every route', () => {
    expect(routes.every((r) => typeof r.loadComponent === 'function')).toBe(true);
  });
});
