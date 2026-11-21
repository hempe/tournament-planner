import { computed, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { ApiClient, ApiError } from '../../core/api/api-client';
import { EventDetailDto } from '../../core/api/generated';
import { SessionStore } from '../../core/auth/session.store';
import { I18n } from '../../core/i18n/i18n';
import { EventDetailPage } from './event-detail.page';
import { RegistrationFlow } from './registration-flow';

function detail(over: Partial<EventDetailDto> = {}, eventOver: Partial<EventDetailDto['event']> = {}): EventDetailDto {
  return {
    event: {
      id: 3, name: 'Frühlingscup', date: '2099-06-01', capacity: 10, joined: 2, waitlist: 1,
      available: 8, isLocked: false, userState: 'none', ...eventOver,
    },
    description: 'Zeile 1\nZeile 2',
    priceMembers: 'CHF 50',
    priceGuests: 'CHF 80',
    registrationClose: '2099-05-30T12:00:00',
    myRegistration: null,
    participants: [
      { name: 'Anna', state: 'registered' },
      { name: 'Ben', state: 'waitlist' },
    ],
    guests: [{ firstName: 'Gus', lastName: 'Guest' }],
    socialEvent: null,
    ...over,
  };
}

describe('EventDetailPage', () => {
  const isAdmin = signal(false);
  let api: { get: ReturnType<typeof vi.fn> };
  let flow: { register: ReturnType<typeof vi.fn>; unregister: ReturnType<typeof vi.fn>; updateComment: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    isAdmin.set(false);
    api = { get: vi.fn().mockResolvedValue(detail()) };
    flow = { register: vi.fn().mockResolvedValue(null), unregister: vi.fn().mockResolvedValue(null), updateComment: vi.fn().mockResolvedValue(null) };
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: ApiClient, useValue: api },
        { provide: RegistrationFlow, useValue: flow },
        {
          provide: SessionStore,
          useValue: { isAdmin: computed(() => isAdmin()), loggedIn: signal(true), user: signal({ displayName: 'Ben' }) },
        },
      ],
    });
    TestBed.inject(I18n).use('de', {
      nav: { back: 'Zurück' },
      events: {
        registered: 'Angemeldet', on_waitlist: 'Auf Warteliste', waitlist: 'Warteliste', register: 'Anmelden',
        unregister: 'Turnier abmelden', save: 'Speichern', edit: 'Bearbeiten', comment: 'Kommentar',
        description: 'Beschreibung', price_members: 'Preis für Mitglieder', price_guests: 'Preis für Gäste',
        registration_close: 'Anmeldeschluss', locked_message: 'Anmeldung geschlossen', not_found: 'Anlass nicht gefunden',
        participants: 'Teilnehmer', your_registration: 'Deine Anmeldung', no_participants: 'Noch keine Anmeldungen.',
      },
      navigation: { home: 'Startseite' },
      guests: { title: 'Gäste' },
      social_events: { registered: 'Angemeldet', full: 'Ausgebucht', available: '1 Platz verfügbar|:count Plätze verfügbar' },
    });
  });

  async function render() {
    const fixture = TestBed.createComponent(EventDetailPage);
    fixture.componentRef.setInput('id', '3');
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;
    const button = (text: string) =>
      Array.from(el.querySelectorAll('button')).find((b) => b.textContent?.trim() === text) as HTMLButtonElement | undefined;
    return { fixture, el, button };
  }

  it('renders name, prices as label/value pairs and the back link to the month', async () => {
    const { el } = await render();

    expect(api.get).toHaveBeenCalledWith('/events/3', expect.anything());
    expect(el.querySelector('h1')?.textContent).toContain('Frühlingscup');
    const pairs = Array.from(el.querySelectorAll('dl > div')).map((d) => [d.querySelector('dt')?.textContent?.trim(), d.querySelector('dd')?.textContent?.trim()]);
    expect(pairs).toContainEqual(['Preis für Mitglieder', 'CHF 50']);
    expect(pairs).toContainEqual(['Preis für Gäste', 'CHF 80']);
    expect(el.querySelector('[data-back-link]')?.getAttribute('href')).toBe('/?month=2099-06');
  });

  it('lists registered, waitlist and guests as sub-sections with counts and highlights the own entry', async () => {
    const { el } = await render();
    const groups = Array.from(el.querySelectorAll<HTMLElement>('[data-participant-group]'));

    expect(groups.map((g) => g.querySelector('h3')?.textContent?.trim())).toEqual(['Angemeldet (1)', 'Warteliste (1)', 'Gäste (1)']);
    expect(groups[0].querySelector('ul')?.textContent).toContain('Anna');
    expect(groups[1].querySelector('ul')?.textContent).toContain('Ben');
    expect(groups[2].querySelector('ul')?.textContent).toContain('Gus Guest');
    expect(Array.from(el.querySelectorAll('[data-own]')).map((li) => li.textContent?.trim())).toEqual(['Ben']);
  });

  it('shows an empty state without participants', async () => {
    api.get.mockResolvedValue(detail({ participants: [], guests: [] }));
    const { el } = await render();

    expect(el.querySelectorAll('[data-participant-group]').length).toBe(0);
    expect(el.textContent).toContain('Noch keine Anmeldungen.');
  });

  it('not registered: register button passes the typed comment', async () => {
    const { fixture, el, button } = await render();
    const input = el.querySelector<HTMLInputElement>('input')!;
    input.value = 'Bin dabei';
    input.dispatchEvent(new Event('input'));

    button('Anmelden')!.click();
    await fixture.whenStable();

    expect(flow.register).toHaveBeenCalledWith(3, 'Bin dabei', { available: 8, isLocked: false });
    expect(button('Turnier abmelden')).toBeUndefined();
    expect(el.querySelector('[data-registration] h2')).toBeNull();
  });

  it('shows the social event status with the plural form of the free spots', async () => {
    const social = { id: 9, tournamentId: 3, name: 'Dinner', date: '2099-06-01', isLocked: false, totalCapacity: 10, registered: 9, available: 1, userRegistered: false };
    api.get.mockResolvedValue(detail({ socialEvent: social }));
    const { el } = await render();

    expect(el.querySelector('[data-social-row] [data-status]')?.textContent?.trim()).toBe('1 Platz verfügbar');
  });

  it('shows the waitlist button when the event is full', async () => {
    api.get.mockResolvedValue(detail({}, { available: 0 }));
    const { button } = await render();

    expect(button('Warteliste')).toBeDefined();
    expect(button('Anmelden')).toBeUndefined();
  });

  it('registered: save and unregister; unregister passes the social event; the page shows the returned detail', async () => {
    const social = { id: 9, tournamentId: 3, name: 'Dinner', date: '2099-06-01', isLocked: false, totalCapacity: null, registered: 0, available: null, userRegistered: true };
    api.get.mockResolvedValue(
      detail({ myRegistration: { state: 'registered', comment: 'alt' }, socialEvent: social }, { userState: 'registered' }),
    );
    const { fixture, el, button } = await render();

    expect(el.querySelector<HTMLInputElement>('input')!.value).toBe('alt');
    expect(button('Speichern')!.disabled).toBe(true);
    expect(el.querySelector('[data-registration] h2')?.textContent).toContain('Deine Anmeldung');
    const socialRow = el.querySelector('a[href="/social-events/9"]');
    expect(socialRow?.textContent).toContain('Dinner');
    expect(socialRow?.querySelector('[data-status]')?.textContent?.trim()).toBe('Angemeldet');

    flow.unregister.mockResolvedValue(detail({ participants: [] }, { name: 'Neu' }));
    button('Turnier abmelden')!.click();
    await fixture.whenStable();

    expect(flow.unregister).toHaveBeenCalledWith(3, social);
    expect(el.textContent).toContain('Neu');
    expect(button('Anmelden')).toBeDefined();
  });

  it('registered: changing the comment enables save and calls updateComment', async () => {
    api.get.mockResolvedValue(detail({ myRegistration: { state: 'registered', comment: 'alt' } }, { userState: 'registered' }));
    const { fixture, el, button } = await render();
    const input = el.querySelector<HTMLInputElement>('input')!;
    input.value = 'neu';
    input.dispatchEvent(new Event('input'));
    await fixture.whenStable();

    button('Speichern')!.click();
    await fixture.whenStable();

    expect(flow.updateComment).toHaveBeenCalledWith(3, 'neu');
  });

  it('locked: shows the message and no register button', async () => {
    api.get.mockResolvedValue(detail({}, { isLocked: true }));
    const { el, button } = await render();

    expect(el.textContent).toContain('Anmeldung geschlossen');
    expect(button('Anmelden')).toBeUndefined();
  });

  it('locked and registered: input and buttons are disabled', async () => {
    api.get.mockResolvedValue(detail({ myRegistration: { state: 'registered', comment: 'x' } }, { isLocked: true, userState: 'registered' }));
    const { el, button } = await render();

    expect(el.textContent).toContain('Anmeldung geschlossen');
    expect(el.querySelector<HTMLInputElement>('input')!.disabled).toBe(true);
    expect(button('Turnier abmelden')!.disabled).toBe(true);
    expect(button('Speichern')!.disabled).toBe(true);
  });

  it('admins see the edit link, members do not', async () => {
    let { el } = await render();
    expect(el.querySelector('a[href="/events/3/admin"]')).toBeNull();

    isAdmin.set(true);
    ({ el } = await render());
    expect(el.querySelector('a[href="/events/3/admin"]')).not.toBeNull();
  });

  it('shows the not-found message with a link home on 404', async () => {
    api.get.mockRejectedValue(new ApiError(404, 'events.not_found'));
    const { el } = await render();

    expect(el.textContent).toContain('Anlass nicht gefunden');
    expect(el.querySelector('a[href="/"]')).not.toBeNull();
  });
});
