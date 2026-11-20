import { TestBed } from '@angular/core/testing';
import { I18n, interpolate, loadLocale, lookup, negotiateLocale } from './i18n';

describe('negotiateLocale', () => {
  it('picks the first supported primary language', () => {
    expect(negotiateLocale(['fr-FR', 'es-ES', 'de'])).toBe('es');
    expect(negotiateLocale(['de-CH'])).toBe('de');
    expect(negotiateLocale(['EN-us'])).toBe('en');
  });

  it('falls back to de', () => {
    expect(negotiateLocale(['fr', 'it'])).toBe('de');
    expect(negotiateLocale([])).toBe('de');
  });
});

describe('lookup / interpolate', () => {
  const table = { events: { unregister: 'Turnier abmelden', nested: { deep: 'x' } } };

  it('resolves dotted keys to strings only', () => {
    expect(lookup(table, 'events.unregister')).toBe('Turnier abmelden');
    expect(lookup(table, 'events.nested')).toBeUndefined();
    expect(lookup(table, 'events.missing')).toBeUndefined();
  });

  it('replaces :name placeholders and keeps unknown ones', () => {
    expect(interpolate(':name abmelden? (:other)', { name: 'Jane' })).toBe('Jane abmelden? (:other)');
    expect(interpolate('min :min', { min: 3 })).toBe('min 3');
  });
});

describe('I18n', () => {
  let i18n: I18n;

  beforeEach(() => {
    i18n = TestBed.inject(I18n);
    i18n.use('de', {
      events: { unregister_user_confirm: ':name abmelden?' },
      count: { players: 'Ein Spieler|:count Spieler', three: 'keine|einer|viele' },
    });
  });

  it('translates with params and returns the key when missing', () => {
    expect(i18n.t('events.unregister_user_confirm', { name: 'Jane' })).toBe('Jane abmelden?');
    expect(i18n.t('does.not.exist')).toBe('does.not.exist');
    expect(i18n.locale()).toBe('de');
  });

  it('chooses plural forms', () => {
    expect(i18n.choice('count.players', 1)).toBe('Ein Spieler');
    expect(i18n.choice('count.players', 4)).toBe('4 Spieler');
    expect(i18n.choice('count.three', 0)).toBe('keine');
    expect(i18n.choice('count.three', 1)).toBe('einer');
    expect(i18n.choice('count.three', 7)).toBe('viele');
  });

  it('loads a locale through the loader', async () => {
    await i18n.load('en', async () => ({ nav: { logout: 'Logout' } }));

    expect(i18n.locale()).toBe('en');
    expect(i18n.t('nav.logout')).toBe('Logout');
  });
});

describe('loadLocale', () => {
  it('loads the real German file', async () => {
    const table = await loadLocale('de');

    expect(lookup(table, 'events.unregister')).toBe('Turnier abmelden');
    expect(lookup(table, 'nav.logout')).toBe('Abmelden');
  });
});
