import { TestBed } from '@angular/core/testing';
import { ApiClient, ApiError } from '../api/api-client';
import { Notify } from '../feedback/notify';
import { APP_VERSION } from '../version';
import { AppErrorHandler } from './app-error-handler';

describe('AppErrorHandler', () => {
  let notify: { error: ReturnType<typeof vi.fn> };
  let api: { post: ReturnType<typeof vi.fn> };
  let handler: AppErrorHandler;

  beforeEach(() => {
    notify = { error: vi.fn() };
    api = { post: vi.fn().mockResolvedValue(undefined) };
    TestBed.configureTestingModule({
      providers: [
        AppErrorHandler,
        { provide: Notify, useValue: notify },
        { provide: ApiClient, useValue: api },
        { provide: APP_VERSION, useValue: '1.2.3' },
      ],
    });
    handler = TestBed.inject(AppErrorHandler);
    vi.spyOn(console, 'error').mockImplementation(() => undefined);
  });

  afterEach(() => vi.restoreAllMocks());

  it('shows the API error key', () => {
    handler.handleError(new ApiError(409, 'events.full'));
    expect(notify.error).toHaveBeenCalledWith('events.full', {});
    expect(api.post).not.toHaveBeenCalled();
  });

  it('passes error params to the notification', () => {
    handler.handleError(new ApiError(409, 'social_events.menu_in_use', {}, { menu: 'Fisch', count: 3 }));
    expect(notify.error).toHaveBeenCalledWith('social_events.menu_in_use', { menu: 'Fisch', count: 3 });
  });

  it('stays quiet for 401 (the app redirects to login)', () => {
    handler.handleError(new ApiError(401, 'auth.login_required'));
    expect(notify.error).not.toHaveBeenCalled();
    expect(api.post).not.toHaveBeenCalled();
  });

  it('stays quiet for aborted requests', () => {
    handler.handleError(new DOMException('aborted', 'AbortError'));
    expect(notify.error).not.toHaveBeenCalled();
    expect(api.post).not.toHaveBeenCalled();
  });

  it('shows a generic message for unexpected errors and logs them', () => {
    const boom = new Error('boom');
    handler.handleError(boom);
    expect(notify.error).toHaveBeenCalledWith('errors.general');
    expect(console.error).toHaveBeenCalledWith(boom);
  });

  it('reports an unexpected error once with message, stack, url and version', () => {
    const boom = new Error('boom');
    handler.handleError(boom);

    expect(api.post).toHaveBeenCalledTimes(1);
    expect(api.post).toHaveBeenCalledWith(
      '/client-errors',
      { message: 'boom', stack: boom.stack, url: window.location.href, appVersion: '1.2.3' },
      undefined,
    );
  });

  it('reports non-Error values as text without a stack', () => {
    handler.handleError('just a string');

    expect(api.post.mock.calls[0][1]).toMatchObject({ message: 'just a string', stack: null });
  });

  it('does not report the same message twice', () => {
    handler.handleError(new Error('same'));
    handler.handleError(new Error('same'));

    expect(api.post).toHaveBeenCalledTimes(1);
    expect(notify.error).toHaveBeenCalledTimes(2);
  });

  it('sends at most 5 reports per page load', () => {
    for (let i = 0; i < 8; i++) {
      handler.handleError(new Error(`error ${i}`));
    }

    expect(api.post).toHaveBeenCalledTimes(5);
  });

  it('swallows a failing report (rejected or throwing) without recursion', async () => {
    api.post.mockRejectedValueOnce(new ApiError(500, 'errors.server_error'));
    api.post.mockImplementationOnce(() => {
      throw new Error('sync failure');
    });

    expect(() => handler.handleError(new Error('one'))).not.toThrow();
    expect(() => handler.handleError(new Error('two'))).not.toThrow();
    await Promise.resolve();

    expect(api.post).toHaveBeenCalledTimes(2);
    expect(notify.error).toHaveBeenCalledTimes(2);
  });
});
