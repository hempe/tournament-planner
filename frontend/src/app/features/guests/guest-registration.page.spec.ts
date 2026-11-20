import { computed, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ApiClient, ApiError } from '../../core/api/api-client';
import { PublicEventDto } from '../../core/api/generated';
import { SessionStore } from '../../core/auth/session.store';
import { Notify } from '../../core/feedback/notify';
import { I18n } from '../../core/i18n/i18n';
import { GuestRegistrationPage } from './guest-registration.page';

function info(over: Partial<PublicEventDto> = {}): PublicEventDto {
  return {
    id: 3, name: 'Frühlingscup', date: '2099-06-01', description: 'Beschreibung X', priceMembers: 'CHF 50',
    priceGuests: 'CHF 80', registrationClose: '2099-05-30T12:00:00', isLocked: false, ...over,
  };
}

describe('GuestRegistrationPage', () => {
  const isAdmin = signal(false);
  let api: { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn> };
  let notify: { success: ReturnType<typeof vi.fn> };
  let navigate: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    isAdmin.set(false);
    api = { get: vi.fn().mockResolvedValue(info()), post: vi.fn().mockResolvedValue(undefined) };
    notify = { success: vi.fn() };
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: ApiClient, useValue: api },
        { provide: Notify, useValue: notify },
        { provide: SessionStore, useValue: { isAdmin: computed(() => isAdmin()), loggedIn: signal(false) } },
      ],
    });
    TestBed.inject(I18n).use('de', {
      navigation: { home: 'Startseite' },
      validation: { required: 'Das Feld :field ist erforderlich.' },
      events: { locked_message: 'Anmeldung geschlossen', not_found: 'Anlass nicht gefunden', description: 'Beschreibung', price_members: 'Preis Mitglieder', price_guests: 'Preis Gäste', registration_close: 'Anmeldeschluss' },
      users: { salutation: 'Anrede', mr: 'Herr', mrs: 'Frau' },
      guests: {
        first_name: 'Vorname', last_name: 'Nachname', email: 'eMail', handicap: 'Handicap', comment: 'Kommentar',
        register: 'Als Gast anmelden', register_success: 'Erfolgreich',
      },
      invalid: { email: 'Ungültige eMail' },
    });
    navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
  });

  async function render() {
    const fixture = TestBed.createComponent(GuestRegistrationPage);
    fixture.componentRef.setInput('id', '3');
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
    const fill = () => {
      type('male', 'true');
      type('firstName', 'Gus');
      type('lastName', 'Guest');
      type('email', 'gus@example.com');
      type('handicap', '12.4');
      type('comment', 'Hallo');
    };
    return { el, type, submit, fill };
  }

  it('shows event info and the required markers for anonymous visitors', async () => {
    const { el } = await render();

    expect(api.get).toHaveBeenCalledWith('/events/3/guest-info', expect.anything());
    expect(el.textContent).toContain('Frühlingscup');
    expect(el.textContent).toContain('CHF 80');
    for (const label of ['Anrede', 'Vorname', 'Nachname', 'eMail', 'Handicap']) {
      const text = Array.from(el.querySelectorAll('label')).find((l) => l.textContent?.includes(label))?.textContent;
      expect(text, label).toContain('*');
    }
    const comment = Array.from(el.querySelectorAll('label')).find((l) => l.textContent?.includes('Kommentar'));
    expect(comment?.textContent).not.toContain('*');
  });

  it('submitting empty shows required errors without calling the API', async () => {
    const { el, submit } = await render();

    await submit();

    expect(api.post).not.toHaveBeenCalled();
    for (const label of ['Anrede', 'Vorname', 'Nachname', 'eMail', 'Handicap']) {
      expect(el.textContent).toContain(`Das Feld ${label} ist erforderlich.`);
    }
  });

  it('posts the exact body, toasts and navigates home', async () => {
    const { fill, submit } = await render();

    fill();
    await submit();

    expect(api.post).toHaveBeenCalledWith(
      '/events/3/guests',
      { male: true, firstName: 'Gus', lastName: 'Guest', email: 'gus@example.com', handicap: '12.4', comment: 'Hallo' },
      undefined,
    );
    expect(notify.success).toHaveBeenCalledWith('guests.register_success');
    expect(navigate).toHaveBeenCalledWith('/');
  });

  it('shows 422 field errors under the inputs', async () => {
    api.post.mockRejectedValue(new ApiError(422, 'validation.failed', { email: { key: 'invalid.email' } }));
    const { el, fill, submit } = await render();

    fill();
    await submit();

    expect(el.querySelector('#email-error')?.textContent).toContain('Ungültige eMail');
    expect(navigate).not.toHaveBeenCalled();
  });

  it('locked event: anonymous sees the message and no form', async () => {
    api.get.mockResolvedValue(info({ isLocked: true }));
    const { el } = await render();

    expect(el.textContent).toContain('Anmeldung geschlossen');
    expect(el.querySelector('form')).toBeNull();
  });

  it('locked event: admin sees the form, email and handicap are optional, and goes to the admin page', async () => {
    isAdmin.set(true);
    api.get.mockResolvedValue(info({ isLocked: true }));
    const { el, type, submit } = await render();

    expect(el.querySelector('form')).not.toBeNull();
    const email = Array.from(el.querySelectorAll('label')).find((l) => l.textContent?.includes('eMail'));
    expect(email?.textContent).not.toContain('*');

    type('male', 'false');
    type('firstName', 'Gus');
    type('lastName', 'Guest');
    await submit();

    expect(api.post).toHaveBeenCalledWith(
      '/events/3/guests',
      { male: false, firstName: 'Gus', lastName: 'Guest', email: null, handicap: null, comment: null },
      undefined,
    );
    expect(navigate).toHaveBeenCalledWith('/events/3/admin');
  });

  it('shows the not-found message on 404', async () => {
    api.get.mockRejectedValue(new ApiError(404, 'events.not_found'));
    const { el } = await render();

    expect(el.textContent).toContain('Anlass nicht gefunden');
  });
});
