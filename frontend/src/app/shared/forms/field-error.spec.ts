import { TestBed } from '@angular/core/testing';
import { ApiError } from '../../core/api/api-client';
import { I18n } from '../../core/i18n/i18n';
import { FieldError } from './field-error';

describe('FieldError', () => {
  beforeEach(() => {
    TestBed.inject(I18n).use('de', {
      validation: { required: 'Das Feld :field ist erforderlich.', min: 'Das Feld :field braucht :min.', min_value: 'Das Feld :field muss mindestens :min sein.' },
      events: { name: 'Name' },
      invalid: { email: 'Ungültige eMail' },
    });
  });

  async function render(inputs: Record<string, unknown>) {
    const fixture = TestBed.createComponent(FieldError);
    for (const [key, value] of Object.entries(inputs)) {
      fixture.componentRef.setInput(key, value);
    }
    await fixture.whenStable();
    return fixture.nativeElement as HTMLElement;
  }

  it('shows nothing without an error', async () => {
    expect((await render({ field: 'name' })).textContent?.trim()).toBe('');
  });

  it('shows the translated server message with params and the field label for validation keys', async () => {
    const error = new ApiError(422, 'validation.failed', { name: { key: 'validation.min', params: { min: 3 } } });
    const el = await render({ field: 'name', label: 'events.name', error });

    expect(el.querySelector('#name-error')?.textContent).toContain('Das Feld Name braucht 3.');
  });

  it('shows non-validation keys as they are', async () => {
    const error = new ApiError(422, 'validation.failed', { email: { key: 'invalid.email' } });
    expect((await render({ field: 'email', error })).textContent).toContain('Ungültige eMail');
  });

  it('shows the required message once the control is touched and invalid', async () => {
    const el = await render({ field: 'name', label: 'events.name', control: { touched: () => true, invalid: () => true, errors: () => [{ kind: 'required' }] } });
    expect(el.textContent).toContain('Das Feld Name ist erforderlich.');
  });

  it('shows the minimum-value message for a min violation', async () => {
    const el = await render({ field: 'capacity', control: { touched: () => true, invalid: () => true, errors: () => [{ kind: 'min', min: 1 }] } });
    expect(el.textContent).toContain('Das Feld capacity muss mindestens 1 sein.');
  });

  it('stays quiet for an untouched control', async () => {
    const el = await render({ field: 'name', control: { touched: () => false, invalid: () => true, errors: () => [{ kind: 'required' }] } });
    expect(el.textContent?.trim()).toBe('');
  });
});
