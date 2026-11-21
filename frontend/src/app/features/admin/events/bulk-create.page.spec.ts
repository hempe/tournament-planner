import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { BulkEventDto } from '../../../core/api/generated';
import { Notify } from '../../../core/feedback/notify';
import { formatDate, formatDateTime } from '../../../core/format/dates';
import { I18n } from '../../../core/i18n/i18n';
import { BulkCreatePage } from './bulk-create.page';

function ev(date: string, over: Partial<BulkEventDto> = {}): BulkEventDto {
  return { name: 'Dienstagsrunde', date, capacity: 24, mixed: true, description: null, priceMembers: null, priceGuests: null, registrationClose: `${date}T12:00:00`, ...over };
}

describe('BulkCreatePage', () => {
  let api: Record<'get' | 'post', ReturnType<typeof vi.fn>>;
  let notify: { success: ReturnType<typeof vi.fn> };
  let navigate: ReturnType<typeof vi.spyOn>;
  const events = [ev('2099-06-02'), ev('2099-06-09', { description: 'Bearbeitet' })];

  beforeEach(() => {
    api = { get: vi.fn(), post: vi.fn().mockResolvedValue({ events }) };
    notify = { success: vi.fn() };
    TestBed.configureTestingModule({
      providers: [provideRouter([]), { provide: ApiClient, useValue: api }, { provide: Notify, useValue: notify }],
    });
    TestBed.inject(I18n).use('de', {
      nav: { back: 'Zurück' },
      validation: { required: 'Das Feld :field ist erforderlich.', date: 'Ungültiges Datum' },
      events: {
        bulk_create: 'Mehrfache Termine erstellen', bulk_start_date: 'Startdatum', bulk_end_date: 'Enddatum', bulk_day_of_week: 'Wochentag',
        bulk_select_day: 'Bitte wählen', bulk_preview: 'Vorschau anzeigen', bulk_preview_title: 'Vorschau: :count Termine werden erstellt',
        bulk_create_all: 'Alle erstellen', bulk_no_events_message: 'Keine passenden Termine.', bulk_registration_close_days: 'Tage vorher',
        bulk_registration_close_time: 'Uhrzeit', name: 'Name', max_participants: 'Max. Teilnehmer', play_together: 'Mixed',
        description: 'Beschreibung', price_members: 'Preis Mitglieder', price_guests: 'Preis Gäste', date: 'Datum',
        registration_close: 'Anmeldeschluss',
      },
    });
    navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
  });

  async function render() {
    const fixture = TestBed.createComponent(BulkCreatePage);
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;
    const type = (id: string, value: string) => {
      const input = el.querySelector<HTMLInputElement | HTMLSelectElement>(`#${id}`)!;
      input.value = value;
      input.dispatchEvent(new Event('input'));
      input.dispatchEvent(new Event('change'));
    };
    const fill = () => {
      type('startDate', '2099-06-01');
      type('endDate', '2099-06-30');
      type('dayOfWeek', '2');
      type('name', 'Dienstagsrunde');
      type('capacity', '24');
    };
    const submit = async () => {
      el.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
      await fixture.whenStable();
    };
    const button = (text: string) => Array.from(el.querySelectorAll('button')).find((b) => b.textContent?.trim() === text) as HTMLButtonElement;
    const click = async (b: HTMLElement) => {
      b.click();
      await fixture.whenStable();
    };
    return { el, type, fill, submit, button, click };
  }

  it('lists the seven weekdays Monday to Sunday with values 1..6,0', async () => {
    const { el } = await render();

    const options = Array.from(el.querySelectorAll<HTMLOptionElement>('#dayOfWeek option')).map((o) => o.value);
    expect(options).toEqual(['', '1', '2', '3', '4', '5', '6', '0']);
    expect(el.querySelector<HTMLOptionElement>('#dayOfWeek option[value="1"]')!.textContent).toMatch(/Montag/);
    expect(el.querySelector<HTMLOptionElement>('#dayOfWeek option[value="0"]')!.textContent).toMatch(/Sonntag/);
  });

  it('does not preview while required fields are missing', async () => {
    const { el, submit } = await render();

    await submit();

    expect(api.post).not.toHaveBeenCalled();
    expect(el.textContent).toContain('Das Feld Startdatum ist erforderlich.');
  });

  it('sends the preview request body', async () => {
    const { el, type, fill, submit } = await render();
    fill();
    el.querySelector<HTMLInputElement>('#mixed')!.click();
    type('description', 'Text');
    type('priceMembers', 'CHF 50');
    type('registrationCloseDays', '3');
    type('registrationCloseTime', '18:30');
    await submit();

    expect(api.post).toHaveBeenCalledWith(
      '/events/bulk/preview',
      {
        startDate: '2099-06-01', endDate: '2099-06-30', dayOfWeek: 2, name: 'Dienstagsrunde', capacity: 24, mixed: true,
        description: 'Text', priceMembers: 'CHF 50', priceGuests: null, registrationCloseDays: 3, registrationCloseTime: '18:30',
      },
      undefined,
    );
  });

  it('sends Sunday (0) as a number and empty optionals as null', async () => {
    const { type, fill, submit } = await render();
    fill();
    type('dayOfWeek', '0');
    await submit();

    expect(api.post.mock.calls[0][1]).toMatchObject({ dayOfWeek: 0, mixed: false, description: null, registrationCloseDays: null, registrationCloseTime: null });
  });

  it('renders the preview with the title, date, name, capacity and registration close', async () => {
    const { el, fill, submit } = await render();
    fill();
    await submit();

    expect(el.querySelector('h1')?.textContent).toContain('Vorschau: 2 Termine werden erstellt');
    expect(el.querySelector('form')).toBeNull();
    const rows = el.querySelectorAll('[data-preview-row]');
    expect(rows).toHaveLength(2);
    expect(rows[0].textContent).toContain(formatDate('2099-06-02', 'de'));
    expect(rows[0].textContent).toContain('Dienstagsrunde');
    expect(rows[0].textContent).toContain('24');
    expect(rows[0].textContent).toContain(formatDateTime('2099-06-02T12:00:00', 'de'));
  });

  it('shows the empty message and no create button when nothing matches', async () => {
    api.post.mockResolvedValue({ events: [] });
    const { el, fill, submit, button } = await render();
    fill();
    await submit();

    expect(el.textContent).toContain('Keine passenden Termine.');
    expect(button('Alle erstellen')).toBeUndefined();
    expect(button('Zurück')).toBeDefined();
  });

  it('back returns to the form with the entered values', async () => {
    const { el, fill, submit, button, click } = await render();
    fill();
    await submit();

    await click(button('Zurück'));

    expect(el.querySelector<HTMLInputElement>('#startDate')!.value).toBe('2099-06-01');
    expect(el.querySelector<HTMLInputElement>('#name')!.value).toBe('Dienstagsrunde');
    expect(el.querySelector<HTMLSelectElement>('#dayOfWeek')!.value).toBe('2');
    expect(el.querySelector<HTMLInputElement>('#capacity')!.value).toBe('24');
  });

  it('create all sends exactly the previewed events, toasts the count and goes to the list', async () => {
    const { fill, submit, button, click } = await render();
    fill();
    await submit();
    api.post.mockResolvedValue({ created: 2 });

    await click(button('Alle erstellen'));

    expect(api.post).toHaveBeenLastCalledWith('/events/bulk', { events }, undefined);
    expect(notify.success).toHaveBeenCalledWith('events.bulk_create_success', { count: 2 });
    expect(navigate).toHaveBeenCalledWith('/events');
  });

  it('shows 422 errors of the preview request under the field', async () => {
    api.post.mockRejectedValue(new ApiError(422, 'validation.failed', { endDate: { key: 'validation.date' } }));
    const { el, fill, submit } = await render();
    fill();
    await submit();

    expect(el.querySelector('#endDate-error')?.textContent).toContain('Ungültiges Datum');
  });
});
