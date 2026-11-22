import flavor from '../../../../flavors/elfaro/flavor.json';
import type { FlavorConfig } from './flavor';
import type { Locale, Translations } from '../i18n/i18n';

/** Build-time flavour. angular.json configuration `amigos` swaps this file for active-flavor.amigos.ts. */
export const ACTIVE_FLAVOR = flavor as FlavorConfig;

export async function loadFlavorTexts(locale: Locale): Promise<Translations> {
  const module = (await import(`../../../../flavors/elfaro/i18n/${locale}.json`)) as { default: Translations };
  return module.default;
}
