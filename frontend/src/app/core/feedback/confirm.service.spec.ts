import { TestBed } from '@angular/core/testing';
import { ConfirmService } from './confirm.service';

describe('ConfirmService', () => {
  it('resolves with the answer and clears the pending request', async () => {
    const confirm = TestBed.inject(ConfirmService);

    const answer = confirm.ask('Turnier abmelden?', { danger: true });
    expect(confirm.pending()?.message).toBe('Turnier abmelden?');

    confirm.answer(true);
    await expect(answer).resolves.toBe(true);
    expect(confirm.pending()).toBeNull();
  });

  it('a new question cancels the previous one', async () => {
    const confirm = TestBed.inject(ConfirmService);

    const first = confirm.ask('first?');
    const second = confirm.ask('second?');
    confirm.answer(true);

    await expect(first).resolves.toBe(false);
    await expect(second).resolves.toBe(true);
  });

  it('answering twice is harmless', async () => {
    const confirm = TestBed.inject(ConfirmService);
    const answer = confirm.ask('q?');

    confirm.answer(true);
    confirm.answer(false);

    await expect(answer).resolves.toBe(true);
  });

  it('choose() resolves with the chosen action value or null on cancel', async () => {
    const confirm = TestBed.inject(ConfirmService);

    const choice = confirm.choose({
      title: 'Abmeldung',
      message: 'Von welchem Anlass?',
      actions: [
        { value: 'tournament', label: 'Nur Turnier', danger: true },
        { value: 'both', label: 'Turnier & Abendessen', danger: true },
      ],
    });
    expect(confirm.pending()?.actions.map((a) => a.value)).toEqual(['tournament', 'both']);
    confirm.answer('both');
    await expect(choice).resolves.toBe('both');

    const cancelled = confirm.choose({ message: 'x', actions: [{ value: 'a', label: 'A' }] });
    confirm.answer(null);
    await expect(cancelled).resolves.toBeNull();
  });
});
