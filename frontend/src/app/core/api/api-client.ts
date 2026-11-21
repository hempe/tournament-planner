import { Injectable, InjectionToken, inject } from '@angular/core';

export interface RequestOptions {
  signal?: AbortSignal;
}

export interface FieldError {
  key: string;
  params?: Record<string, string | number>;
}

/** Error from the API. `error` and `fields[*].key` are translation keys. */
export class ApiError extends Error {
  constructor(
    readonly status: number,
    readonly error: string,
    readonly fields: Record<string, FieldError> = {},
    readonly params: Record<string, string | number> = {},
  ) {
    super(`${status} ${error}`);
    this.name = 'ApiError';
  }
}

export interface ApiHooks {
  /** Called for every 401 response (not logged in / session expired). */
  onUnauthorized?: () => void;
  /** Called with the X-App-Version header of every response. */
  onServerVersion?: (version: string) => void;
}

export const API_HOOKS = new InjectionToken<ApiHooks>('API_HOOKS', { factory: () => ({}) });

const XSRF_COOKIE = 'XSRF-TOKEN';
const XSRF_HEADER = 'X-XSRF-TOKEN';

export function readCookie(name: string, cookies: string = document.cookie): string | null {
  for (const part of cookies.split(';')) {
    const [key, ...rest] = part.trim().split('=');
    if (key === name) {
      return decodeURIComponent(rest.join('='));
    }
  }
  return null;
}

/**
 * Transport for the PHP JSON API (same origin, under /api). Feature code uses the
 * generated clients in generated.ts, which call these methods.
 */
@Injectable({ providedIn: 'root' })
export class ApiClient {
  private readonly hooks = inject(API_HOOKS);

  get<T>(path: string, options?: RequestOptions): Promise<T> {
    return this.request<T>('GET', path, undefined, options);
  }

  post<T>(path: string, body?: unknown, options?: RequestOptions): Promise<T> {
    return this.request<T>('POST', path, body, options);
  }

  put<T>(path: string, body?: unknown, options?: RequestOptions): Promise<T> {
    return this.request<T>('PUT', path, body, options);
  }

  patch<T>(path: string, body?: unknown, options?: RequestOptions): Promise<T> {
    return this.request<T>('PATCH', path, body, options);
  }

  delete<T>(path: string, options?: RequestOptions): Promise<T> {
    return this.request<T>('DELETE', path, undefined, options);
  }

  private async request<T>(
    method: string,
    path: string,
    body: unknown,
    options: RequestOptions = {},
    retried = false,
  ): Promise<T> {
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
    }
    if (method !== 'GET') {
      const token = readCookie(XSRF_COOKIE);
      if (token !== null) {
        headers[XSRF_HEADER] = token;
      }
    }

    let response: Response;
    try {
      response = await fetch(`/api${path}`, {
        method,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
        credentials: 'same-origin',
        signal: options.signal,
      });
    } catch (error) {
      if ((error as Error)?.name === 'AbortError') {
        throw error;
      }
      throw new ApiError(0, 'errors.network');
    }

    const version = response.headers.get('X-App-Version');
    if (version !== null) {
      this.hooks.onServerVersion?.(version);
    }

    if (response.status === 204) {
      return undefined as T;
    }

    const data: unknown = await response.json().catch(() => null);
    if (response.ok) {
      return data as T;
    }

    const payload = (data ?? {}) as {
      error?: string;
      fields?: Record<string, FieldError>;
      params?: Record<string, string | number>;
    };
    const error = new ApiError(
      response.status,
      payload.error ?? 'errors.general',
      payload.fields ?? {},
      payload.params ?? {},
    );

    // The 403 response carries a fresh XSRF cookie; retry once with it.
    if (response.status === 403 && error.error === 'errors.csrf' && !retried) {
      return this.request<T>(method, path, body, options, true);
    }
    if (response.status === 401) {
      this.hooks.onUnauthorized?.();
    }
    throw error;
  }
}
