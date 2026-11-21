import { TestBed } from '@angular/core/testing';
import { SwUpdate } from '@angular/service-worker';
import { I18n } from '../i18n/i18n';
import { TOASTER } from '../feedback/notify';
import { APP_VERSION, VersionService } from '../version';
import { RELOAD, UpdateService } from './update.service';

describe('UpdateService', () => {
  function setup(sw: { isEnabled: boolean; checkForUpdate?: () => Promise<boolean> }, clientVersion = 'dev') {
    const swUpdate = {
      isEnabled: sw.isEnabled,
      checkForUpdate: vi.fn(sw.checkForUpdate ?? (() => Promise.resolve(false))),
      activateUpdate: vi.fn(() => Promise.resolve(true)),
    };
    const toaster = { success: vi.fn(), error: vi.fn(), info: vi.fn(), persistent: vi.fn() };
    const reload = vi.fn();
    TestBed.configureTestingModule({
      providers: [
        { provide: SwUpdate, useValue: swUpdate },
        { provide: TOASTER, useValue: toaster },
        { provide: RELOAD, useValue: reload },
        { provide: APP_VERSION, useValue: clientVersion },
      ],
    });
    TestBed.inject(I18n).use('de', { update: { available: 'Neue Version', reload: 'Neu laden' } });
    return { swUpdate, toaster, reload, service: TestBed.inject(UpdateService) };
  }

  it('shows the update toast once, however often it checks', async () => {
    const { service, toaster, swUpdate } = setup({ isEnabled: true, checkForUpdate: () => Promise.resolve(true) });

    await service.check();
    await service.check();

    expect(swUpdate.checkForUpdate).toHaveBeenCalledTimes(2);
    expect(toaster.persistent).toHaveBeenCalledTimes(1);
    expect(toaster.persistent.mock.calls[0][0]).toBe('Neue Version');
    expect(toaster.persistent.mock.calls[0][1].label).toBe('Neu laden');
  });

  it('does not toast when no update is found', async () => {
    const { service, toaster } = setup({ isEnabled: true });
    await service.check();
    expect(toaster.persistent).not.toHaveBeenCalled();
  });

  it('activates the update before reloading', async () => {
    const { service, toaster, swUpdate, reload } = setup({ isEnabled: true, checkForUpdate: () => Promise.resolve(true) });
    await service.check();

    await toaster.persistent.mock.calls[0][1].onClick();

    expect(swUpdate.activateUpdate).toHaveBeenCalled();
    expect(reload).toHaveBeenCalled();
  });

  it('does nothing when the service worker is disabled and the version is current', async () => {
    const { service, toaster, swUpdate } = setup({ isEnabled: false });
    await service.check();
    TestBed.tick();
    expect(swUpdate.checkForUpdate).not.toHaveBeenCalled();
    expect(toaster.persistent).not.toHaveBeenCalled();
  });

  it('offers a plain reload when the service worker is disabled and the server is newer', () => {
    const { toaster, reload } = setup({ isEnabled: false }, '2026-12-04-a1b2c3d');
    TestBed.inject(UpdateService);
    TestBed.inject(VersionService).reportServerVersion('2027-01-10-ffff000');
    TestBed.tick();

    expect(toaster.persistent).toHaveBeenCalledTimes(1);
    toaster.persistent.mock.calls[0][1].onClick();
    expect(reload).toHaveBeenCalled();
  });
});
