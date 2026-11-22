import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ApiClient, ApiError } from '../../../core/api/api-client';
import { AdminGuestDto } from '../../../core/api/generated';
import { ConfirmService } from '../../../core/feedback/confirm.service';
import { Notify } from '../../../core/feedback/notify';
import { I18n } from '../../../core/i18n/i18n';
import { GuestEditPage } from './guest-edit.page';

const guest: AdminGuestDto = {
  id: 5, male: false, firstName: 'Gina', lastName: 'Guest', email: 'gina@example.com', handicap: 12.4, comment: 'Notiz', registeredAt: '2099-05-02T08:00:00',
};

describe('GuestEditPage', () => {
  let api: Record<'get' | 'put' | 'delete', ReturnType<typeof vi.fn>>;
  let confirm: { ask: ReturnType<typeof vi.fn> };
  let notify: { success: ReturnType<typeof vi.fn> };
  let navigate: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    api = { get: vi.fn().mockResolvedValue(guest), put: vi.fn().mockResolvedValue(guest), delete: vi.fn().mockResolvedValue(undefined) };
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
      navigation: { home: 'Startseite' },
      validation: { required: 'Das Feld :field ist erforderlich.', email: 'Ungültige eMail' },
      events: { save: 'Speichern', delete: 'Löschen' },
      users: { salutation: 'Anrede', mr: 'Herr', mrs: 'Frau' },
      guests: {
        edit: 'Gast bearbeiten', first_name: 'Vorname', last_name: 'Nachname', email: 'eMail', handicap: 'Handicap', comment: 'Kommentar',
        update_success: 'Gast aktualisiert', delete_success: 'Gast gelöscht', delete_confirm: 'Gast definitiv löschen?', not_found: 'Gast nicht gefunden',
      },
    });
    navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
  });

  async function render() {
    const fixture = TestBed.createComponent(GuestEditPage);
    fixture.componentRef.setInput('id', '3');
    fixture.componentRef.setInput('guestId', '5');
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
    return { fixture, el, type, submit };
  }

  it('loads and prefills the guest', async () => {
    const { el } = await render();

    expect(api.get).toHaveBeenCalledWith('/events/3/guests/5', expect.anything());
    expect(el.querySelector<HTMLInputElement>('#firstName')!.value).toBe('Gina');
    expect(el.querySelector<HTMLInputElement>('#email')!.value).toBe('gina@example.com');
    expect(el.querySelector<HTMLInputElement>('#handicap')!.value).toBe('12.4');
    expect(el.querySelector<HTMLSelectElement>('#male')!.value).toBe('false');
    expect(el.querySelector('a[href="/events/3/admin"]')).not.toBeNull();
  });

  it('email and handicap are optional, names are required', async () => {
    const { el, type, submit } = await render();

    type('firstName', '');
    await submit();
    expect(api.put).not.toHaveBeenCalled();
    expect(el.textContent).toContain('Das Feld Vorname ist erforderlich.');

    type('firstName', 'Gina');
    type('email', '');
    type('handicap', '');
    await submit();
    expect(api.put).toHaveBeenCalledWith(
      '/events/3/guests/5',
      { male: false, firstName: 'Gina', lastName: 'Guest', email: null, handicap: null, comment: 'Notiz', website: null },
      undefined,
    );
  });

  it('saves, toasts and returns to the event administration', async () => {
    const { type, submit } = await render();

    type('lastName', 'Gast');
    await submit();

    expect(api.put).toHaveBeenCalledWith(
      '/events/3/guests/5',
      { male: false, firstName: 'Gina', lastName: 'Gast', email: 'gina@example.com', handicap: '12.4', comment: 'Notiz', website: null },
      undefined,
    );
    expect(notify.success).toHaveBeenCalledWith('guests.update_success');
    expect(navigate).toHaveBeenCalledWith('/events/3/admin');
  });

  it('shows 422 field errors and stays', async () => {
    api.put.mockRejectedValue(new ApiError(422, 'validation.failed', { email: { key: 'validation.email' } }));
    const { el, submit } = await render();

    await submit();

    expect(el.querySelector('#email-error')?.textContent).toContain('Ungültige eMail');
    expect(navigate).not.toHaveBeenCalled();
  });

  it('deletes after a danger confirmation, toasts and goes back', async () => {
    const { fixture, el } = await render();

    Array.from(el.querySelectorAll('button')).find((b) => b.textContent?.trim() === 'Löschen')!.click();
    await fixture.whenStable();

    expect(confirm.ask).toHaveBeenCalledWith('Gast definitiv löschen?', { danger: true });
    expect(api.delete).toHaveBeenCalledWith('/events/3/guests/5', undefined);
    expect(notify.success).toHaveBeenCalledWith('guests.delete_success');
    expect(navigate).toHaveBeenCalledWith('/events/3/admin');
  });

  it('shows the not-found message on 404', async () => {
    api.get.mockRejectedValue(new ApiError(404, 'guests.not_found'));
    const { el } = await render();

    expect(el.textContent).toContain('Gast nicht gefunden');
  });
});
