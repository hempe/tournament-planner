import { TestBed } from '@angular/core/testing';
import { APP_VERSION, VersionService } from './version';

describe('VersionService', () => {
  function service(clientVersion: string): VersionService {
    TestBed.configureTestingModule({ providers: [{ provide: APP_VERSION, useValue: clientVersion }] });
    return TestBed.inject(VersionService);
  }

  it('is not outdated until the server reports a different release', () => {
    const version = service('2026-12-04-a1b2c3d');
    expect(version.outdated()).toBe(false);

    version.reportServerVersion('2026-12-04-a1b2c3d');
    expect(version.outdated()).toBe(false);

    version.reportServerVersion('2027-01-10-ffff000');
    expect(version.outdated()).toBe(true);
  });

  it('never reports outdated in development', () => {
    const version = service('dev');
    version.reportServerVersion('2027-01-10-ffff000');
    expect(version.outdated()).toBe(false);
  });

  it('defaults the build version to "dev" when no build constant is defined', () => {
    TestBed.configureTestingModule({});
    expect(TestBed.inject(APP_VERSION)).toBe('dev');
  });
});
