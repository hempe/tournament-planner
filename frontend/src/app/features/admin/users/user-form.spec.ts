import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { AdminUserDto } from '../../../core/api/generated';
import { Notify } from '../../../core/feedback/notify';
import { I18n } from '../../../core/i18n/i18n';
import { UserCreatePage } from './user-create.page';
import { UserEditPage } from './user-edit.page';

const existing: AdminUserDto = { id: 5, username: 'bert', firstName: 'Bert', lastName: 'Beispiel', displayName: 'Bert Beispiel', male: false, isAdmin: false, rfeg: 'R1', memberNumber: 'M9' };

describe('user create and edit pages', () => {
  let api: Record<'get' | 'put' | 'post', ReturnType<typeof vi.fn>>;
  let notify: { success: ReturnType<typeof vi.fn> };
  let navigate: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    api = { get: vi.fn().mockResolvedValue(existing), put: vi.fn().mockResolvedValue(existing), post: vi.fn().mockResolvedValue(existing) };
    notify = { success: vi.fn() };
    TestBed.configureTestingModule({ providers: [provideRouter([]), { provide: ApiClient, useValue: api }, { provide: Notify, useValue: notify }] });
    TestBed.inject(I18n).use('de', {
      validation: { required: 'Das Feld :field ist erforderlich.' },
      users: {
        new: 'Neuer Benutzer', edit: 'Bearbeiten', salutation: 'Anrede', mr: 'Herr', mrs: 'Frau', username: 'Benutzername', password: 'Passwort',
        new_password: 'Neues Passwort', first_name: 'Vorname', last_name: 'Nachname', member_number: 'Mitgliedernummer', rfeg: 'RFeg',
        create: 'Erstellen', update: 'Aktualisieren', username_taken: 'Benutzername ":username" ist bereits vergeben', not_found: 'Benutzer nicht gefunden',
      },
    });
    navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
  });

  async function render<T>(component: new () => T, id?: string) {
    const fixture = TestBed.createComponent(component);
    if (id) {
      fixture.componentRef.setInput('id', id);
    }
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;
    const type = (elId: string, value: string) => {
      const input = el.querySelector<HTMLInputElement | HTMLSelectElement>(`#${elId}`)!;
      input.value = value;
      input.dispatchEvent(new Event('input'));
      input.dispatchEvent(new Event('change'));
    };
    const submit = async () => {
      el.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
      await fixture.whenStable();
    };
    return { el, type, submit };
  }

  describe('create', () => {
    it('shows the empty form with a required password and does not submit it incomplete', async () => {
      const { el, submit } = await render(UserCreatePage);

      expect(el.querySelector('h1')?.textContent).toContain('Neuer Benutzer');
      expect(el.querySelector('label[for="password"]')?.textContent).toContain('Passwort');
      await submit();

      expect(api.post).not.toHaveBeenCalled();
      expect(el.textContent).toContain('Das Feld Benutzername ist erforderlich.');
      expect(el.textContent).toContain('Das Feld Passwort ist erforderlich.');
      expect(el.textContent).toContain('Das Feld Anrede ist erforderlich.');
    });

    it('posts the request body, toasts and goes to the list', async () => {
      const { type, submit } = await render(UserCreatePage);
      type('male', 'true');
      type('username', 'neu');
      type('password', 'Geheim123!');
      type('firstName', 'Nina');
      type('memberNumber', '42');
      await submit();

      expect(api.post).toHaveBeenCalledWith(
        '/users',
        { male: true, username: 'neu', password: 'Geheim123!', firstName: 'Nina', lastName: null, rfeg: null, memberNumber: '42' },
        undefined,
      );
      expect(notify.success).toHaveBeenCalledWith('users.create_success');
      expect(navigate).toHaveBeenCalledWith('/users');
    });

    it('sends Frau as male=false', async () => {
      const { type, submit } = await render(UserCreatePage);
      type('male', 'false');
      type('username', 'neu');
      type('password', 'Geheim123!');
      await submit();

      expect(api.post.mock.calls[0][1]).toMatchObject({ male: false });
    });

    it('shows a taken username under the username input with the name', async () => {
      api.post.mockRejectedValue(new ApiError(422, 'validation.failed', { username: { key: 'users.username_taken', params: { username: 'neu' } } }));
      const { el, type, submit } = await render(UserCreatePage);
      type('male', 'true');
      type('username', 'neu');
      type('password', 'Geheim123!');
      await submit();

      expect(el.querySelector('#username-error')?.textContent).toContain('Benutzername "neu" ist bereits vergeben');
      expect(navigate).not.toHaveBeenCalled();
    });
  });

  describe('edit', () => {
    it('loads the user into the form with an optional new password', async () => {
      const { el } = await render(UserEditPage, '5');

      expect(api.get).toHaveBeenCalledWith('/users/5', expect.anything());
      expect(el.querySelector('h1')?.textContent).toContain('Bearbeiten');
      expect(el.querySelector<HTMLInputElement>('#username')!.value).toBe('bert');
      expect(el.querySelector<HTMLSelectElement>('#male')!.value).toBe('false');
      expect(el.querySelector<HTMLInputElement>('#firstName')!.value).toBe('Bert');
      expect(el.querySelector<HTMLInputElement>('#rfeg')!.value).toBe('R1');
      expect(el.querySelector('label[for="password"]')?.textContent).toContain('Neues Passwort');
      expect(el.querySelector('label[for="password"]')?.textContent).not.toContain('*');
    });

    it('without a password sends password null; toasts and goes to the list', async () => {
      const { type, submit } = await render(UserEditPage, '5');
      type('lastName', 'Neu');
      await submit();

      expect(api.put).toHaveBeenCalledWith(
        '/users/5',
        { male: false, username: 'bert', password: null, firstName: 'Bert', lastName: 'Neu', rfeg: 'R1', memberNumber: 'M9' },
        undefined,
      );
      expect(notify.success).toHaveBeenCalledWith('users.update_success');
      expect(navigate).toHaveBeenCalledWith('/users');
    });

    it('sends a typed new password', async () => {
      const { type, submit } = await render(UserEditPage, '5');
      type('password', 'Neu12345!');
      await submit();

      expect(api.put.mock.calls[0][1]).toMatchObject({ password: 'Neu12345!' });
    });

    it('shows a taken username under the input', async () => {
      api.put.mockRejectedValue(new ApiError(422, 'validation.failed', { username: { key: 'users.username_taken', params: { username: 'anna' } } }));
      const { el, type, submit } = await render(UserEditPage, '5');
      type('username', 'anna');
      await submit();

      expect(el.querySelector('#username-error')?.textContent).toContain('Benutzername "anna" ist bereits vergeben');
    });

    it('shows the not-found message on 404', async () => {
      api.get.mockRejectedValue(new ApiError(404, 'users.not_found'));
      const { el } = await render(UserEditPage, '5');

      expect(el.textContent).toContain('Benutzer nicht gefunden');
    });
  });
});
