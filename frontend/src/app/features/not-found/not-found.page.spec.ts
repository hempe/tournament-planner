import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { I18n } from '../../core/i18n/i18n';
import { NotFoundPage } from './not-found.page';

describe('NotFoundPage', () => {
  it('explains and links home', async () => {
    TestBed.configureTestingModule({ providers: [provideRouter([])] });
    TestBed.inject(I18n).use('de', {
      errors: { '404_title': 'Seite nicht gefunden', not_found: 'Die angeforderte Seite wurde nicht gefunden.' },
      navigation: { home: 'Zur Startseite' },
    });
    const fixture = TestBed.createComponent(NotFoundPage);
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;

    expect(el.textContent).toContain('Seite nicht gefunden');
    expect(el.querySelector('a')?.getAttribute('href')).toBe('/');
  });
});
