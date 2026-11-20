import { TestBed } from '@angular/core/testing';
import { ApiClient, ApiError } from '../api/api-client';
import { UserDto } from '../api/generated';
import { SessionStore } from './session.store';

const jane: UserDto = {
  id: 7, username: 'jane', firstName: 'Jane', lastName: 'Doe', displayName: 'Jane Doe', isAdmin: false,
};

describe('SessionStore', () => {
  let api: { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn> };
  let store: SessionStore;

  beforeEach(() => {
    api = { get: vi.fn(), post: vi.fn() };
    TestBed.configureTestingModule({ providers: [{ provide: ApiClient, useValue: api }] });
    store = TestBed.inject(SessionStore);
  });

  it('starts logged out', () => {
    expect(store.user()).toBeNull();
    expect(store.loggedIn()).toBe(false);
    expect(store.isAdmin()).toBe(false);
  });

  it('refresh() loads the current user from /auth/me', async () => {
    api.get.mockResolvedValue({ user: { ...jane, isAdmin: true } });

    await store.refresh();

    expect(api.get).toHaveBeenCalledWith('/auth/me', undefined);
    expect(store.loggedIn()).toBe(true);
    expect(store.isAdmin()).toBe(true);
  });

  it('refresh() treats API errors as logged out', async () => {
    api.get.mockRejectedValue(new ApiError(0, 'errors.network'));

    await store.refresh();

    expect(store.user()).toBeNull();
  });

  it('login() posts credentials and stores the user', async () => {
    api.post.mockResolvedValue({ user: jane });

    await store.login('jane', 'secret');

    expect(api.post).toHaveBeenCalledWith('/auth/login', { username: 'jane', password: 'secret' }, undefined);
    expect(store.user()).toEqual(jane);
  });

  it('logout() clears the user', async () => {
    api.post.mockResolvedValueOnce({ user: jane }).mockResolvedValueOnce(undefined);
    await store.login('jane', 'secret');

    await store.logout();

    expect(api.post).toHaveBeenLastCalledWith('/auth/logout', undefined, undefined);
    expect(store.user()).toBeNull();
  });
});
