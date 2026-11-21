import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ApiClient } from '../../../core/api/api-client';
import { EventSummaryDto } from '../../../core/api/generated';
import { ConfirmService } from '../../../core/feedback/confirm.service';
import { Notify } from '../../../core/feedback/notify';
import { I18n } from '../../../core/i18n/i18n';
import { EventListPage } from './event-list.page';

function ev(id: number, name: string, over: Partial<EventSummaryDto> = {}): EventSummaryDto {
  return { id, name, date: '2099-06-05', capacity: 20, joined: 12, waitlist: 3, available: 8, isLocked: false, userState: 'none', ...over };
}

describe('EventListPage', () => {
  let api: { get: ReturnType<typeof vi.fn>; delete: ReturnType<typeof vi.fn> };
  let confirm: { ask: ReturnType<typeof vi.fn> };
  let notify: { success: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    api = { get: vi.fn().mockResolvedValue([ev(1, 'Frühlingscup'), ev(2, 'Herbstcup', { date: '2099-09-01', joined: 5, waitlist: 0 })]), delete: vi.fn().mockResolvedValue(undefined) };
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
      events: {
        title: 'Anlässe', date: 'Datum', name: 'Name', max_participants: 'Max. Teilnehmer', registered: 'Angemeldet',
        waitlist: 'Warteliste', add: 'Anlass hinzufügen', bulk_create: 'Mehrfache Termine erstellen', delete: 'Löschen',
        delete_confirm: 'Termin :name definitiv löschen?', delete_success: 'Anlass gelöscht',
      },
    });
  });

  async function render() {
    const fixture = TestBed.createComponent(EventListPage);
    await fixture.whenStable();
    return { fixture, el: fixture.nativeElement as HTMLElement };
  }

  it('lists the events with capacity, registered and waitlist counts', async () => {
    const { el } = await render();

    expect(api.get).toHaveBeenCalledWith('/events', expect.anything());
    const rows = Array.from(el.querySelectorAll('tbody tr'));
    expect(rows).toHaveLength(2);
    expect(rows[0].textContent).toContain('Frühlingscup');
    expect(rows[0].textContent).toContain('20');
    expect(rows[0].textContent).toContain('12');
    expect(rows[0].textContent).toContain('3');
    expect(rows[1].textContent).toContain('Herbstcup');
    for (const header of ['Datum', 'Name', 'Max. Teilnehmer', 'Angemeldet', 'Warteliste']) {
      expect(el.querySelector('thead')?.textContent).toContain(header);
    }
  });

  it('links to create and bulk create', async () => {
    const { el } = await render();

    expect(el.querySelector('a[href="/events/new"]')?.textContent).toContain('Anlass hinzufügen');
    expect(el.querySelector('a[href="/events/bulk/new"]')?.textContent).toContain('Mehrfache Termine erstellen');
  });

  it('a row click opens the event administration', async () => {
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
    const { el } = await render();

    (el.querySelectorAll('tbody tr')[1] as HTMLElement).click();

    expect(navigate).toHaveBeenCalledWith(['/events', 2, 'admin']);
  });

  it('delete confirms with the name, calls the API and reloads', async () => {
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
    const { fixture, el } = await render();

    el.querySelectorAll<HTMLButtonElement>('tbody button')[0].click();
    await fixture.whenStable();

    expect(confirm.ask).toHaveBeenCalledWith('Termin Frühlingscup definitiv löschen?', { danger: true });
    expect(api.delete).toHaveBeenCalledWith('/events/1', undefined);
    expect(notify.success).toHaveBeenCalledWith('events.delete_success');
    expect(api.get).toHaveBeenCalledTimes(2);
    expect(navigate).not.toHaveBeenCalled(); // the click must not bubble to the row
  });

  it('does nothing when the confirmation is cancelled', async () => {
    confirm.ask.mockResolvedValue(false);
    const { fixture, el } = await render();

    el.querySelectorAll<HTMLButtonElement>('tbody button')[0].click();
    await fixture.whenStable();

    expect(api.delete).not.toHaveBeenCalled();
  });
});
