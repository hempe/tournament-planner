const MONTH_PATTERN = /^(\d{4})-(0[1-9]|1[0-2])$/;
const DATE_PATTERN = /^(\d{4})-(\d{1,2})-(\d{1,2})$/;

const pad = (n: number): string => String(n).padStart(2, '0');

/** 'YYYY-MM' of a date (local). */
export function monthOf(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
}

/** Shift 'YYYY-MM' by n months ('2099-12', 1 → '2100-01'). */
export function shiftMonth(month: string, delta: number): string {
  const [year, m] = month.split('-').map(Number);
  return monthOf(new Date(year, m - 1 + delta, 1));
}

/** Resolve the calendar month from ?month=YYYY-MM or the legacy ?date=YYYY-MM-D; falls back to `today`. */
export function resolveMonth(month: string | undefined, legacyDate: string | undefined, today: Date): string {
  if (month !== undefined && MONTH_PATTERN.test(month)) {
    return month;
  }
  const legacy = legacyDate === undefined ? null : DATE_PATTERN.exec(legacyDate);
  if (legacy && Number(legacy[2]) >= 1 && Number(legacy[2]) <= 12) {
    return `${legacy[1]}-${pad(Number(legacy[2]))}`;
  }
  return monthOf(today);
}

/** Weeks (Monday first) covering the month; days outside the month are null. */
export function monthGrid(month: string): (string | null)[][] {
  const [year, m] = month.split('-').map(Number);
  const daysInMonth = new Date(year, m, 0).getDate();
  const leading = (new Date(year, m - 1, 1).getDay() + 6) % 7;
  const cells: (string | null)[] = Array<null>(leading).fill(null);
  for (let day = 1; day <= daysInMonth; day++) {
    cells.push(`${month}-${pad(day)}`);
  }
  while (cells.length % 7 !== 0) {
    cells.push(null);
  }
  const weeks: (string | null)[][] = [];
  for (let i = 0; i < cells.length; i += 7) {
    weeks.push(cells.slice(i, i + 7));
  }
  return weeks;
}
