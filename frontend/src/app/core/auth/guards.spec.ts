import { computed, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot, UrlTree, provideRouter } from '@angular/router';
import { UserDto } from '../api/generated';
import { adminGuard, authGuard, guestGuard } from './guards';
import { SessionStore } from './session.store';

function setup(user: Partial<UserDto> | null) {
  const current = signal(user as UserDto | null);
  TestBed.configureTestingModule({
    providers: [
      provideRouter([]),
      {
        provide: SessionStore,
        useValue: {
          user: current,
          loggedIn: computed(() => current() !== null),
          isAdmin: computed(() => current()?.isAdmin === true),
        },
      },
    ],
  });
  const router = TestBed.inject(Router);
  const run = (guard: typeof authGuard, url = '/events/3') =>
    TestBed.runInInjectionContext(() =>
      guard({} as ActivatedRouteSnapshot, { url } as RouterStateSnapshot),
    );
  const serialize = (result: unknown) => router.serializeUrl(result as UrlTree);
  return { run, serialize };
}

describe('guards', () => {
  it('authGuard lets logged-in users through', () => {
    const { run } = setup({ isAdmin: false });
    expect(run(authGuard)).toBe(true);
  });

  it('authGuard sends anonymous users to /login with returnUrl', () => {
    const { run, serialize } = setup(null);
    expect(serialize(run(authGuard))).toBe('/login?returnUrl=%2Fevents%2F3');
  });

  it('adminGuard allows admins, sends members home and anonymous to login', () => {
    expect(setup({ isAdmin: true }).run(adminGuard)).toBe(true);

    TestBed.resetTestingModule();
    const member = setup({ isAdmin: false });
    expect(member.serialize(member.run(adminGuard))).toBe('/');

    TestBed.resetTestingModule();
    const anonymous = setup(null);
    expect(anonymous.serialize(anonymous.run(adminGuard, '/users'))).toBe('/login?returnUrl=%2Fusers');
  });

  it('guestGuard sends logged-in users home', () => {
    const member = setup({ isAdmin: false });
    expect(member.serialize(member.run(guestGuard, '/login'))).toBe('/');

    TestBed.resetTestingModule();
    expect(setup(null).run(guestGuard, '/login')).toBe(true);
  });
});
