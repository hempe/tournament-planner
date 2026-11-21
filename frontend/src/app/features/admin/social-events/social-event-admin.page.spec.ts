import { ErrorHandler } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { SocialEventAdminDto } from '../../../core/api/generated';
import { AppErrorHandler } from '../../../core/errors/app-error-handler';
import { ConfirmService } from '../../../core/feedback/confirm.service';
import { Notify } from '../../../core/feedback/notify';
import { I18n } from '../../../core/i18n/i18n';
import { SocialEventAdminPage } from './social-event-admin.page';

function dto(over: Partial<SocialEventAdminDto> = {}, eventOver: Partial<SocialEventAdminDto['event']> = {}): SocialEventAdminDto {
  return {
    event: { id: 4, tournamentId: 3, name: 'Dinner', date: '2099-06-01', isLocked: false, totalCapacity: 18, registered: 2, available: 16, userRegistered: false, ...eventOver },
    description: 'Beschreibung X',
    registrationClose: '2099-05-30T12:00:00',
    menus: 'Fleisch, Fisch',
    tables: '10, 8',
    menuOptions: [{ id: 1, name: 'Fleisch' }],
    tableOptions: [{ id: 1, number: 1, capacity: 10, available: 9 }],
    registrations: [
      { id: 21, name: 'Anna Muster', email: null, isGuest: false, menuName: 'Fleisch', tableNumber: 1 },
      { id: 22, name: 'Gus Guest', email: 'gus@example.com', isGuest: true, menuName: null, tableNumber: null },
    ],
    ...over,
  };
}

describe('SocialEventAdminPage', () => {
  let api: Record<'get' | 'put' | 'post' | 'delete', ReturnType<typeof vi.fn>>;
  let confirm: { ask: ReturnType<typeof vi.fn> };
  let notify: { success: ReturnType<typeof vi.fn>; error: ReturnType<typeof vi.fn> };
  let navigate: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    api = { get: vi.fn().mockResolvedValue(dto()), put: vi.fn(), post: vi.fn(), delete: vi.fn() };
    confirm = { ask: vi.fn().mockResolvedValue(true) };
    notify = { success: vi.fn(), error: vi.fn() };
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: ApiClient, useValue: api },
        { provide: ConfirmService, useValue: confirm },
        { provide: Notify, useValue: notify },
        { provide: ErrorHandler, useClass: AppErrorHandler },
      ],
    });
    TestBed.inject(I18n).use('de', {
      nav: { back: 'Zurück' },
      validation: { required: 'Das Feld :field ist erforderlich.' },
      guests: { guest: 'Gast' },
      events: { delete_success: 'Anlass gelöscht', lock_success: 'Gesperrt', unlock_success: 'Entsperrt', update_success: 'Aktualisiert', unregistration_success: 'Abgemeldet', unregister_user_confirm: ':name abmelden?', locked: 'Gesperrt', no_participants: 'Noch keine Anmeldungen.' },
      social_events: {
        libero: 'Kein Wunsch',
        name: 'Name', date: 'Datum', description: 'Beschreibung', registration_close: 'Anmeldeschluss', menus: 'Menüoptionen', menus_hint: 'Menü-Hinweis',
        tables: 'Tische', tables_hint: 'Tisch-Hinweis', save: 'Speichern', lock: 'Sperren', unlock: 'Entsperren', lock_confirm: 'Anlass sperren?',
        unlock_confirm: 'Anlass entsperren?', delete: 'Löschen', delete_confirm: 'Anlass dauerhaft löschen?', participants: 'Teilnehmer',
        add_guest: 'Gast hinzufügen', unregister: 'Anlass abmelden', table_number: 'Tisch :number', not_found: 'Anlass nicht gefunden',
      },
      navigation: { home: 'Startseite' },
    });
    navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
  });

  async function render() {
    const fixture = TestBed.createComponent(SocialEventAdminPage);
    fixture.componentRef.setInput('id', '4');
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;
    const buttonIn = (root: ParentNode, text: string) =>
      Array.from(root.querySelectorAll('button')).find((b) => b.textContent?.trim() === text) as HTMLButtonElement;
    const click = async (b: HTMLElement) => {
      b.click();
      await fixture.whenStable();
    };
    const type = (id: string, value: string) => {
      const input = el.querySelector<HTMLInputElement>(`#${id}`)!;
      input.value = value;
      input.dispatchEvent(new Event('input'));
    };
    const submit = async () => {
      el.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
      await fixture.whenStable();
    };
    return { fixture, el, buttonIn, click, type, submit };
  }

  it('renders the header, the form in edit mode and the participants', async () => {
    const { el } = await render();

    expect(api.get).toHaveBeenCalledWith('/social-events/4/admin', expect.anything());
    expect(el.querySelector('h1')?.textContent).toContain('Dinner');
    expect(el.querySelector<HTMLInputElement>('#name')!.value).toBe('Dinner');
    expect(el.querySelector<HTMLInputElement>('#date')!.value).toBe('2099-06-01');
    expect(el.querySelector<HTMLInputElement>('#menus')!.value).toBe('Fleisch, Fisch');
    expect(el.querySelector<HTMLInputElement>('#tables')!.value).toBe('10, 8');
    expect(el.querySelector<HTMLInputElement>('#registrationClose')!.value).toBe('2099-05-30T12:00');
    const rows = el.querySelectorAll('[data-participant]');
    expect(rows).toHaveLength(2);
    expect(rows[0].textContent).toContain('Anna Muster');
    expect(rows[0].textContent).toContain('Fleisch');
    expect(rows[0].textContent).toContain('Tisch 1');
    expect(rows[0].textContent).not.toContain('Gast');
    expect(rows[1].textContent).toContain('Gus Guest');
    expect(rows[1].textContent).toContain('Gast');
    expect(rows[1].textContent).toContain('Kein Wunsch');
    expect(el.querySelector('[data-participants] h2')?.textContent).toContain('(2)');
  });

  it('links back to the tournament administration when linked, else home; add guest link', async () => {
    const { el } = await render();
    const hrefs = Array.from(el.querySelectorAll('a')).map((a) => a.getAttribute('href'));

    expect(hrefs).toContain('/events/3/admin');
    expect(hrefs).toContain('/social-events/4/guests/new');
  });

  it('back goes home for a standalone social event', async () => {
    api.get.mockResolvedValue(dto({}, { tournamentId: null }));
    const { el } = await render();

    expect(Array.from(el.querySelectorAll('a')).map((a) => a.getAttribute('href'))).toContain('/');
  });

  it('saves the form body (tournamentId stays) and shows the returned DTO', async () => {
    api.put.mockResolvedValue(dto({ description: 'Neu' }));
    const { type, submit } = await render();
    type('name', 'Neues Dinner');
    await submit();

    expect(api.put).toHaveBeenCalledWith(
      '/social-events/4',
      { name: 'Neues Dinner', date: '2099-06-01', tournamentId: 3, description: 'Beschreibung X', registrationClose: '2099-05-30T12:00', menus: 'Fleisch, Fisch', tables: '10, 8' },
      undefined,
    );
    expect(notify.success).toHaveBeenCalledWith('events.update_success');
  });

  it('a 409 with params (menu in use) reaches the toast through the global handler', async () => {
    const error = new ApiError(409, 'social_events.menu_in_use', {}, { menu: 'Fisch', count: 2 });
    api.put.mockRejectedValue(error);
    const { submit } = await render();

    await submit();
    await new Promise((resolve) => setTimeout(resolve));

    expect(notify.error).toHaveBeenCalledWith('social_events.menu_in_use', { menu: 'Fisch', count: 2 });
  });

  it('lock confirms and re-renders; unlock does the reverse', async () => {
    api.put.mockResolvedValue(dto({}, { isLocked: true }));
    api.delete.mockResolvedValue(dto());
    const { el, buttonIn, click } = await render();

    await click(buttonIn(el, 'Sperren'));
    expect(confirm.ask).toHaveBeenCalledWith('Anlass sperren?');
    expect(api.put).toHaveBeenCalledWith('/social-events/4/lock', undefined, undefined);
    expect(buttonIn(el, 'Sperren')).toBeUndefined();

    await click(buttonIn(el, 'Entsperren'));
    expect(confirm.ask).toHaveBeenLastCalledWith('Anlass entsperren?');
    expect(api.delete).toHaveBeenCalledWith('/social-events/4/lock', undefined);
    expect(buttonIn(el, 'Sperren')).toBeDefined();
  });

  it('keeps unsaved form edits when locking', async () => {
    api.put.mockResolvedValue(dto({ description: 'Anders' }, { isLocked: true }));
    const { el, buttonIn, click, type } = await render();

    type('name', 'Ungespeichert');
    await click(buttonIn(el, 'Sperren'));

    expect(buttonIn(el, 'Entsperren')).toBeDefined();
    expect(el.querySelector<HTMLInputElement>('#name')!.value).toBe('Ungespeichert');
  });

  it('deletes after a danger confirmation and goes back to the tournament', async () => {
    api.delete.mockResolvedValue(undefined);
    const { el, buttonIn, click } = await render();

    await click(buttonIn(el, 'Löschen'));

    expect(confirm.ask).toHaveBeenCalledWith('Anlass dauerhaft löschen?', { danger: true });
    expect(api.delete).toHaveBeenCalledWith('/social-events/4', undefined);
    expect(notify.success).toHaveBeenCalledWith('events.delete_success');
    expect(navigate).toHaveBeenCalledWith('/events/3/admin');
  });

  it('does not delete when the confirmation is cancelled', async () => {
    confirm.ask.mockResolvedValue(false);
    const { el, buttonIn, click } = await render();

    await click(buttonIn(el, 'Löschen'));

    expect(api.delete).not.toHaveBeenCalled();
  });

  it('removing a participant confirms with the name and re-renders from the returned DTO', async () => {
    api.delete.mockResolvedValue(dto({ registrations: [dto().registrations[1]] }));
    const { el, click } = await render();
    const row = el.querySelector<HTMLElement>('[data-participant]')!;

    await click(row.querySelector<HTMLElement>('button[aria-label="Anlass abmelden"]')!);

    expect(confirm.ask).toHaveBeenCalledWith('Anna Muster abmelden?', { danger: true });
    expect(api.delete).toHaveBeenCalledWith('/social-events/4/registrations/21', undefined);
    expect(el.querySelectorAll('[data-participant]')).toHaveLength(1);
    expect(el.textContent).not.toContain('Anna Muster');
  });

  it('shows the not-found message on 404', async () => {
    api.get.mockRejectedValue(new ApiError(404, 'social_events.not_found'));
    const { el } = await render();

    expect(el.textContent).toContain('Anlass nicht gefunden');
  });
});
