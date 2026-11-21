import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { SwUpdate } from '@angular/service-worker';
import { App } from './app';
import { I18n } from './core/i18n/i18n';

describe('App', () => {
  it('renders the shell with header and footer', async () => {
    TestBed.configureTestingModule({ providers: [
        provideRouter([]),
        { provide: SwUpdate, useValue: { isEnabled: false, checkForUpdate: vi.fn(), activateUpdate: vi.fn() } },
      ] });
    TestBed.inject(I18n).use('de', { app: { name: 'GOLF EL FARO' }, auth: { login: 'Mitglieder Anmeldung' } });
    const fixture = TestBed.createComponent(App);
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;

    expect(el.querySelector('app-header')?.textContent).toContain('GOLF EL FARO');
    expect(el.querySelector('app-footer')?.textContent).toContain('dev');
  });
});
