import { computed, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { ApiClient, ApiError } from '../../core/api/api-client';
import { SocialEventDetailDto } from '../../core/api/generated';
import { SessionStore } from '../../core/auth/session.store';
import { ConfirmService } from '../../core/feedback/confirm.service';
import { I18n } from '../../core/i18n/i18n';
import { SocialEventDetailPage } from './social-event-detail.page';

function detail(over: Partial<SocialEventDetailDto> = {}, eventOver: Partial<SocialEventDetailDto['event']> = {}): SocialEventDetailDto {
  return {
    event: {
      id: 9, tournamentId: 3, name: 'Dinner', date: '2099-06-01', isLocked: false,
      totalCapacity: 20, registered: 2, available: 18, userRegistered: false, ...eventOver,
    },
    description: 'Zeile 1\nZeile 2',
    registrationClose: '2099-05-30T12:00:00',
    menus: [{ id: 1, name: 'Fleisch' }, { id: 2, name: 'Fisch' }],
    tables: [
      { id: 11, number: 1, capacity: 4, available: 2 },
      { id: 12, number: 2, capacity: 4, available: 0 },
    ],
    myRegistration: null,
    participants: [{ name: 'Anna', tableNumber: 1 }, { name: 'Ben', tableNumber: null }],
    ...over,
  };
}

describe('SocialEventDetailPage', () => {
  const isAdmin = signal(false);
  let api: { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn>; delete: ReturnType<typeof vi.fn> };
  let confirm: { ask: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    isAdmin.set(false);
    api = { get: vi.fn().mockResolvedValue(detail()), post: vi.fn(), delete: vi.fn() };
    confirm = { ask: vi.fn().mockResolvedValue(true) };
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: ApiClient, useValue: api },
        { provide: ConfirmService, useValue: confirm },
        {
          provide: SessionStore,
          useValue: { isAdmin: computed(() => isAdmin()), loggedIn: signal(true), user: signal({ displayName: 'Anna' }) },
        },
      ],
    });
    TestBed.inject(I18n).use('de', {
      nav: { back: 'Zurück' },
      navigation: { home: 'Startseite' },
      validation: { required: 'Das Feld :field ist erforderlich.' },
      social_events: {
        registered: 'Angemeldet', menu: 'Menü', table: 'Tisch', table_number: 'Tisch :number', libero: 'Kein Wunsch',
        unregister: 'Anlass abmelden', unregister_confirm: 'Wirklich abmelden?', register: 'Anmelden',
        register_confirm: 'Anmelden?', locked_message: 'Anmeldung geschlossen.', full: 'Ausgebucht',
        select_menu: 'Menü auswählen', participants: 'Teilnehmer', edit: 'Bearbeiten', description: 'Beschreibung',
        registration_close: 'Anmeldeschluss', not_found: 'Anlass nicht gefunden', invalid_menu: 'Ungültige Menüauswahl.',
        name: 'Name',
      },
      events: { your_registration: 'Deine Anmeldung', no_participants: 'Noch keine Anmeldungen.' },
    });
  });

  async function render() {
    const fixture = TestBed.createComponent(SocialEventDetailPage);
    fixture.componentRef.setInput('id', '9');
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;
    const button = (text: string) =>
      Array.from(el.querySelectorAll('button')).find((b) => b.textContent?.trim() === text) as HTMLButtonElement | undefined;
    const select = (id: string) => el.querySelector<HTMLSelectElement>(`#${id}`)!;
    const choose = (id: string, value: string) => {
      const s = select(id);
      s.value = value;
      s.dispatchEvent(new Event('input'));
    };
    const submit = async () => {
      el.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
      await fixture.whenStable();
    };
    return { fixture, el, button, select, choose, submit };
  }

  it('renders name, description, participants and the back link', async () => {
    const { el } = await render();

    expect(api.get).toHaveBeenCalledWith('/social-events/9', expect.anything());
    expect(el.textContent).toContain('Dinner');
    expect(el.textContent).toContain('Zeile 1');
    expect(el.textContent).toContain('Anna');
    expect(el.textContent).toContain('Ben');
    expect(el.querySelector('a[href="/?month=2099-06"]')).not.toBeNull();
  });

  it('registered: shows menu and table, unregister confirms and shows the returned detail', async () => {
    api.get.mockResolvedValue(
      detail({ myRegistration: { menuName: 'Fisch', tableNumber: 1 } }, { userRegistered: true }),
    );
    const { fixture, el, button } = await render();

    expect(el.textContent).toContain('Angemeldet');
    expect(el.textContent).toContain('Fisch');
    expect(el.textContent).toContain('Tisch 1');
    expect(el.querySelector('form')).toBeNull();

    api.delete.mockResolvedValue(detail());
    button('Anlass abmelden')!.click();
    await fixture.whenStable();

    expect(confirm.ask).toHaveBeenCalledWith('Wirklich abmelden?', { danger: true });
    expect(api.delete).toHaveBeenCalledWith('/social-events/9/registrations/me', undefined);
    expect(el.querySelector('form')).not.toBeNull();
  });

  it('participants: table number or libero per row, count in the heading, own row highlighted', async () => {
    const { el } = await render();
    const rows = Array.from(el.querySelectorAll('[data-participants] tbody tr')).map((tr) =>
      Array.from(tr.querySelectorAll('td')).map((td) => td.textContent?.trim()),
    );

    expect(el.querySelector('[data-participants] h2')?.textContent).toContain('Teilnehmer (2)');
    expect(rows).toEqual([['1', 'Anna'], ['Kein Wunsch', 'Ben']]);
    expect(el.querySelector('[data-participants] tr[data-own]')?.textContent).toContain('Anna');
  });

  it('registered: menu and table are label/value pairs under "Deine Anmeldung"', async () => {
    api.get.mockResolvedValue(detail({ myRegistration: { menuName: 'Fisch', tableNumber: 2 } }, { userRegistered: true }));
    const { el } = await render();
    const pairs = Array.from(el.querySelectorAll('[data-registration] dl > div')).map((d) => [
      d.querySelector('dt')?.textContent?.trim(),
      d.querySelector('dd')?.textContent?.trim(),
    ]);

    expect(el.querySelector('[data-registration] h2')?.textContent).toContain('Deine Anmeldung');
    expect(pairs).toEqual([['Menü', 'Fisch'], ['Tisch', 'Tisch 2']]);
  });

  it('registered without a table shows libero', async () => {
    api.get.mockResolvedValue(detail({ myRegistration: { menuName: 'Fisch', tableNumber: null } }, { userRegistered: true }));
    const { el } = await render();

    expect(el.textContent).toContain('Kein Wunsch');
  });

  it('requires a menu when menus exist: no API call and an error is shown', async () => {
    const { el, submit } = await render();

    await submit();

    expect(api.post).not.toHaveBeenCalled();
    expect(el.textContent).toContain('Das Feld Menü ist erforderlich.');
  });

  it('submits menuId and tableId after confirmation', async () => {
    api.post.mockResolvedValue(detail({ myRegistration: { menuName: 'Fisch', tableNumber: 1 } }, { userRegistered: true }));
    const { el, choose, submit } = await render();

    choose('menu', '2');
    choose('table', '11');
    await submit();

    expect(confirm.ask).toHaveBeenCalledWith('Anmelden?');
    expect(api.post).toHaveBeenCalledWith('/social-events/9/registrations', { menuId: 2, tableId: 11 }, undefined);
    expect(el.textContent).toContain('Tisch 1');
  });

  it('sends tableId null for libero', async () => {
    api.post.mockResolvedValue(detail());
    const { choose, submit } = await render();

    choose('menu', '1');
    await submit();

    expect(api.post).toHaveBeenCalledWith('/social-events/9/registrations', { menuId: 1, tableId: null }, undefined);
  });

  it('does not register when the confirmation is declined', async () => {
    confirm.ask.mockResolvedValue(false);
    const { choose, submit } = await render();

    choose('menu', '1');
    await submit();

    expect(api.post).not.toHaveBeenCalled();
  });

  it('without menus no menu select is shown and the form submits', async () => {
    api.get.mockResolvedValue(detail({ menus: [] }));
    api.post.mockResolvedValue(detail());
    const { el, submit } = await render();

    expect(el.querySelector('#menu')).toBeNull();
    await submit();

    expect(api.post).toHaveBeenCalledWith('/social-events/9/registrations', { menuId: null, tableId: null }, undefined);
  });

  it('shows a 422 menu field error under the menu select', async () => {
    api.post.mockRejectedValue(new ApiError(422, 'validation.failed', { menuId: { key: 'social_events.invalid_menu' } }));
    const { el, choose, submit } = await render();

    choose('menu', '1');
    await submit();

    expect(el.querySelector('#menu-error')?.textContent).toContain('Ungültige Menüauswahl.');
  });

  it('disables full tables', async () => {
    const { select } = await render();

    const options = Array.from(select('table').options);
    expect(options.find((o) => o.value === '12')!.disabled).toBe(true);
    expect(options.find((o) => o.value === '11')!.disabled).toBe(false);
  });

  it('locked: shows the message and no form', async () => {
    api.get.mockResolvedValue(detail({}, { isLocked: true }));
    const { el } = await render();

    expect(el.textContent).toContain('Anmeldung geschlossen.');
    expect(el.querySelector('form')).toBeNull();
  });

  it('full: shows the full message and no form', async () => {
    api.get.mockResolvedValue(detail({}, { available: 0 }));
    const { el } = await render();

    expect(el.textContent).toContain('Ausgebucht');
    expect(el.querySelector('form')).toBeNull();
  });

  it('admins see the edit link, members do not', async () => {
    let { el } = await render();
    expect(el.querySelector('a[href="/social-events/9/admin"]')).toBeNull();

    isAdmin.set(true);
    ({ el } = await render());
    expect(el.querySelector('a[href="/social-events/9/admin"]')).not.toBeNull();
  });

  it('shows the not-found message on 404', async () => {
    api.get.mockRejectedValue(new ApiError(404, 'social_events.not_found'));
    const { el } = await render();

    expect(el.textContent).toContain('Anlass nicht gefunden');
  });
});
