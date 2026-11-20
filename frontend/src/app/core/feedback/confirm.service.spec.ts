import { TestBed } from '@angular/core/testing';
import { ConfirmService } from './confirm.service';

describe('ConfirmService', () => {
  it('resolves with the answer and clears the pending request', async () => {
    const confirm = TestBed.inject(ConfirmService);

    const answer = confirm.ask('Turnier abmelden?', { danger: true });
    expect(confirm.pending()).toEqual({ message: 'Turnier abmelden?', danger: true });

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
});
