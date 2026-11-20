import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ApiClient } from '../../core/api/api-client';
import { CalendarDto, EventSummaryDto, SocialEventSummaryDto } from '../../core/api/generated';
import { SessionStore } from '../../core/auth/session.store';
import { I18n } from '../../core/i18n/i18n';
import { RegistrationFlow } from '../events/registration-flow';
import { CalendarPage } from './calendar.page';

function ev(id: number, name: string, over: Partial<EventSummaryDto> = {}): EventSummaryDto {
  return {
    id, name, date: '2099-06-05', capacity: 10, joined: 3, waitlist: 0, available: 5, isLocked: false,
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
  let api: { get: ReturnType<typeof vi.fn> };
  let flow: { register: ReturnType<typeof vi.fn> };
  let navigate: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    loggedIn.set(false);
    api = { get: vi.fn().mockResolvedValue(calendar()) };
    flow = { register: vi.fn().mockResolvedValue(null) };
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: ApiClient, useValue: api },
        { provide: RegistrationFlow, useValue: flow },
        { provide: SessionStore, useValue: { loggedIn } },
      ],
    });
    TestBed.inject(I18n).use('de', {
      calendar: { previous_month: 'Vorheriger Monat', next_month: 'Nächster Monat' },
      events: {
        registered: 'Angemeldet', on_waitlist: 'Auf Warteliste', locked: 'Gesperrt',
        waitlist_available: 'Warteliste verfügbar', spot_available: ':count Platz frei',
        spots_available: ':count Plätze frei', register: 'Anmelden', waitlist: 'Warteliste',
      },
      social_events: { registered: 'Angemeldet', full: 'Ausgebucht', available: ':count Plätze verfügbar' },
      errors: { general: 'Fehler' },
    });
    navigate = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
  });

  async function render(inputs: { month?: string; date?: string } = { month: '2099-06' }) {
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
    expect(navigate).toHaveBeenLastCalledWith([], { queryParams: { month: '2099-07' } });

    el.querySelector<HTMLButtonElement>('[aria-label="Vorheriger Monat"]')!.click();
    expect(navigate).toHaveBeenLastCalledWith([], { queryParams: { month: '2099-05' } });
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
    const calls = api.get.mock.calls.length;

    card('Offen').querySelector<HTMLButtonElement>('button')!.click();
    await fixture.whenStable();

    expect(flow.register).toHaveBeenCalledWith(3, '', { available: 5, isLocked: false });
    expect(api.get.mock.calls.length).toBe(calls + 1);
    // registered / locked tournaments offer no quick register
    expect(card('Registriert').querySelector('button')).toBeNull();
    expect(card('Gesperrt').querySelector('button')).toBeNull();
    expect(card('Voll').querySelector('button')?.textContent?.trim()).toBe('Warteliste');
  });

  it('shows the translated API error', async () => {
    const { ApiError } = await import('../../core/api/api-client');
    api.get.mockRejectedValue(new ApiError(500, 'errors.general'));
    const { el } = await render();

    expect(el.textContent).toContain('Fehler');
  });
});
