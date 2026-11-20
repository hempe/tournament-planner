import { Injectable, computed, inject, signal } from '@angular/core';
import { ApiClient, ApiError } from '../api/api-client';
import { UserDto, authApi } from '../api/generated';

/** The logged-in user as signals. Loaded once at startup (see core/init.ts). */
@Injectable({ providedIn: 'root' })
export class SessionStore {
  private readonly auth = authApi(inject(ApiClient));
  private readonly _user = signal<UserDto | null>(null);

  readonly user = this._user.asReadonly();
  readonly loggedIn = computed(() => this._user() !== null);
  readonly isAdmin = computed(() => this._user()?.isAdmin === true);

  async refresh(): Promise<void> {
    try {
      this._user.set((await this.auth.me()).user);
    } catch (error) {
      if (!(error instanceof ApiError)) {
        throw error;
      }
      this._user.set(null);
    }
  }

  async login(username: string, password: string): Promise<void> {
    const session = await this.auth.login({ username, password });
    this._user.set(session.user);
  }

  async logout(): Promise<void> {
    await this.auth.logout();
    this._user.set(null);
  }

  /** Forget the user locally (e.g. after a 401). */
  clear(): void {
    this._user.set(null);
  }
}
