import { ApiError } from '../../core/api/api-client';
import { I18n } from '../../core/i18n/i18n';

/** Translated 422 field errors keyed by API field name. */
export function translateFieldErrors(error: ApiError, i18n: I18n): Record<string, string> {
  return Object.fromEntries(Object.entries(error.fields).map(([name, f]) => [name, i18n.t(f.key, f.params)]));
}
