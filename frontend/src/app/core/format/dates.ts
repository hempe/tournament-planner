/** API dates are 'YYYY-MM-DD', date-times 'YYYY-MM-DDTHH:MM:SS', both local (Europe/Zurich). */
export function parseApiDate(value: string): Date {
  const [datePart, timePart = '00:00:00'] = value.split('T');
  const [y, m, d] = datePart.split('-').map(Number);
  const [hh, mm, ss] = timePart.split(':').map(Number);
  return new Date(y, m - 1, d, hh, mm, ss || 0);
}

export function formatDate(value: string, locale: string): string {
  return new Intl.DateTimeFormat(locale, { dateStyle: 'full' }).format(parseApiDate(value));
}

export function formatDateTime(value: string, locale: string): string {
  return new Intl.DateTimeFormat(locale, { dateStyle: 'full', timeStyle: 'short' }).format(parseApiDate(value));
}

/** 'YYYY-MM' → "Juni 2099" */
export function formatMonth(month: string, locale: string): string {
  return new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(parseApiDate(`${month}-01`));
}

/** Local date → 'YYYY-MM-DD' (comparable with API dates as strings). */
export function toApiDate(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}
