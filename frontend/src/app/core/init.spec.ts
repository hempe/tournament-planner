import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { API_HOOKS } from './api/api-client';
import { SessionStore } from './auth/session.store';
import { apiHooks } from './init';
import { VersionService } from './version';

describe('apiHooks', () => {
  it('forwards the server version and handles 401', async () => {
    const session = { clear: vi.fn() };
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: SessionStore, useValue: session },
        { provide: API_HOOKS, useFactory: apiHooks },
      ],
    });
    const hooks = TestBed.inject(API_HOOKS);
    const router = TestBed.inject(Router);
    const navigate = vi.spyOn(router, 'navigate').mockResolvedValue(true);

    hooks.onServerVersion?.('2026-12-04-a1b2c3d');
    hooks.onUnauthorized?.();

    expect(TestBed.inject(VersionService).serverVersion()).toBe('2026-12-04-a1b2c3d');
    expect(session.clear).toHaveBeenCalled();
    expect(navigate).toHaveBeenCalledWith(['/login'], { queryParams: { returnUrl: '/' } });
  });
});
