import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ApiError } from '../../core/api/api-client';
import { SessionStore } from '../../core/auth/session.store';
import { I18n } from '../../core/i18n/i18n';
import { LoginPage, safeReturnUrl } from './login.page';

describe('LoginPage', () => {
  let session: { login: ReturnType<typeof vi.fn> };
  let navigate: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    session = { login: vi.fn().mockResolvedValue(undefined) };
    TestBed.configureTestingModule({
      providers: [provideRouter([]), { provide: SessionStore, useValue: session }],
    });
    TestBed.inject(I18n).use('de', {
      auth: {
        login: 'Mitglieder Anmeldung',
        username: 'Benutzername',
        password: 'Passwort',
        login_failed: 'Anmeldung fehlgeschlagen',
      },
      validation: { required: 'Das Feld :field ist erforderlich.' },
    });
    navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
  });

  async function render(returnUrl?: string) {
    const fixture = TestBed.createComponent(LoginPage);
    if (returnUrl !== undefined) {
      fixture.componentRef.setInput('returnUrl', returnUrl);
    }
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;
    const type = (id: string, value: string) => {
      const input = el.querySelector<HTMLInputElement>(`#${id}`)!;
      input.value = value;
      input.dispatchEvent(new Event('input'));
    };
    // jsdom does not submit forms on button click
    const submit = async () => {
      el.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
      await fixture.whenStable();
    };
    return { fixture, el, type, submit };
  }

  it('shows required errors and does not call the API when empty', async () => {
    const { el, submit } = await render();

    await submit();

    expect(session.login).not.toHaveBeenCalled();
    expect(el.textContent).toContain('Das Feld Benutzername ist erforderlich.');
    expect(el.textContent).toContain('Das Feld Passwort ist erforderlich.');
  });

  it('logs in and navigates to the return URL', async () => {
    const { type, submit } = await render('/events/3');

    type('username', 'jane');
    type('password', 'secret');
    await submit();

    expect(session.login).toHaveBeenCalledWith('jane', 'secret');
    expect(navigate).toHaveBeenCalledWith('/events/3');
  });

  it('shows the server message for wrong credentials', async () => {
    session.login.mockRejectedValue(new ApiError(422, 'auth.login_failed'));
    const { el, type, submit } = await render();

    type('username', 'jane');
    type('password', 'wrong');
    await submit();

    expect(el.querySelector('[role="alert"]')?.textContent).toContain('Anmeldung fehlgeschlagen');
    expect(navigate).not.toHaveBeenCalled();
  });
});

describe('safeReturnUrl', () => {
  it('only allows same-site paths', () => {
    expect(safeReturnUrl('/events/3')).toBe('/events/3');
    expect(safeReturnUrl(undefined)).toBe('/');
    expect(safeReturnUrl('')).toBe('/');
    expect(safeReturnUrl('https://evil.example')).toBe('/');
    expect(safeReturnUrl('//evil.example')).toBe('/');
  });
});
