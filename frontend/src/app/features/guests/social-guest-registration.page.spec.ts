import { computed, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ApiClient, ApiError } from '../../core/api/api-client';
import { SocialGuestInfoDto } from '../../core/api/generated';
import { SessionStore } from '../../core/auth/session.store';
import { Notify } from '../../core/feedback/notify';
import { I18n } from '../../core/i18n/i18n';
import { SocialGuestRegistrationPage } from './social-guest-registration.page';

function info(over: Partial<SocialGuestInfoDto> = {}, eventOver: Partial<SocialGuestInfoDto['event']> = {}): SocialGuestInfoDto {
  return {
    event: {
      id: 9, tournamentId: 3, name: 'Dinner', date: '2099-06-01', isLocked: false,
      totalCapacity: 20, registered: 2, available: 18, userRegistered: false, ...eventOver,
    },
    description: 'Abendessen',
    menus: [{ id: 1, name: 'Fleisch' }, { id: 2, name: 'Fisch' }],
    tables: [{ id: 11, number: 1, capacity: 4, available: 2 }],
    ...over,
  };
}

describe('SocialGuestRegistrationPage', () => {
  const isAdmin = signal(false);
  let api: { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn> };
  let notify: { success: ReturnType<typeof vi.fn> };
  let navigate: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    isAdmin.set(false);
    api = { get: vi.fn().mockResolvedValue(info()), post: vi.fn().mockResolvedValue(undefined) };
    notify = { success: vi.fn() };
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: ApiClient, useValue: api },
        { provide: Notify, useValue: notify },
        { provide: SessionStore, useValue: { isAdmin: computed(() => isAdmin()), loggedIn: signal(false) } },
      ],
    });
    TestBed.inject(I18n).use('de', {
      navigation: { home: 'Startseite' },
      validation: { required: 'Das Feld :field ist erforderlich.' },
      social_events: {
        locked_message: 'Anmeldung geschlossen.', not_found: 'Anlass nicht gefunden', menu: 'Menü',
        select_menu: 'Menü auswählen', table: 'Tisch', table_number: 'Tisch :number', libero: 'Kein Wunsch',
        invalid_menu: 'Ungültige Menüauswahl.',
      },
      guests: { first_name: 'Vorname', last_name: 'Nachname', email: 'eMail', register: 'Als Gast anmelden' },
    });
    navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
  });

  async function render() {
    const fixture = TestBed.createComponent(SocialGuestRegistrationPage);
    fixture.componentRef.setInput('id', '9');
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;
    const type = (id: string, value: string) => {
      const input = el.querySelector<HTMLInputElement | HTMLSelectElement>(`#${id}`)!;
      input.value = value;
      input.dispatchEvent(new Event('input'));
    };
    const submit = async () => {
      el.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
      await fixture.whenStable();
    };
    const fill = () => {
      type('firstName', 'Gus');
      type('lastName', 'Guest');
      type('email', 'gus@example.com');
      type('menu', '2');
      type('table', '11');
    };
    return { el, type, submit, fill };
  }

  it('shows event info and the required markers for anonymous visitors', async () => {
    const { el } = await render();

    expect(api.get).toHaveBeenCalledWith('/social-events/9/guest-info', expect.anything());
    expect(el.textContent).toContain('Dinner');
    for (const label of ['Vorname', 'Nachname', 'eMail', 'Menü']) {
      const text = Array.from(el.querySelectorAll('label')).find((l) => l.textContent?.includes(label))?.textContent;
      expect(text, label).toContain('*');
    }
    const table = Array.from(el.querySelectorAll('label')).find((l) => l.textContent?.includes('Tisch'));
    expect(table?.textContent).not.toContain('*');
  });

  it('submitting empty shows required errors without calling the API', async () => {
    const { el, submit } = await render();

    await submit();

    expect(api.post).not.toHaveBeenCalled();
    for (const label of ['Vorname', 'Nachname', 'eMail', 'Menü']) {
      expect(el.textContent).toContain(`Das Feld ${label} ist erforderlich.`);
    }
  });

  it('posts the exact body, toasts and navigates home', async () => {
    const { fill, submit } = await render();

    fill();
    await submit();

    expect(api.post).toHaveBeenCalledWith(
      '/social-events/9/guests',
      { firstName: 'Gus', lastName: 'Guest', email: 'gus@example.com', menuId: 2, tableId: 11 },
      undefined,
    );
    expect(notify.success).toHaveBeenCalledWith('social_events.guest_register_success');
    expect(navigate).toHaveBeenCalledWith('/');
  });

  it('without menus the menu select is absent and not required', async () => {
    api.get.mockResolvedValue(info({ menus: [], tables: [] }));
    const { el, type, submit } = await render();

    expect(el.querySelector('#menu')).toBeNull();
    type('firstName', 'Gus');
    type('lastName', 'Guest');
    type('email', 'g@example.com');
    await submit();

    expect(api.post).toHaveBeenCalledWith(
      '/social-events/9/guests',
      { firstName: 'Gus', lastName: 'Guest', email: 'g@example.com', menuId: null, tableId: null },
      undefined,
    );
  });

  it('shows 422 field errors under the inputs', async () => {
    api.post.mockRejectedValue(new ApiError(422, 'validation.failed', { menuId: { key: 'social_events.invalid_menu' } }));
    const { el, fill, submit } = await render();

    fill();
    await submit();

    expect(el.querySelector('#menuId-error')?.textContent).toContain('Ungültige Menüauswahl.');
    expect(navigate).not.toHaveBeenCalled();
  });

  it('locked event: anonymous sees the message and no form', async () => {
    api.get.mockResolvedValue(info({}, { isLocked: true }));
    const { el } = await render();

    expect(el.textContent).toContain('Anmeldung geschlossen.');
    expect(el.querySelector('form')).toBeNull();
  });

  it('locked event: admin sees the form, email is optional, and goes to the admin page', async () => {
    isAdmin.set(true);
    api.get.mockResolvedValue(info({ menus: [] }, { isLocked: true }));
    const { el, type, submit } = await render();

    const email = Array.from(el.querySelectorAll('label')).find((l) => l.textContent?.includes('eMail'));
    expect(email?.textContent).not.toContain('*');
    type('firstName', 'Gus');
    type('lastName', 'Guest');
    await submit();

    expect(api.post).toHaveBeenCalledWith(
      '/social-events/9/guests',
      { firstName: 'Gus', lastName: 'Guest', email: null, menuId: null, tableId: null },
      undefined,
    );
    expect(navigate).toHaveBeenCalledWith('/social-events/9/admin');
  });

  it('shows the not-found message on 404', async () => {
    api.get.mockRejectedValue(new ApiError(404, 'social_events.not_found'));
    const { el } = await render();

    expect(el.textContent).toContain('Anlass nicht gefunden');
  });
});
