import { Injectable, signal } from '@angular/core';
import { loadFlavorTexts } from '../flavor/active-flavor';

export const LOCALES = ['de', 'en', 'es'] as const;
export type Locale = (typeof LOCALES)[number];
export type Translations = Record<string, unknown>;
export type Params = Record<string, string | number>;

/** First supported primary language from the browser's list; German otherwise. */
export function negotiateLocale(preferred: readonly string[]): Locale {
  for (const tag of preferred) {
    const primary = tag.slice(0, 2).toLowerCase();
    if ((LOCALES as readonly string[]).includes(primary)) {
      return primary as Locale;
    }
  }
  return 'de';
}

export function lookup(table: Translations, key: string): string | undefined {
  let node: unknown = table;
  for (const part of key.split('.')) {
    if (typeof node !== 'object' || node === null) {
      return undefined;
    }
    node = (node as Record<string, unknown>)[part];
  }
  return typeof node === 'string' ? node : undefined;
}

/** Replace `:name` placeholders (same syntax as the PHP Translator). */
export function interpolate(text: string, params: Params): string {
  return text.replace(/:([A-Za-z_]+)/g, (match: string, name: string) =>
    name in params ? String(params[name]) : match,
  );
}

/** Deep merge: string leaves of `override` replace those of `base` (same rule as PHP Translator::merge). */
export function mergeTexts(base: Translations, override: Translations): Translations {
  const out: Translations = { ...base };
  for (const [key, value] of Object.entries(override)) {
    const current = out[key];
    out[key] =
      typeof value === 'object' && value !== null && typeof current === 'object' && current !== null
        ? mergeTexts(current as Translations, value as Translations)
        : value;
  }
  return out;
}

/** Each language file (and its flavour override) becomes its own lazy chunk. */
export async function loadLocale(locale: Locale): Promise<Translations> {
  const [base, override] = await Promise.all([
    import(`../../../i18n/${locale}.json`).then((m: { default: Translations }) => m.default),
    loadFlavorTexts(locale),
  ]);
  return mergeTexts(base, override);
}


@Injectable({ providedIn: 'root' })
export class I18n {
  private readonly _locale = signal<Locale>('de');
  readonly locale = this._locale.asReadonly();
  private table: Translations = {};

  async load(locale: Locale, loader: (locale: Locale) => Promise<Translations> = loadLocale): Promise<void> {
    this.use(locale, await loader(locale));
  }

  /** Install a translation table directly (used by load() and by tests). */
  use(locale: Locale, table: Translations): void {
    this.table = table;
    this._locale.set(locale);
  }

  t(key: string, params?: Params): string {
    const text = lookup(this.table, key) ?? key;
    return params ? interpolate(text, params) : text;
  }

  /** Plural forms: "one|many" or "zero|one|many"; `:count` is always available. */
  choice(key: string, count: number, params: Params = {}): string {
    const forms = (lookup(this.table, key) ?? key).split('|');
    let form: string;
    if (forms.length === 1) {
      form = forms[0];
    } else if (forms.length === 2) {
      form = forms[count === 1 ? 0 : 1];
    } else {
      form = forms[Math.min(count, 2)] ?? forms[0];
    }
    return interpolate(form, { count, ...params });
  }
}
