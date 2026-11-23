import { Provider } from '@angular/core';
import { ACTIVE_FLAVOR } from './active-flavor';
import { FLAVOR, FlavorConfig } from './flavor';

/** Test fixture: the Amigos flavour (specs only — never import from application code). */
export const amigosFlavor: FlavorConfig = {
  name: 'Amigos de Tauro',
  shortName: 'Amigos',
  logo: '/logo-header.png',
  timezone: 'Atlantic/Canary',
  usernameOrder: 'lastFirst',
  website: null,
  features: {
    socialEvents: false,
    guests: false,
    prices: false,
    description: false,
    deadline: false,
    genderSplit: false,
    waitlistOrder: 'priority',
    userFields: ['phone', 'handicap', 'priority'],
  },
};

let testFlavor: FlavorConfig | null = null;

/** FLAVOR for specs, resolved lazily: setTestFlavor() inside a test works until the first component injects it. */
export function provideTestFlavor(): Provider {
  return { provide: FLAVOR, useFactory: () => testFlavor ?? ACTIVE_FLAVOR };
}

/** Switch the flavour for the current test (reset with null in afterEach). */
export function setTestFlavor(flavor: FlavorConfig | null): void {
  testFlavor = flavor;
}
