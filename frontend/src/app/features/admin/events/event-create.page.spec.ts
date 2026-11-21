import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { Notify } from '../../../core/feedback/notify';
import { I18n } from '../../../core/i18n/i18n';
import { EventCreatePage } from './event-create.page';

describe('EventCreatePage', () => {
  let api: { post: ReturnType<typeof vi.fn> };
  let notify: { success: ReturnType<typeof vi.fn> };
  let navigate: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    api = { post: vi.fn().mockResolvedValue({ event: { id: 7 } }) };
    notify = { success: vi.fn() };
    TestBed.configureTestingModule({
      providers: [provideRouter([]), { provide: ApiClient, useValue: api }, { provide: Notify, useValue: notify }],
    });
    TestBed.inject(I18n).use('de', {
      validation: { required: 'Das Feld :field ist erforderlich.', date: 'Ungültiges Datum' },
      events: { new: 'Neuer Anlass', name: 'Name', date: 'Datum', max_participants: 'Max. Teilnehmer', save: 'Speichern' },
    });
    navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
  });

  async function render(date?: string) {
    const fixture = TestBed.createComponent(EventCreatePage);
    if (date) {
      fixture.componentRef.setInput('date', date);
    }
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;
    const type = (id: string, value: string) => {
      const input = el.querySelector<HTMLInputElement>(`#${id}`)!;
      input.value = value;
      input.dispatchEvent(new Event('input'));
    };
    const submit = async () => {
      el.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
      await fixture.whenStable();
    };
    return { el, type, submit };
  }

  it('shows the heading and prefills the date from ?date', async () => {
    const { el } = await render('2099-06-07');

    expect(el.querySelector('h1')?.textContent).toContain('Neuer Anlass');
    expect(el.querySelector<HTMLInputElement>('#date')!.value).toBe('2099-06-07');
  });

  it('does not call the API while required fields are missing', async () => {
    const { el, submit } = await render();

    await submit();

    expect(api.post).not.toHaveBeenCalled();
    expect(el.textContent).toContain('Das Feld Name ist erforderlich.');
  });

  it('posts the exact body, toasts and opens the administration page', async () => {
    const { type, submit } = await render('2099-06-07');

    type('name', 'Cup');
    type('capacity', '16');
    await submit();

    expect(api.post).toHaveBeenCalledWith(
      '/events',
      { name: 'Cup', date: '2099-06-07', capacity: 16, mixed: false, description: null, priceMembers: null, priceGuests: null, registrationClose: null },
      undefined,
    );
    expect(notify.success).toHaveBeenCalledWith('events.update_success');
    expect(navigate).toHaveBeenCalledWith('/events/7/admin');
  });

  it('shows 422 field errors and stays on the page', async () => {
    api.post.mockRejectedValue(new ApiError(422, 'validation.failed', { date: { key: 'validation.date' } }));
    const { el, type, submit } = await render('2099-06-07');

    type('name', 'Cup');
    type('capacity', '16');
    await submit();

    expect(el.querySelector('#date-error')?.textContent).toContain('Ungültiges Datum');
    expect(navigate).not.toHaveBeenCalled();
  });
});
