import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ApiClient } from '../../core/api/api-client';
import { CalendarDto, EventSummaryDto, SocialEventSummaryDto } from '../../core/api/generated';
import { SessionStore } from '../../core/auth/session.store';
import { I18n } from '../../core/i18n/i18n';
import { RegistrationFlow } from '../events/registration-flow';
import { CalendarPage } from './calendar.page';
import { monthOf } from './month';
import { amigosFlavor, provideTestFlavor, setTestFlavor } from '../../core/flavor/testing';

function ev(id: number, name: string, over: Partial<EventSummaryDto> = {}): EventSummaryDto {
  return {
    id, name, date: '2099-06-05', capacity: 10, joined: 3, waitlist: 0, available: 5, isLocked: false, manuallyLocked: false,
    userState: 'none', ...over,
  };
}

const social: SocialEventSummaryDto = {
  id: 9, tournamentId: 1, name: 'Abendessen', date: '2099-06-05', isLocked: false, totalCapacity: 20,
  registered: 5, available: 15, userRegistered: false,
};

function calendar(over: Partial<CalendarDto> = {}): CalendarDto {
  return {
    month: '2099-06',
    events: [
      ev(1, 'Registriert', { userState: 'registered' }),
      ev(2, 'Wartend', { userState: 'waitlist', available: 0 }),
      ev(3, 'Offen', { available: 5 }),
      ev(4, 'Voll', { available: 0 }),
      ev(5, 'Gesperrt', { isLocked: true }),
      ev(6, 'Letzter', { available: 1, date: '2099-06-20' }),
    ],
    socialEvents: [social],
    ...over,
  };
}

describe('CalendarPage', () => {
  const loggedIn = signal(false);
  const isAdmin = signal(false);
  let api: { get: ReturnType<typeof vi.fn> };
  let flow: { register: ReturnType<typeof vi.fn> };
  let navigate: ReturnType<typeof vi.spyOn>;

  afterEach(() => setTestFlavor(null));

  beforeEach(() => {
    loggedIn.set(false);
    isAdmin.set(false);
    localStorage.clear();
    // the page loads the shown month; logged-in users also load this and next month for "Meine nächsten Anmeldungen"
    api = {
      get: vi.fn().mockImplementation(async (path: string) =>
        path === '/calendar/2099-06' ? calendar() : { month: path.slice(-7), events: [], socialEvents: [] },
      ),
    };
    flow = { register: vi.fn().mockResolvedValue(null) };
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        provideTestFlavor(),
        { provide: ApiClient, useValue: api },
        { provide: RegistrationFlow, useValue: flow },
        { provide: SessionStore, useValue: { loggedIn, isAdmin } },
      ],
    });
    TestBed.inject(I18n).use('de', {
      calendar: {
        previous_month: 'Vorheriger Monat', next_month: 'Nächster Monat', no_events: 'Keine Anlässe in diesem Monat.',
        view_list: 'Liste', view_month: 'Monat', my_registrations: 'Meine nächsten Anmeldungen',
        no_registrations: 'Keine bevorstehenden Anmeldungen.', legend_registered: 'Angemeldet', legend_open: 'Anmeldung offen',
        legend_closed: 'Geschlossen', legend_social: 'Gesellschaftlicher Anlass', add_event_on: 'Anlass am :date hinzufügen',
        participants: ':joined von :capacity',
      },
      events: {
        registered: 'Angemeldet', on_waitlist: 'Auf Warteliste', locked: 'Gesperrt',
        waitlist_available: 'Warteliste verfügbar', spot_available: ':count Platz frei',
        spots_available: ':count Plätze frei', register: 'Anmelden', waitlist: 'Warteliste', new_for_date: 'Neuer Anlass: :date',
      },
      social_events: { registered: 'Angemeldet', full: 'Ausgebucht', available: '1 Platz verfügbar|:count Plätze verfügbar' },
      errors: { general: 'Fehler' },
    });
    navigate = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
  });

  async function render(inputs: { month?: string; date?: string; view?: string } = { month: '2099-06' }) {
    const fixture = TestBed.createComponent(CalendarPage);
    for (const [key, value] of Object.entries(inputs)) {
      fixture.componentRef.setInput(key, value);
    }
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;
    const card = (name: string) =>
      Array.from(el.querySelectorAll<HTMLElement>('[data-card]')).find((c) => c.textContent?.includes(name))!;
    return { fixture, el, card };
  }

  it('renders the month title and the event names', async () => {
    const { el } = await render();

    expect(api.get).toHaveBeenCalledWith('/calendar/2099-06', expect.anything());
    expect(el.textContent).toContain('Juni 2099');
    for (const name of ['Registriert', 'Wartend', 'Offen', 'Voll', 'Gesperrt', 'Letzter']) {
      expect(el.textContent).toContain(name);
    }
  });

  it('maps the legacy date parameter to its month', async () => {
    await render({ date: '2099-6-1' });

    expect(api.get).toHaveBeenCalledWith('/calendar/2099-06', expect.anything());
  });

  it('navigates to the next and previous month', async () => {
    const { fixture, el } = await render();

    el.querySelector<HTMLButtonElement>('[aria-label="Nächster Monat"]')!.click();
    expect(navigate).toHaveBeenLastCalledWith([], { queryParams: { month: '2099-07', date: null }, queryParamsHandling: 'merge' });

    el.querySelector<HTMLButtonElement>('[aria-label="Vorheriger Monat"]')!.click();
    expect(navigate).toHaveBeenLastCalledWith([], { queryParams: { month: '2099-05', date: null }, queryParamsHandling: 'merge' });
    await fixture.whenStable();
  });

  it('anonymous: hides social events; open tournaments link to the guest form; full and locked ones are not links', async () => {
    const { el, card } = await render();

    expect(el.textContent).not.toContain('Abendessen');
    expect(card('Offen').querySelector('a')?.getAttribute('href')).toBe('/events/3/guests/new');
    expect(card('Voll').querySelector('a')).toBeNull();
    expect(card('Gesperrt').querySelector('a')).toBeNull();
    expect(card('Offen').querySelector('button')).toBeNull();
  });

  it('member: links to the detail page and shows the status text', async () => {
    loggedIn.set(true);
    const { el, card } = await render();

    expect(card('Offen').querySelector('a')?.getAttribute('href')).toBe('/events/3');
    expect(card('Voll').querySelector('a')?.getAttribute('href')).toBe('/events/4');
    expect(card('Registriert').textContent).toContain('Angemeldet');
    expect(card('Wartend').textContent).toContain('Auf Warteliste');
    expect(card('Offen').textContent).toContain('5 Plätze frei');
    expect(card('Letzter').textContent).toContain('1 Platz frei');
    expect(card('Voll').textContent).toContain('Warteliste verfügbar');
    expect(card('Gesperrt').textContent).toContain('Gesperrt');
    expect(el.textContent).toContain('Abendessen');
    expect(card('Abendessen').querySelector('a')?.getAttribute('href')).toBe('/social-events/9');
    expect(card('Abendessen').textContent).toContain('15 Plätze verfügbar');
  });

  it('phone list: shows an empty state for a month without events', async () => {
    api.get.mockResolvedValue(calendar({ events: [], socialEvents: [] }));
    const { el } = await render();

    expect(el.querySelector('[data-empty]')?.textContent).toContain('Keine Anlässe in diesem Monat.');
  });

  it('member: one free dinner spot uses the singular', async () => {
    loggedIn.set(true);
    api.get.mockResolvedValue(calendar({ socialEvents: [{ ...social, available: 1 }] }));
    const { card } = await render();

    expect(card('Abendessen').textContent).toContain('1 Platz verfügbar');
    expect(card('Abendessen').textContent).not.toContain('Plätze');
  });

  it('member: past events count as locked', async () => {
    loggedIn.set(true);
    api.get.mockResolvedValue(calendar({ month: '2000-01', events: [ev(7, 'Alt', { date: '2000-01-10' })], socialEvents: [] }));
    const { card } = await render({ month: '2000-01' });

    expect(card('Alt').textContent).toContain('Gesperrt');
    expect(card('Alt').querySelector('button')).toBeNull();
  });

  it('member: quick register calls the flow and reloads the calendar', async () => {
    loggedIn.set(true);
    flow.register.mockResolvedValue({});
    const { fixture, card } = await render();
    const monthCalls = () => api.get.mock.calls.filter(([path]) => path === '/calendar/2099-06').length;
    const calls = monthCalls();

    card('Offen').querySelector<HTMLButtonElement>('button')!.click();
    await fixture.whenStable();

    expect(flow.register).toHaveBeenCalledWith(3, '', { available: 5, isLocked: false });
    expect(monthCalls()).toBe(calls + 1);
    // registered / locked tournaments offer no quick register
    expect(card('Registriert').querySelector('button')).toBeNull();
    expect(card('Gesperrt').querySelector('button')).toBeNull();
    expect(card('Voll').querySelector('button')?.getAttribute('aria-label')).toBe('Warteliste');
    // colours of the previous site: light = registered, dark = open, grey = not open
    expect(card('Registriert').getAttribute('data-state')).toBe('registered');
    expect(card('Offen').getAttribute('data-state')).toBe('open');
    expect(card('Offen').className).toContain('bg-primary');
    expect(card('Gesperrt').getAttribute('data-state')).toBe('closed');
    expect(card('Gesperrt').className).toContain('bg-muted');
  });

  it('admin, month view: every day cell gets a plus link to the create page with the date prefilled', async () => {
    isAdmin.set(true);
    loggedIn.set(true);
    const { el } = await render();

    const link = el.querySelector('a[href="/events/new?date=2099-06-15"]');
    expect(link?.getAttribute('aria-label')).toMatch(/^Neuer Anlass: .*15/);
    expect(el.querySelectorAll('a[href^="/events/new?date=2099-06-"]')).toHaveLength(30);
  });

  it('non-admin members get no plus links', async () => {
    loggedIn.set(true);
    const { el } = await render();

    expect(el.querySelector('a[href^="/events/new"]')).toBeNull();
  });

  it('shows the translated API error', async () => {
    const { ApiError } = await import('../../core/api/api-client');
    api.get.mockRejectedValue(new ApiError(500, 'errors.general'));
    const { el } = await render();

    expect(el.textContent).toContain('Fehler');
  });

  describe('desktop views', () => {
    it('defaults to the month view', async () => {
      const { el } = await render();

      expect(el.querySelector('[data-month-grid]')).not.toBeNull();
      expect(el.querySelector('[data-agenda]')).toBeNull();
    });

    it('?view=list shows the list with sidebar; month navigation keeps the parameter', async () => {
      const { el } = await render({ month: '2099-06', view: 'list' });

      expect(el.querySelector('[data-agenda]')).not.toBeNull();
      expect(el.querySelector('[data-month-grid]')).toBeNull();
      expect(el.querySelector('[data-capacity]')?.textContent).toContain('3 von 10');
    });

    it('the switch puts the view into the URL (replacing the entry) and remembers it', async () => {
      const { el } = await render();

      el.querySelector<HTMLButtonElement>('[data-view="list"]')!.click();

      expect(navigate).toHaveBeenLastCalledWith([], { queryParams: { view: 'list' }, queryParamsHandling: 'merge', replaceUrl: true });
      expect(localStorage.getItem('tp.calendar-view')).toBe('list');
    });

    it('without a URL parameter the remembered choice is used; the URL wins over it', async () => {
      localStorage.setItem('tp.calendar-view', 'list');
      expect((await render()).el.querySelector('[data-agenda]')).not.toBeNull();

      TestBed.resetTestingModule();
      TestBed.configureTestingModule({
        providers: [provideRouter([]), { provide: ApiClient, useValue: api }, { provide: RegistrationFlow, useValue: flow }, { provide: SessionStore, useValue: { loggedIn, isAdmin } }],
      });
      expect((await render({ month: '2099-06', view: 'month' })).el.querySelector('[data-month-grid]')).not.toBeNull();
    });

    it('legend: guests see no "registered" hint (they cannot be registered), members do', async () => {
      const anonymous = await render({ month: '2099-06', view: 'list' });
      const legend = () => anonymous.el.querySelector('[data-legend]')!.textContent!;
      expect(legend()).toContain('Anmeldung offen');
      expect(legend()).toContain('Geschlossen');
      expect(legend()).not.toContain('Angemeldet');
      expect(legend()).not.toContain('Gesellschaftlicher Anlass');

      loggedIn.set(true);
      await anonymous.fixture.whenStable();
      expect(legend()).toContain('Angemeldet');
      expect(legend()).toContain('Gesellschaftlicher Anlass');
    });

    it('clicking a day in the mini month highlights that day in the list; admins get an add action for it', async () => {
      isAdmin.set(true);
      loggedIn.set(true);
      const { fixture, el } = await render({ month: '2099-06', view: 'list' });
      const miniDay = (n: number) => Array.from(el.querySelectorAll<HTMLButtonElement>('[data-mini-day]')).find((b) => b.textContent?.trim().startsWith(String(n)))!;

      miniDay(5).click();
      await fixture.whenStable();

      expect(el.querySelector('section[data-selected]')?.id).toBe('day-2099-06-05');
      expect(el.querySelector('[data-add-event]')?.getAttribute('href')).toBe('/events/new?date=2099-06-05');
    });

    it('members: no add action in the mini month', async () => {
      loggedIn.set(true);
      const { fixture, el } = await render({ month: '2099-06', view: 'list' });

      el.querySelector<HTMLButtonElement>('[data-mini-day]')!.click();
      await fixture.whenStable();

      expect(el.querySelector('[data-add-event]')).toBeNull();
    });

    it('"Meine nächsten Anmeldungen" lists upcoming registrations of this and next month', async () => {
      loggedIn.set(true);
      const thisMonth = monthOf(new Date());
      api.get.mockImplementation(async (path: string) => {
        if (path === '/calendar/2099-06') {
          return calendar();
        }
        if (path === `/calendar/${thisMonth}`) {
          return {
            month: thisMonth,
            events: [ev(11, 'Mein Turnier', { userState: 'registered', date: '2099-01-02' }), ev(12, 'Nicht meins', { date: '2099-01-03' })],
            socialEvents: [{ ...social, id: 13, name: 'Mein Essen', date: '2099-01-02', userRegistered: true }],
          };
        }
        return { month: path.slice(-7), events: [ev(14, 'Vorbei', { userState: 'registered', date: '2000-01-01' })], socialEvents: [] };
      });
      const { el } = await render({ month: '2099-06', view: 'list' });
      const box = el.querySelector('[data-my-registrations]')!;

      expect(box.textContent).toContain('Mein Turnier');
      expect(box.textContent).toContain('Mein Essen');
      expect(box.textContent).not.toContain('Nicht meins');
      expect(box.textContent).not.toContain('Vorbei');
      expect(box.querySelector('a')?.getAttribute('href')).toBe('/events/11');
    });

    it('guests get no "Meine nächsten Anmeldungen" box', async () => {
      const { el } = await render({ month: '2099-06', view: 'list' });

      expect(el.querySelector('[data-my-registrations]')).toBeNull();
    });
  });

  it('anonymous without guest registration (Amigos): open tournaments link to the event (login)', async () => {
    setTestFlavor(amigosFlavor);
    const { card } = await render();

    expect(card('Offen').querySelector('a')?.getAttribute('href')).toBe('/events/3');
  });
});
