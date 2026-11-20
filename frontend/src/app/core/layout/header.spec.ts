import { computed, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { UserDto } from '../api/generated';
import { SessionStore } from '../auth/session.store';
import { Notify } from '../feedback/notify';
import { I18n } from '../i18n/i18n';
import { Header } from './header';

describe('Header', () => {
  const user = signal<UserDto | null>(null);
  const session = {
    user,
    loggedIn: computed(() => user() !== null),
    isAdmin: computed(() => user()?.isAdmin === true),
    logout: vi.fn().mockResolvedValue(undefined),
  };

  beforeEach(() => {
    user.set(null);
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: SessionStore, useValue: session },
        { provide: Notify, useValue: { success: vi.fn(), error: vi.fn() } },
      ],
    });
    TestBed.inject(I18n).use('de', {
      app: { name: 'GOLF EL FARO' },
      nav: { events: 'Anlässe', users: 'Benutzer', logout: 'Abmelden' },
      auth: { login: 'Mitglieder Anmeldung', logout_success: 'Erfolgreich abgemeldet' },
    });
  });

  it('shows only the login link when logged out', async () => {
    const fixture = TestBed.createComponent(Header);
    await fixture.whenStable();
    const text = fixture.nativeElement.textContent as string;

    expect(text).toContain('Mitglieder Anmeldung');
    expect(text).not.toContain('Abmelden');
    expect(text).not.toContain('Anlässe');
  });

  it('shows name, events and logout for members; users only for admins', async () => {
    user.set({ id: 1, username: 'jane', firstName: 'Jane', lastName: 'Doe', displayName: 'Jane Doe', isAdmin: false });
    const fixture = TestBed.createComponent(Header);
    await fixture.whenStable();
    let text = fixture.nativeElement.textContent as string;

    expect(text).toContain('Jane Doe');
    expect(text).toContain('Anlässe');
    expect(text).toContain('Abmelden');
    expect(text).not.toContain('Benutzer');

    user.set({ ...user()!, isAdmin: true });
    await fixture.whenStable();
    text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Benutzer');
  });

  it('logs out via the session store', async () => {
    user.set({ id: 1, username: 'jane', firstName: null, lastName: null, displayName: 'jane', isAdmin: false });
    const fixture = TestBed.createComponent(Header);
    await fixture.whenStable();

    const logout = Array.from(fixture.nativeElement.querySelectorAll('button') as NodeListOf<HTMLButtonElement>)
      .find((b) => b.textContent?.trim() === 'Abmelden')!;
    logout.click();
    await fixture.whenStable();

    expect(session.logout).toHaveBeenCalled();
  });
});
