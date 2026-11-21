import { TestBed } from '@angular/core/testing';
import { I18n } from '../i18n/i18n';
import { Notify, TOASTER } from './notify';

describe('Notify', () => {
  it('translates keys before showing toasts', () => {
    const toaster = { success: vi.fn(), error: vi.fn(), info: vi.fn() };
    TestBed.configureTestingModule({ providers: [{ provide: TOASTER, useValue: toaster }] });
    TestBed.inject(I18n).use('de', {
      auth: { logout_success: 'Erfolgreich abgemeldet' },
      events: { unregister_user_confirm: ':name abmelden?' },
    });
    const notify = TestBed.inject(Notify);

    notify.success('auth.logout_success');
    notify.error('events.unregister_user_confirm', { name: 'Jane' });

    expect(toaster.success).toHaveBeenCalledWith('Erfolgreich abgemeldet');
    expect(toaster.error).toHaveBeenCalledWith('Jane abmelden?');
  });

  it('shows a persistent translated toast with an action', () => {
    const toaster = { success: vi.fn(), error: vi.fn(), info: vi.fn(), persistent: vi.fn() };
    TestBed.configureTestingModule({ providers: [{ provide: TOASTER, useValue: toaster }] });
    TestBed.inject(I18n).use('de', { update: { available: 'Neue Version', reload: 'Neu laden' } });
    const onAction = vi.fn();

    TestBed.inject(Notify).persistent('update.available', 'update.reload', onAction);

    expect(toaster.persistent).toHaveBeenCalledWith('Neue Version', { label: 'Neu laden', onClick: onAction });
  });
});
