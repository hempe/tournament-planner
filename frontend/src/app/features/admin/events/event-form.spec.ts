import { TestBed } from '@angular/core/testing';
import { ApiError } from '../../../core/api/api-client';
import { I18n } from '../../../core/i18n/i18n';
import { EventForm } from './event-form';
import { amigosFlavor, provideTestFlavor, setTestFlavor } from '../../../core/flavor/testing';

describe('EventForm', () => {
  afterEach(() => setTestFlavor(null));

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideTestFlavor()] });
    TestBed.inject(I18n).use('de', {
      validation: { required: 'Das Feld :field ist erforderlich.', min: 'Das Feld :field ist zu klein.', min_value: 'Das Feld :field muss mindestens :min sein.' },
      events: {
        name: 'Name', date: 'Datum', max_participants: 'Max. Teilnehmer', play_together: 'Gemischt', description: 'Beschreibung',
        price_members: 'Preis für Mitglieder', price_guests: 'Preis für Gäste', registration_close: 'Anmeldeschluss', save: 'Speichern',
      },
    });
  });

  async function render(inputs: Record<string, unknown> = {}) {
    const fixture = TestBed.createComponent(EventForm);
    for (const [key, value] of Object.entries(inputs)) {
      fixture.componentRef.setInput(key, value);
    }
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;
    const saved: unknown[] = [];
    fixture.componentInstance.save.subscribe((v) => saved.push(v));
    const type = (id: string, value: string) => {
      const input = el.querySelector<HTMLInputElement>(`#${id}`)!;
      input.value = value;
      input.dispatchEvent(new Event('input'));
    };
    const submit = async () => {
      el.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
      await fixture.whenStable();
    };
    return { fixture, el, type, submit, saved };
  }

  it('create mode: required fields and capacity >= 1 are validated without emitting', async () => {
    const { el, type, submit, saved } = await render({ mode: 'create' });

    await submit();
    expect(saved).toEqual([]);
    for (const label of ['Name', 'Datum', 'Max. Teilnehmer']) {
      expect(el.textContent).toContain(`Das Feld ${label} ist erforderlich.`);
    }

    type('name', 'Cup');
    type('date', '2099-06-01');
    type('capacity', '0');
    await submit();
    expect(saved).toEqual([]);
    expect(el.textContent).toContain('Das Feld Max. Teilnehmer muss mindestens 1 sein.');
  });

  it('create mode: emits the body with empty strings as null', async () => {
    const { type, submit, saved } = await render({ mode: 'create' });

    type('name', 'Cup');
    type('date', '2099-06-01');
    type('capacity', '12');
    await submit();

    expect(saved).toEqual([
      { name: 'Cup', date: '2099-06-01', capacity: 12, mixed: false, description: null, priceMembers: null, priceGuests: null, registrationClose: null },
    ]);
  });

  it('create mode: emits all optional fields and the mixed flag', async () => {
    const { el, type, submit, saved } = await render({ mode: 'create' });

    type('name', 'Cup');
    type('date', '2099-06-01');
    type('capacity', '12');
    const mixed = el.querySelector<HTMLInputElement>('#mixed')!;
    mixed.click();
    type('description', 'Text');
    type('priceMembers', 'CHF 50');
    type('priceGuests', 'CHF 80');
    type('registrationClose', '2099-05-30T12:00');
    await submit();

    expect(saved).toEqual([
      {
        name: 'Cup', date: '2099-06-01', capacity: 12, mixed: true, description: 'Text', priceMembers: 'CHF 50',
        priceGuests: 'CHF 80', registrationClose: '2099-05-30T12:00',
      },
    ]);
  });

  it('edit mode: no date field, prefilled, emits the update body without a date', async () => {
    const { el, type, submit, saved } = await render({
      mode: 'edit',
      initial: { name: 'Cup', capacity: 8, mixed: true, description: null, registrationClose: '2099-05-30T12:00:00' },
    });

    expect(el.querySelector('#date')).toBeNull();
    expect(el.querySelector<HTMLInputElement>('#name')!.value).toBe('Cup');
    expect(el.querySelector<HTMLInputElement>('#registrationClose')!.value).toBe('2099-05-30T12:00');

    type('name', 'Cup 2');
    await submit();

    expect(saved).toEqual([
      { name: 'Cup 2', capacity: 8, mixed: true, description: null, priceMembers: null, priceGuests: null, registrationClose: '2099-05-30T12:00' },
    ]);
  });

  it('create mode prefills the date', async () => {
    const { el } = await render({ mode: 'create', initial: { date: '2099-06-07' } });
    expect(el.querySelector<HTMLInputElement>('#date')!.value).toBe('2099-06-07');
  });

  it('shows 422 field errors', async () => {
    const error = new ApiError(422, 'validation.failed', { capacity: { key: 'validation.min' } });
    const { el } = await render({ error });
    expect(el.querySelector('#capacity-error')?.textContent).toContain('Das Feld Max. Teilnehmer ist zu klein.');
  });

  it('hides gender split, description, prices and deadline when the flavour has them off; sends mixed', async () => {
    setTestFlavor(amigosFlavor);
    const { el, type, submit, saved } = await render({ mode: 'create' });

    for (const id of ['mixed', 'description', 'priceMembers', 'priceGuests', 'registrationClose']) {
      expect(el.querySelector(`#${id}`), id).toBeNull();
    }
    type('name', 'Monatsturnier');
    type('date', '2099-05-01');
    type('capacity', '20');
    await submit();

    expect(saved).toEqual([expect.objectContaining({ name: 'Monatsturnier', mixed: true, description: null, priceMembers: null, registrationClose: null })]);
  });
});
