import { TestBed } from '@angular/core/testing';
import { I18n } from '../i18n/i18n';
import { ConfirmDialog } from './confirm-dialog';
import { ConfirmService } from './confirm.service';

describe('ConfirmDialog', () => {
  function button(text: string): HTMLButtonElement {
    return Array.from(document.querySelectorAll('button')).find((b) => b.textContent?.trim() === text)!;
  }

  beforeEach(() => {
    TestBed.inject(I18n).use('de', {
      calendar: { confirmation: 'Bestätigung' },
      actions: { cancel: 'Abbrechen', confirm: 'Bestätigen' },
    });
  });

  it('shows the pending question and resolves true on confirm', async () => {
    const fixture = TestBed.createComponent(ConfirmDialog);
    const confirm = TestBed.inject(ConfirmService);
    await fixture.whenStable();

    const answer = confirm.ask('Möchtest du dich wirklich von diesem Turnier abmelden?');
    await fixture.whenStable();

    expect(document.body.textContent).toContain('Bestätigung');
    expect(document.body.textContent).toContain('Möchtest du dich wirklich von diesem Turnier abmelden?');
    button('Bestätigen').click();
    await expect(answer).resolves.toBe(true);
  });

  it('resolves false on cancel', async () => {
    const fixture = TestBed.createComponent(ConfirmDialog);
    const confirm = TestBed.inject(ConfirmService);
    await fixture.whenStable();

    const answer = confirm.ask('Sicher?');
    await fixture.whenStable();
    button('Abbrechen').click();

    await expect(answer).resolves.toBe(false);
  });

  it('renders custom title and all actions of choose()', async () => {
    const fixture = TestBed.createComponent(ConfirmDialog);
    const confirm = TestBed.inject(ConfirmService);
    await fixture.whenStable();

    const choice = confirm.choose({
      title: 'Abmeldung',
      message: 'Von welchem Anlass möchtest du dich abmelden?',
      actions: [
        { value: 'tournament', label: 'Nur Turnier' },
        { value: 'both', label: 'Turnier & Abendessen' },
      ],
    });
    await fixture.whenStable();

    expect(document.body.textContent).toContain('Abmeldung');
    button('Turnier & Abendessen').click();
    await expect(choice).resolves.toBe('both');
  });

  it('uses a custom cancel label', async () => {
    const fixture = TestBed.createComponent(ConfirmDialog);
    const confirm = TestBed.inject(ConfirmService);
    await fixture.whenStable();

    const choice = confirm.choose({ message: 'Auch Abendessen?', actions: [{ value: 'yes', label: 'Ja' }], cancelLabel: 'Nein danke' });
    await fixture.whenStable();
    button('Nein danke').click();

    await expect(choice).resolves.toBeNull();
  });
});
