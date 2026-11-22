import { adminGuard, authGuard, guestGuard } from './core/auth/guards';
import { routes as allRoutes } from './app.routes';
import { featureGuard } from './core/flavor/flavor';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { FLAVOR } from './core/flavor/flavor';
import { amigosFlavor } from './core/flavor/testing';

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
      ['events/:id/guests/:guestId/edit', [featureGuard('guests'), adminGuard]],
      ['events/:id/guests/new', [featureGuard('guests')]],
      ['events/:id', [authGuard]],
      ['social-events/new', [featureGuard('socialEvents'), adminGuard]],
      ['social-events/:id/admin', [featureGuard('socialEvents'), adminGuard]],
      ['social-events/:id/guests/new', [featureGuard('socialEvents'), featureGuard('guests')]],
      ['social-events/:id', [featureGuard('socialEvents'), authGuard]],
      ['users', [adminGuard]],
      ['users/new', [adminGuard]],
      ['users/:id/edit', [adminGuard]],
      ['not-found', undefined],
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

  it('switched-off features lead to the not-found page', async () => {
    TestBed.configureTestingModule({ providers: [provideRouter(allRoutes), { provide: FLAVOR, useValue: amigosFlavor }] });
    const router = TestBed.inject(Router);

    await router.navigateByUrl('/events/1/guests/new');
    expect(router.url).toBe('/not-found');
    await router.navigateByUrl('/social-events/1/guests/new');
    expect(router.url).toBe('/not-found');
  });
});
