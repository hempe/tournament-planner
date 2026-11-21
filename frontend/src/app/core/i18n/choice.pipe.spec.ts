import { TestBed } from '@angular/core/testing';
import { ChoicePipe } from './choice.pipe';
import { I18n } from './i18n';

describe('ChoicePipe', () => {
  it('picks the singular or plural form and fills :count', () => {
    TestBed.inject(I18n).use('de', { s: { available: '1 Platz verfügbar|:count Plätze verfügbar' } });
    const pipe = TestBed.runInInjectionContext(() => new ChoicePipe());

    expect(pipe.transform('s.available', 1)).toBe('1 Platz verfügbar');
    expect(pipe.transform('s.available', 3)).toBe('3 Plätze verfügbar');
  });
});
