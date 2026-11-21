import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { AdminGuestDto, AdminRegistrationDto, EventAdminDto } from '../../../core/api/generated';
import { ConfirmService } from '../../../core/feedback/confirm.service';
import { Notify } from '../../../core/feedback/notify';
import { formatDateTime } from '../../../core/format/dates';
import { I18n } from '../../../core/i18n/i18n';
import { EventAdminPage } from './event-admin.page';

function reg(userId: number, name: string, male: boolean, state: 'registered' | 'waitlist', comment = ''): AdminRegistrationDto {
  return { userId, name, male, state, comment, registeredAt: '2099-05-01T10:30:00', rfeg: null, memberNumber: null };
}

function guest(id: number, firstName: string, male: boolean): AdminGuestDto {
  return { id, firstName, lastName: 'Guest', male, email: `${firstName.toLowerCase()}@example.com`, handicap: 12.4, comment: 'Gast-Notiz', registeredAt: '2099-05-02T08:00:00' };
}

function dto(over: Partial<EventAdminDto> = {}, eventOver: Partial<EventAdminDto['event']> = {}): EventAdminDto {
  return {
    event: { id: 3, name: 'Frühlingscup', date: '2099-06-01', capacity: 10, joined: 2, waitlist: 2, available: 8, isLocked: false, userState: 'none', ...eventOver },
    mixed: false,
    description: 'Beschreibung X',
    priceMembers: 'CHF 50',
    priceGuests: null,
    registrationClose: '2099-05-30T12:00:00',
    registrations: [
      reg(11, 'Anna', true, 'registered', 'c1'),
      reg(12, 'Berta', false, 'registered'),
      reg(13, 'Carl', true, 'waitlist'),
      reg(14, 'Dora', false, 'waitlist'),
    ],
    guests: [guest(5, 'Gus', true), guest(6, 'Gina', false)],
    availableUsers: [{ id: 20, displayName: 'Egon' }],
    socialEvent: null,
    ...over,
  };
}

describe('EventAdminPage', () => {
  let api: Record<'get' | 'put' | 'post' | 'patch' | 'delete', ReturnType<typeof vi.fn>>;
  let confirm: { ask: ReturnType<typeof vi.fn> };
  let notify: { success: ReturnType<typeof vi.fn> };
  let navigate: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    api = { get: vi.fn().mockResolvedValue(dto()), put: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() };
    confirm = { ask: vi.fn().mockResolvedValue(true) };
    notify = { success: vi.fn() };
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: ApiClient, useValue: api },
        { provide: ConfirmService, useValue: confirm },
        { provide: Notify, useValue: notify },
      ],
    });
    TestBed.inject(I18n).use('de', {
      nav: { back: 'Zurück' },
      validation: { required: 'Das Feld :field ist erforderlich.' },
      events: {
        name: 'Name', date: 'Datum', max_participants: 'Max. Teilnehmer', play_together: 'Mixed', description: 'Beschreibung',
        price_members: 'Preis für Mitglieder', price_guests: 'Preis für Gäste', registration_close: 'Anmeldeschluss', save: 'Speichern',
        lock: 'Sperren', unlock: 'Entsperren', lock_confirm: 'Termin sperren?', unlock_confirm: 'Termin entsperren?',
        delete: 'Löschen', delete_confirm_short: 'Termin definitiv löschen?', export: 'Exportieren', participants: 'Teilnehmer',
        waitlist: 'Warteliste', male: 'Herren', female: 'Damen', comment: 'Kommentar', unregister: 'Abmelden',
        unregister_user_confirm: ':name abmelden?', register: 'Anmelden', comment_update_success: 'Kommentar aktualisiert',
        update_success: 'Anlass aktualisiert', delete_success: 'Anlass gelöscht', registration_success: 'Angemeldet',
        unregistration_success: 'Abgemeldet', lock_success: 'Gesperrt', unlock_success: 'Entsperrt', not_found: 'Anlass nicht gefunden',
      },
      auth: { not_registered: 'Nicht angemeldet', register_user: ':username anmelden?', register_user_waitlist: ':username auf die Warteliste setzen?' },
      guests: { title: 'Gäste', add: 'Gast hinzufügen', edit: 'Gast bearbeiten', delete_confirm: 'Gast definitiv löschen?', delete_success: 'Gast gelöscht' },
      social_events: { edit: 'Gesellschaftlichen Anlass bearbeiten', new: 'Neuer gesellschaftlicher Anlass' },
      navigation: { home: 'Startseite' },
    });
    navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
  });

  async function render() {
    const fixture = TestBed.createComponent(EventAdminPage);
    fixture.componentRef.setInput('id', '3');
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;
    const group = (title: string) =>
      Array.from(el.querySelectorAll<HTMLElement>('[data-group]')).find((g) => g.querySelector('h2, h3')?.textContent?.trim() === title);
    const row = (name: string) =>
      Array.from(el.querySelectorAll<HTMLElement>('[data-row]')).find((r) => r.textContent?.includes(name))!;
    const buttonIn = (root: ParentNode, text: string) =>
      Array.from(root.querySelectorAll('button')).find((b) => b.textContent?.trim() === text) as HTMLButtonElement;
    const click = async (button: HTMLElement) => {
      button.click();
      await fixture.whenStable();
    };
    const type = (input: HTMLInputElement, value: string) => {
      input.value = value;
      input.dispatchEvent(new Event('input'));
    };
    return { fixture, el, group, row, buttonIn, click, type };
  }

  it('separate genders: four groups with the right members, registered-at and the event data', async () => {
    const { el, group } = await render();

    expect(api.get).toHaveBeenCalledWith('/events/3/admin', expect.anything());
    expect(el.querySelector('h1')?.textContent).toContain('Frühlingscup');
    const names = (title: string) => Array.from(group(title)!.querySelectorAll('[data-row]')).map((r) => r.querySelector('[data-name]')?.textContent?.trim());
    expect(names('Herren')).toEqual(['Anna']);
    expect(names('Damen')).toEqual(['Berta']);
    expect(names('Herren – Warteliste')).toEqual(['Carl']);
    expect(names('Damen – Warteliste')).toEqual(['Dora']);
    expect(group('Teilnehmer')).toBeUndefined();
    expect(group('Herren')!.textContent).toContain(formatDateTime('2099-05-01T10:30:00', 'de'));
    expect(el.querySelector<HTMLInputElement>('#name')!.value).toBe('Frühlingscup');
  });

  it('mixed: two groups, participants and waitlist', async () => {
    api.get.mockResolvedValue(dto({ mixed: true }));
    const { el, group } = await render();

    expect(group('Teilnehmer')!.querySelectorAll('[data-row]')).toHaveLength(2);
    expect(group('Warteliste')!.querySelectorAll('[data-row]')).toHaveLength(2);
    expect(group('Herren')).toBeUndefined();
    expect(el.querySelectorAll('[data-guest-group]')).toHaveLength(1);
  });

  it('registers another user with a comment after confirming, and shows the returned DTO', async () => {
    api.post.mockResolvedValue(dto({ availableUsers: [], registrations: [reg(20, 'Egon', true, 'registered', 'hi')] }));
    const { el, row, buttonIn, click, type } = await render();

    expect(el.textContent).toContain('Nicht angemeldet');
    type(row('Egon').querySelector('input')!, 'hi');
    await click(buttonIn(row('Egon'), 'Anmelden'));

    expect(confirm.ask).toHaveBeenCalledWith('Egon anmelden?');
    expect(api.post).toHaveBeenCalledWith('/events/3/registrations/20', { comment: 'hi' }, undefined);
    expect(notify.success).toHaveBeenCalledWith('events.registration_success');
    expect(el.textContent).not.toContain('Nicht angemeldet');
    expect(el.textContent).toContain('Egon');
  });

  it('asks about the waitlist when no spot is free', async () => {
    api.get.mockResolvedValue(dto({}, { available: 0 }));
    api.post.mockResolvedValue(dto());
    const { row, buttonIn, click } = await render();

    await click(buttonIn(row('Egon'), 'Anmelden'));

    expect(confirm.ask).toHaveBeenCalledWith('Egon auf die Warteliste setzen?');
  });

  it('does not register when the confirmation is cancelled', async () => {
    confirm.ask.mockResolvedValue(false);
    const { row, buttonIn, click } = await render();

    await click(buttonIn(row('Egon'), 'Anmelden'));

    expect(api.post).not.toHaveBeenCalled();
  });

  it('unregisters after confirming with the name', async () => {
    api.delete.mockResolvedValue(dto({ registrations: [] }));
    const { el, row, buttonIn, click } = await render();

    await click(buttonIn(row('Anna'), 'Abmelden'));

    expect(confirm.ask).toHaveBeenCalledWith('Anna abmelden?', { danger: true });
    expect(api.delete).toHaveBeenCalledWith('/events/3/registrations/11', undefined);
    expect(el.querySelector('[data-row]')?.textContent).not.toContain('Anna');
  });

  it('saves a registration comment without confirming', async () => {
    api.patch.mockResolvedValue(dto());
    const { row, buttonIn, click, type } = await render();

    const input = row('Anna').querySelector('input')!;
    expect(input.value).toBe('c1');
    type(input, 'neu');
    await click(buttonIn(row('Anna'), 'Speichern'));

    expect(confirm.ask).not.toHaveBeenCalled();
    expect(api.patch).toHaveBeenCalledWith('/events/3/registrations/11', { comment: 'neu' }, undefined);
    expect(notify.success).toHaveBeenCalledWith('events.comment_update_success');
  });

  it('lock confirms, calls the API and re-renders from the returned DTO; unlock does the reverse', async () => {
    api.put.mockResolvedValue(dto({}, { isLocked: true }));
    api.delete.mockResolvedValue(dto());
    const { el, buttonIn, click } = await render();

    await click(buttonIn(el, 'Sperren'));
    expect(confirm.ask).toHaveBeenCalledWith('Termin sperren?');
    expect(api.put).toHaveBeenCalledWith('/events/3/lock', undefined, undefined);
    expect(buttonIn(el, 'Sperren')).toBeUndefined();

    await click(buttonIn(el, 'Entsperren'));
    expect(confirm.ask).toHaveBeenLastCalledWith('Termin entsperren?');
    expect(api.delete).toHaveBeenCalledWith('/events/3/lock', undefined);
    expect(buttonIn(el, 'Sperren')).toBeDefined();
  });

  it('deletes the event after a danger confirmation and goes home', async () => {
    api.delete.mockResolvedValue(undefined);
    const { el, buttonIn, click } = await render();

    await click(buttonIn(el, 'Löschen'));

    expect(confirm.ask).toHaveBeenCalledWith('Termin definitiv löschen?', { danger: true });
    expect(api.delete).toHaveBeenCalledWith('/events/3', undefined);
    expect(navigate).toHaveBeenCalledWith('/');
  });

  it('export is a plain download link', async () => {
    const { el } = await render();

    const link = el.querySelector<HTMLAnchorElement>('a[download]')!;
    expect(link.getAttribute('href')).toBe('/api/events/3/export');
    expect(link.textContent).toContain('Exportieren');
    expect(api.get).toHaveBeenCalledTimes(1);
  });

  it('saves the event form with the update body', async () => {
    api.put.mockResolvedValue(dto());
    const { fixture, el, type } = await render();

    type(el.querySelector<HTMLInputElement>('#name')!, 'Neuer Name');
    el.querySelector('app-event-form form')!.dispatchEvent(new Event('submit', { cancelable: true }));
    await fixture.whenStable();

    expect(api.put).toHaveBeenCalledWith(
      '/events/3',
      { name: 'Neuer Name', capacity: 10, mixed: false, description: 'Beschreibung X', priceMembers: 'CHF 50', priceGuests: null, registrationClose: '2099-05-30T12:00' },
      undefined,
    );
    expect(notify.success).toHaveBeenCalledWith('events.update_success');
  });

  it('shows 422 errors of the event form', async () => {
    api.put.mockRejectedValue(new ApiError(422, 'validation.failed', { name: { key: 'validation.required' } }));
    const { fixture, el } = await render();

    el.querySelector('app-event-form form')!.dispatchEvent(new Event('submit', { cancelable: true }));
    await fixture.whenStable();

    expect(el.querySelector('#name-error')?.textContent).toContain('Das Feld Name ist erforderlich.');
  });

  it('lists guests split by gender with edit link, add link and delete', async () => {
    api.delete.mockResolvedValue(undefined);
    const { fixture, el, buttonIn, click } = await render();

    const gus = Array.from(el.querySelectorAll<HTMLElement>('[data-guest]')).find((g) => g.textContent?.includes('Gus'))!;
    expect(gus.textContent).toContain('gus@example.com');
    expect(gus.textContent).toContain('12.4');
    expect(gus.textContent).toContain('Gast-Notiz');
    expect(gus.querySelector('a')?.getAttribute('href')).toBe('/events/3/guests/5/edit');
    expect(el.querySelector('a[href="/events/3/guests/new"]')?.textContent).toContain('Gast hinzufügen');
    expect(el.querySelectorAll('[data-guest-group]')).toHaveLength(2);

    await click(buttonIn(gus, 'Löschen'));
    await fixture.whenStable();

    expect(confirm.ask).toHaveBeenCalledWith('Gast definitiv löschen?', { danger: true });
    expect(api.delete).toHaveBeenCalledWith('/events/3/guests/5', undefined);
    expect(notify.success).toHaveBeenCalledWith('guests.delete_success');
    expect(api.get).toHaveBeenCalledTimes(2);
  });

  it('links to the social event or offers to create one', async () => {
    const { el } = await render();
    expect(el.querySelector('a[href="/social-events/new?tournamentId=3"]')?.textContent).toContain('Neuer gesellschaftlicher Anlass');

  });

  it('links to the linked social event administration', async () => {
    api.get.mockResolvedValue(dto({
      socialEvent: { id: 9, tournamentId: 3, name: 'Abendessen', date: '2099-06-01', isLocked: false, totalCapacity: 20, registered: 5, available: 15, userRegistered: false },
    }));
    const { el } = await render();

    const link = el.querySelector('a[href="/social-events/9/admin"]');
    expect(link?.textContent).toContain('Abendessen');
    expect(link?.textContent).toContain('Gesellschaftlichen Anlass bearbeiten');
    expect(el.querySelector('a[href^="/social-events/new"]')).toBeNull();
  });

  it('shows the not-found message on 404', async () => {
    api.get.mockRejectedValue(new ApiError(404, 'events.not_found'));
    const { el } = await render();

    expect(el.textContent).toContain('Anlass nicht gefunden');
  });
});
