import { TestBed } from '@angular/core/testing';
import { ApiError } from '../api/api-client';
import { Notify } from '../feedback/notify';
import { AppErrorHandler } from './app-error-handler';

describe('AppErrorHandler', () => {
  let notify: { error: ReturnType<typeof vi.fn> };
  let handler: AppErrorHandler;

  beforeEach(() => {
    notify = { error: vi.fn() };
    TestBed.configureTestingModule({ providers: [AppErrorHandler, { provide: Notify, useValue: notify }] });
    handler = TestBed.inject(AppErrorHandler);
    vi.spyOn(console, 'error').mockImplementation(() => undefined);
  });

  afterEach(() => vi.restoreAllMocks());

  it('shows the API error key', () => {
    handler.handleError(new ApiError(409, 'events.full'));
    expect(notify.error).toHaveBeenCalledWith('events.full');
  });

  it('stays quiet for 401 (the app redirects to login)', () => {
    handler.handleError(new ApiError(401, 'auth.login_required'));
    expect(notify.error).not.toHaveBeenCalled();
  });

  it('stays quiet for aborted requests', () => {
    handler.handleError(new DOMException('aborted', 'AbortError'));
    expect(notify.error).not.toHaveBeenCalled();
  });

  it('shows a generic message for unexpected errors and logs them', () => {
    const boom = new Error('boom');
    handler.handleError(boom);
    expect(notify.error).toHaveBeenCalledWith('errors.general');
    expect(console.error).toHaveBeenCalledWith(boom);
  });
});
