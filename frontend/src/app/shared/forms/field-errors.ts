import { ApiError } from '../../core/api/api-client';
import { I18n } from '../../core/i18n/i18n';

/** Translated 422 message for one field, or null. `label` (already translated) fills `:field` of `validation.*` keys. */
export function translateFieldError(error: ApiError | null, name: string, i18n: I18n, label?: string): string | null {
  const f = error?.fields[name];
  if (!f) {
    return null;
  }
  const params = f.key.startsWith('validation.') ? { field: label ?? name, ...f.params } : f.params;
  return i18n.t(f.key, params);
}

/** Translated 422 field errors keyed by API field name. */
export function translateFieldErrors(error: ApiError, i18n: I18n): Record<string, string> {
  return Object.fromEntries(Object.keys(error.fields).map((name) => [name, translateFieldError(error, name, i18n) ?? '']));
}
