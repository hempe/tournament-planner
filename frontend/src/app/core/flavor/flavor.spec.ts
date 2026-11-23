import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ACTIVE_FLAVOR } from './active-flavor';
import { FLAVOR, FlavorConfig, Features, featureGuard, provideFlavor } from './flavor';
import { mergeTexts } from '../i18n/i18n';

const amigos: FlavorConfig = {
  name: 'Amigos de Tauro', shortName: 'Amigos',
  logo: '/logo-header.png', timezone: 'Atlantic/Canary', usernameOrder: 'lastFirst', website: null,
  features: {
    socialEvents: false, guests: false, prices: false, description: false, deadline: false, genderSplit: false,
    waitlistOrder: 'priority', userFields: ['phone', 'handicap', 'priority'],
  },
};

describe('flavor', () => {
  it('defaults to the El Faro build (tests always compile the default configuration)', () => {
    expect(ACTIVE_FLAVOR.name).toBe('GOLF EL FARO');
    expect(TestBed.inject(FLAVOR)).toBe(ACTIVE_FLAVOR);
    expect(TestBed.inject(Features).has('guests')).toBe(true);
  });

  it('answers feature and user-field questions from the injected flavour', () => {
    TestBed.configureTestingModule({ providers: [provideFlavor(amigos)] });
    const features = TestBed.inject(Features);
    expect(features.has('guests')).toBe(false);
    expect(features.userField('handicap')).toBe(true);
    expect(features.userField('rfeg')).toBe(false);
  });

  it('featureGuard sends switched-off routes to the not-found page', () => {
    TestBed.configureTestingModule({ providers: [provideRouter([]), provideFlavor(amigos)] });
    const result = TestBed.runInInjectionContext(() => featureGuard('guests')({} as never, {} as never));
    expect(TestBed.inject(Router).serializeUrl(result as never)).toBe('/not-found');
  });

  it('mergeTexts replaces only overridden leaves', () => {
    expect(mergeTexts({ app: { name: 'A', welcome: 'W' }, x: 'y' }, { app: { name: 'B' } })).toEqual({ app: { name: 'B', welcome: 'W' }, x: 'y' });
  });
});
