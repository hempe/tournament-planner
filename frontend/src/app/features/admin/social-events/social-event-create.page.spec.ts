import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { Notify } from '../../../core/feedback/notify';
import { I18n } from '../../../core/i18n/i18n';
import { SocialEventCreatePage } from './social-event-create.page';

describe('SocialEventCreatePage', () => {
  let api: Record<'get' | 'post', ReturnType<typeof vi.fn>>;
  let notify: { success: ReturnType<typeof vi.fn> };
  let navigate: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    api = {
      get: vi.fn().mockResolvedValue({ event: { id: 3, name: 'Frühlingscup', date: '2099-06-01' } }),
      post: vi.fn().mockResolvedValue({ event: { id: 9 } }),
    };
    notify = { success: vi.fn() };
    TestBed.configureTestingModule({
      providers: [provideRouter([]), { provide: ApiClient, useValue: api }, { provide: Notify, useValue: notify }],
    });
    TestBed.inject(I18n).use('de', {
      validation: { required: 'Das Feld :field ist erforderlich.', date: 'Ungültiges Datum' },
      social_events: {
        new: 'Neuer gesellschaftlicher Anlass', name: 'Name', date: 'Datum', description: 'Beschreibung', registration_close: 'Anmeldeschluss',
        menus: 'Menüoptionen', menus_hint: 'Menü-Hinweis', tables: 'Tische', tables_hint: 'Tisch-Hinweis', create: 'Erstellen',
        linked_tournament: 'Verknüpftes Turnier',
      },
    });
    navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
  });

  async function render(tournamentId?: string) {
    const fixture = TestBed.createComponent(SocialEventCreatePage);
    if (tournamentId) {
      fixture.componentRef.setInput('tournamentId', tournamentId);
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

  it('standalone: heading, hints, no tournament lookup and no linked tournament', async () => {
    const { el } = await render();

    expect(el.querySelector('h1')?.textContent).toContain('Neuer gesellschaftlicher Anlass');
    expect(api.get).not.toHaveBeenCalled();
    expect(el.textContent).toContain('Menü-Hinweis');
    expect(el.textContent).toContain('Tisch-Hinweis');
    expect(el.textContent).not.toContain('Verknüpftes Turnier');
  });

  it('with ?tournamentId: prefills the date and shows the linked tournament', async () => {
    const { el } = await render('3');

    expect(api.get).toHaveBeenCalledWith('/events/3/admin', expect.anything());
    expect(el.querySelector<HTMLInputElement>('#date')!.value).toBe('2099-06-01');
    expect(el.querySelector<HTMLInputElement>('#name')!.value).toBe('');
    expect(el.textContent).toContain('Verknüpftes Turnier');
    expect(el.textContent).toContain('Frühlingscup');
  });

  it('does not call the API while name and date are missing', async () => {
    const { el, submit } = await render();

    await submit();

    expect(api.post).not.toHaveBeenCalled();
    expect(el.textContent).toContain('Das Feld Name ist erforderlich.');
    expect(el.textContent).toContain('Das Feld Datum ist erforderlich.');
  });

  it('saves the request body with the tournament and goes to the administration page', async () => {
    const { type, submit } = await render('3');
    type('name', 'Dinner');
    type('description', 'Text');
    type('registrationClose', '2099-05-30T12:00');
    type('menus', 'Fleisch, Fisch');
    type('tables', '10, 8');
    await submit();

    expect(api.post).toHaveBeenCalledWith(
      '/social-events',
      { name: 'Dinner', date: '2099-06-01', tournamentId: 3, description: 'Text', registrationClose: '2099-05-30T12:00', menus: 'Fleisch, Fisch', tables: '10, 8' },
      undefined,
    );
    expect(navigate).toHaveBeenCalledWith('/social-events/9/admin');
  });

  it('standalone body has tournamentId null and empty optionals null', async () => {
    const { type, submit } = await render();
    type('name', 'Dinner');
    type('date', '2099-06-02');
    await submit();

    expect(api.post).toHaveBeenCalledWith(
      '/social-events',
      { name: 'Dinner', date: '2099-06-02', tournamentId: null, description: null, registrationClose: null, menus: '', tables: '' },
      undefined,
    );
  });

  it('shows 422 errors under the field', async () => {
    api.post.mockRejectedValue(new ApiError(422, 'validation.failed', { date: { key: 'validation.date' } }));
    const { el, type, submit } = await render();
    type('name', 'Dinner');
    type('date', '2099-06-02');
    await submit();

    expect(el.querySelector('#date-error')?.textContent).toContain('Ungültiges Datum');
    expect(navigate).not.toHaveBeenCalled();
  });
});
