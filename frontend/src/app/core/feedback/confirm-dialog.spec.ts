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
});
