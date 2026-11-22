import flavor from '../../../../flavors/amigos/flavor.json';
import type { FlavorConfig } from './flavor';
import type { Locale, Translations } from '../i18n/i18n';

/** Build-time flavour. Swapped in for active-flavor.ts by the angular.json configuration `amigos`. */
export const ACTIVE_FLAVOR = flavor as FlavorConfig;

export async function loadFlavorTexts(locale: Locale): Promise<Translations> {
  const module = (await import(`../../../../flavors/amigos/i18n/${locale}.json`)) as { default: Translations };
  return module.default;
}
