import { formatDate, formatDateTime, formatMonth, parseApiDate } from './dates';

describe('dates', () => {
  it('parses API dates and date-times as local time', () => {
    const d = parseApiDate('2099-06-01');
    expect([d.getFullYear(), d.getMonth(), d.getDate(), d.getHours()]).toEqual([2099, 5, 1, 0]);
    const t = parseApiDate('2099-05-30T18:30:00');
    expect([t.getDate(), t.getHours(), t.getMinutes()]).toEqual([30, 18, 30]);
  });

  it('formats with Intl in the given locale', () => {
    expect(formatDate('2099-06-01', 'de')).toBe('Montag, 1. Juni 2099');
    expect(formatDateTime('2099-05-30T18:30:00', 'de')).toBe('Samstag, 30. Mai 2099 um 18:30');
    expect(formatMonth('2099-06', 'de')).toBe('Juni 2099');
    expect(formatMonth('2099-06', 'en')).toBe('June 2099');
  });
});
