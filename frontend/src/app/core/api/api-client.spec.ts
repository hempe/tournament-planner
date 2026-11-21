import { TestBed } from '@angular/core/testing';
import { API_HOOKS, ApiClient, ApiError, readCookie } from './api-client';

function json(status: number, body: unknown, headers: Record<string, string> = {}): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json', ...headers },
  });
}

describe('ApiClient', () => {
  let fetchMock: ReturnType<typeof vi.fn>;
  let hooks: { onUnauthorized: ReturnType<typeof vi.fn>; onServerVersion: ReturnType<typeof vi.fn> };
  let api: ApiClient;

  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    hooks = { onUnauthorized: vi.fn(), onServerVersion: vi.fn() };
    TestBed.configureTestingModule({ providers: [{ provide: API_HOOKS, useValue: hooks }] });
    api = TestBed.inject(ApiClient);
    document.cookie = 'XSRF-TOKEN=tok%20123';
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
  });

  function lastCall(): { url: string; init: RequestInit } {
    const [url, init] = fetchMock.mock.calls.at(-1)!;
    return { url, init };
  }

  it('GETs under /api and returns the parsed body', async () => {
    fetchMock.mockResolvedValue(json(200, { user: null }));

    await expect(api.get('/auth/me')).resolves.toEqual({ user: null });

    const { url, init } = lastCall();
    expect(url).toBe('/api/auth/me');
    expect(init.method).toBe('GET');
    expect(init.credentials).toBe('same-origin');
    expect((init.headers as Record<string, string>)['X-XSRF-TOKEN']).toBeUndefined();
  });

  it('POSTs JSON with the XSRF header from the cookie', async () => {
    fetchMock.mockResolvedValue(json(200, { ok: true }));

    await api.post('/auth/login', { username: 'a', password: 'b' });

    const { init } = lastCall();
    const headers = init.headers as Record<string, string>;
    expect(init.body).toBe('{"username":"a","password":"b"}');
    expect(headers['Content-Type']).toBe('application/json');
    expect(headers['X-XSRF-TOKEN']).toBe('tok 123');
  });

  it('returns undefined for 204', async () => {
    fetchMock.mockResolvedValue(new Response(null, { status: 204 }));

    await expect(api.post('/auth/logout')).resolves.toBeUndefined();
  });

  it('throws ApiError with key and field errors', async () => {
    fetchMock.mockResolvedValue(
      json(422, { error: 'validation.failed', fields: { username: { key: 'validation.required' } } }),
    );

    const error = await api.post('/auth/login', {}).catch((e: unknown) => e);

    expect(error).toBeInstanceOf(ApiError);
    expect((error as ApiError).status).toBe(422);
    expect((error as ApiError).error).toBe('validation.failed');
    expect((error as ApiError).fields).toEqual({ username: { key: 'validation.required' } });
  });

  it('reads params of an error', async () => {
    fetchMock.mockResolvedValue(
      json(409, { error: 'social_events.menu_in_use', params: { menu: 'Fisch', count: 3 } }),
    );

    const error = (await api.delete('/x').catch((e: unknown) => e)) as ApiError;

    expect(error.params).toEqual({ menu: 'Fisch', count: 3 });
  });

  it('defaults params to an empty object', async () => {
    fetchMock.mockResolvedValue(json(404, { error: 'errors.not_found' }));

    const error = (await api.get('/x').catch((e: unknown) => e)) as ApiError;

    expect(error.params).toEqual({});
  });

  it('uses errors.general when the error body is not JSON', async () => {
    fetchMock.mockResolvedValue(new Response('<html>oops</html>', { status: 500 }));

    const error = (await api.get('/x').catch((e: unknown) => e)) as ApiError;

    expect(error.error).toBe('errors.general');
  });

  it('calls onUnauthorized on 401', async () => {
    fetchMock.mockResolvedValue(json(401, { error: 'auth.login_required' }));

    await expect(api.get('/events')).rejects.toBeInstanceOf(ApiError);
    expect(hooks.onUnauthorized).toHaveBeenCalledTimes(1);
  });

  it('retries once after a CSRF rejection', async () => {
    fetchMock
      .mockResolvedValueOnce(json(403, { error: 'errors.csrf' }))
      .mockResolvedValueOnce(json(200, { ok: true }));

    await expect(api.post('/auth/login', {})).resolves.toEqual({ ok: true });
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });

  it('gives up after a second CSRF rejection', async () => {
    fetchMock.mockImplementation(async () => json(403, { error: 'errors.csrf' }));

    await expect(api.post('/auth/login', {})).rejects.toMatchObject({ status: 403, error: 'errors.csrf' });
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });

  it('reports the server version header', async () => {
    fetchMock.mockResolvedValue(json(200, {}, { 'X-App-Version': '2026-12-04-a1b2c3d' }));

    await api.get('/auth/me');

    expect(hooks.onServerVersion).toHaveBeenCalledWith('2026-12-04-a1b2c3d');
  });

  it('maps network failures to errors.network', async () => {
    fetchMock.mockRejectedValue(new TypeError('Failed to fetch'));

    await expect(api.get('/auth/me')).rejects.toMatchObject({ status: 0, error: 'errors.network' });
  });

  it('rethrows aborts unchanged', async () => {
    const abort = new DOMException('aborted', 'AbortError');
    fetchMock.mockRejectedValue(abort);

    await expect(api.get('/auth/me')).rejects.toBe(abort);
  });
});

describe('readCookie', () => {
  it('finds and decodes a cookie', () => {
    expect(readCookie('B', 'A=1; B=x%3Dy; C=3')).toBe('x=y');
    expect(readCookie('D', 'A=1')).toBeNull();
  });
});
