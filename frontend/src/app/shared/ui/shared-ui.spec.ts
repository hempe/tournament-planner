import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { I18n } from '../../core/i18n/i18n';
import { BackLink } from './back-link';
import { Badge } from './badge';
import { DetailList } from './detail-list';
import { Page, PageHeader } from './page';

@Component({
  imports: [BackLink, Badge, DetailList, Page, PageHeader],
  template: `
    <div appPage width="wide" data-testid="page">
      <app-back-link [link]="['/events', 3]" [queryParams]="{ month: '2099-06' }" />
      <app-page-header title="Titel" subtitle="Samstag">
        <div meta><span appBadge variant="secondary" data-testid="badge">Auf Warteliste</span></div>
        <button type="button">Aktion</button>
      </app-page-header>
      <app-page-header title="Ohne" data-testid="plain" />
      <dl appDetailList>
        <div><dt>Menü</dt><dd>Fisch</dd></div>
      </dl>
    </div>
  `,
})
class Host {}

describe('shared UI', () => {
  async function render() {
    TestBed.configureTestingModule({ providers: [provideRouter([])] });
    TestBed.inject(I18n).use('de', { nav: { back: 'Zurück' } });
    const fixture = TestBed.createComponent(Host);
    await fixture.whenStable();
    return fixture.nativeElement as HTMLElement;
  }

  it('back link: chevron + translated text, link with query params, flush left', async () => {
    const el = await render();
    const link = el.querySelector<HTMLAnchorElement>('[data-back-link]')!;

    expect(link.getAttribute('href')).toBe('/events/3?month=2099-06');
    expect(link.textContent?.trim()).toBe('Zurück');
    expect(link.querySelector('ng-icon')?.getAttribute('name')).toBe('lucideChevronLeft');
    expect(link.className).toContain('-ml-1');
  });

  it('page header: subtitle above the h1, meta content and actions', async () => {
    const el = await render();
    const header = el.querySelector('app-page-header')!;

    expect(header.querySelector('[data-subtitle]')?.textContent).toContain('Samstag');
    expect(header.querySelector('h1')?.textContent).toContain('Titel');
    expect(header.querySelector('[data-testid="badge"]')).not.toBeNull();
    expect(header.querySelector('button')?.textContent).toContain('Aktion');
    expect(el.querySelector('[data-testid="plain"] [data-subtitle]')).toBeNull();
  });

  it('page: centred column with the chosen width', async () => {
    const el = await render();
    const page = el.querySelector('[data-testid="page"]')!;

    expect(page.className).toContain('max-w-5xl');
    expect(page.className).toContain('mx-auto');
  });

  it('badge: pill with variant colours and the Hind Guntur top nudge', async () => {
    const el = await render();
    const badge = el.querySelector('[data-testid="badge"]')!;

    expect(badge.getAttribute('data-slot')).toBe('badge');
    expect(badge.className).toContain('rounded-full');
    expect(badge.className).toContain('bg-secondary');
    expect(badge.className).toContain('pt-[0.3em]');
  });

  it('detail list: two columns from sm up', async () => {
    const el = await render();
    const dl = el.querySelector('dl')!;

    expect(dl.getAttribute('data-slot')).toBe('detail-list');
    expect(dl.className).toContain('sm:[&>div]:grid-cols-[10rem_minmax(0,1fr)]');
  });
});
