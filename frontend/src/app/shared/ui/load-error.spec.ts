import { Component, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { ApiError } from '../../core/api/api-client';
import { I18n } from '../../core/i18n/i18n';
import { LoadError } from './load-error';

@Component({
  imports: [LoadError],
  template: `<app-load-error [error]="error()" notFoundKey="events.not_found" [showHome]="home()" (retry)="retries = retries + 1" />`,
})
class Host {
  readonly error = signal<unknown>(null);
  readonly home = signal(false);
  retries = 0;
}

describe('LoadError', () => {
  async function render(error: unknown, home = false) {
    TestBed.configureTestingModule({ providers: [provideRouter([])] });
    TestBed.inject(I18n).use('de', {
      errors: { network: 'Keine Verbindung zum Server.', general: 'Ein Fehler ist aufgetreten.' },
      events: { not_found: 'Anlass nicht gefunden' },
      navigation: { try_again: 'Nochmal versuchen', home: 'Zur Startseite' },
    });
    const fixture = TestBed.createComponent(Host);
    fixture.componentInstance.error.set(error);
    fixture.componentInstance.home.set(home);
    await fixture.whenStable();
    return { fixture, el: fixture.nativeElement as HTMLElement };
  }

  it('offers a retry for connection problems and emits it', async () => {
    const { fixture, el } = await render(new ApiError(0, 'errors.network'));

    expect(el.querySelector('[role="alert"]')?.textContent).toContain('Keine Verbindung zum Server.');
    el.querySelector<HTMLButtonElement>('[data-retry]')!.click();
    expect(fixture.componentInstance.retries).toBe(1);
  });

  it('offers a retry for server errors and unknown errors', async () => {
    expect((await render(new ApiError(500, 'errors.server_error'))).el.querySelector('[data-retry]')).not.toBeNull();
    TestBed.resetTestingModule();
    expect((await render(new Error('boom'))).el.textContent).toContain('Ein Fehler ist aufgetreten.');
  });

  it('shows the not-found text without retry for 404, with a home link when asked', async () => {
    const { el } = await render(new ApiError(404, 'events.not_found'), true);

    expect(el.textContent).toContain('Anlass nicht gefunden');
    expect(el.querySelector('[data-retry]')).toBeNull();
    expect(el.querySelector('a')?.getAttribute('href')).toBe('/');
  });

  it('does not offer a retry for 403', async () => {
    const { el } = await render(new ApiError(403, 'errors.forbidden'));

    expect(el.querySelector('[data-retry]')).toBeNull();
  });
});
