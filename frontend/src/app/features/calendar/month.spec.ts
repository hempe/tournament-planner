import { monthGrid, monthOf, resolveMonth, shiftMonth } from './month';

describe('month helpers', () => {
  it('monthOf formats the local month', () => {
    expect(monthOf(new Date(2099, 5, 15))).toBe('2099-06');
    expect(monthOf(new Date(2099, 11, 31))).toBe('2099-12');
  });

  it('shiftMonth crosses year boundaries both ways', () => {
    expect(shiftMonth('2099-12', 1)).toBe('2100-01');
    expect(shiftMonth('2100-01', -1)).toBe('2099-12');
    expect(shiftMonth('2099-06', 0)).toBe('2099-06');
    expect(shiftMonth('2099-06', 14)).toBe('2100-08');
  });

  it('resolveMonth prefers ?month, maps the legacy ?date, and falls back to today', () => {
    const today = new Date(2099, 2, 10);
    expect(resolveMonth('2099-06', undefined, today)).toBe('2099-06');
    expect(resolveMonth(undefined, '2099-6-1', today)).toBe('2099-06');
    expect(resolveMonth(undefined, '2099-12-31', today)).toBe('2099-12');
    expect(resolveMonth('2099-06', '2098-01-01', today)).toBe('2099-06');
    expect(resolveMonth('junk', undefined, today)).toBe('2099-03');
    expect(resolveMonth('2099-13', undefined, today)).toBe('2099-03');
    expect(resolveMonth(undefined, 'nope', today)).toBe('2099-03');
    expect(resolveMonth(undefined, undefined, today)).toBe('2099-03');
  });

  it('monthGrid starts Monday-first with 5 weeks for June 2099', () => {
    const grid = monthGrid('2099-06');

    expect(grid).toHaveLength(5);
    expect(grid[0][0]).toBe('2099-06-01');
    expect(grid.every((week) => week.length === 7)).toBe(true);
    expect(grid[4][1]).toBe('2099-06-30');
    expect(grid[4].slice(2)).toEqual([null, null, null, null, null]);
  });

  it('monthGrid pads leading days when the month starts on Sunday', () => {
    // 1 Nov 2099 is a Sunday
    const grid = monthGrid('2099-11');

    expect(grid[0].slice(0, 6)).toEqual([null, null, null, null, null, null]);
    expect(grid[0][6]).toBe('2099-11-01');
    expect(grid[1][0]).toBe('2099-11-02');
  });
});
