# UI Refactor — Plan 2: Frontend Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** An Angular 22 app in `frontend/` that boots, loads translations, knows the logged-in user, can log in and out against the plan-1 API, and talks to PHP exclusively through a TypeScript client **generated from the PHP DTOs and route attributes**.

**Architecture:** Zoneless Angular 22 (standalone components, signals, signal forms), Tailwind 4, Spartan UI components copied into `src/app/ui/` (we own them), no RxJS and no `HttpClient` in app code (ESLint-enforced). `ApiClient` is a small `fetch` wrapper returning Promises; `bin/generate-api.php` turns `#[Returns]`/`#[Body]` attributes and DTO classes into `src/app/core/api/generated.ts`, and a PHPUnit test fails when the committed file is stale.

**Tech Stack:** Angular 22.3 (`@angular/build`, Vitest 5 + jsdom), Tailwind 4.3 via `@tailwindcss/postcss`, Spartan UI 1.10 (`@spartan-ng/brain` pinned exactly; CLI only used temporarily to copy components), `@ng-icons/lucide`, angular-eslint, Node 26 (dev only), PHP 8.5 for the generator.

**Spec:** `docs/superpowers/specs/2026-11-20-ui-refactor-design.md` (§2, §3, §4.3–4.7, §5)

**Series:** Plan 2 of 5 — (1) API foundation ✅, (2) frontend foundation, (3) member features, (4) admin features, (5) build/PWA/E2E/cut-over.

**Verified beforehand** (scratch project in `/tmp`, 2026-11-20): `ng new` 22.3 is zoneless and uses Vitest by default; `ng add tailwindcss` and `ng add angular-eslint` work; the Spartan CLI runs non-interactively when `components.json` exists; Spartan components build and test; `` import(`../i18n/${locale}.json`) `` produces one lazy chunk per language; signal forms (`@angular/forms/signals`, `@publicApi 22.0`) work with `[formField]`.

**Known jsdom quirk:** clicking a submit button does **not** submit a form in jsdom. In component tests, submit with `form.dispatchEvent(new Event('submit', { cancelable: true }))`.

---

## Conventions for every task

- PHP: `composer test` (baseline after plan 1: `OK, but there were issues! Tests: 506, … Warnings: 3, Deprecations: 9` — warnings/deprecations are pre-existing). Single test: `vendor/bin/phpunit --filter <Name>`.
- Frontend (run inside `frontend/`): `npm test` (Vitest, non-watch), `npm run lint`, `npx ng build`. Single spec file: `npx ng test --watch=false --include src/app/core/api/api-client.spec.ts`.
- App code must not import `rxjs` or `@angular/common/http` (lint fails).
- Commit after each task with the given message; end every message with
  `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`
- npm 12 prints `install-scripts … blocked` warnings; they are expected and harmless (esbuild ships prebuilt binaries via optional dependencies).

## File map

| File | Responsibility |
|---|---|
| `frontend/` (workspace files) | Angular CLI workspace `tournament-planner-ui` |
| `frontend/components.json` | Spartan CLI config (`src/app/ui`, alias `@ui`) |
| `frontend/proxy.conf.json` | dev server: `/api` → `http://localhost:5000` |
| `frontend/eslint.config.js` | lint rules incl. the RxJS/HttpClient ban |
| `frontend/src/styles.css` | Tailwind + Spartan theme + club colours |
| `frontend/src/app/ui/**` | Spartan components (generated once, then ours) |
| `frontend/src/app/core/api/api-client.ts` | `fetch` transport, `ApiError`, `API_HOOKS` |
| `frontend/src/app/core/api/generated.ts` | **generated** — DTO interfaces + per-controller clients |
| `frontend/src/app/core/i18n/i18n.ts` | locale negotiation, lookup, interpolation, `I18n` service |
| `frontend/src/app/core/i18n/translate.pipe.ts` | `{{ 'key' \| t }}` |
| `frontend/src/app/core/auth/session.store.ts` | current user as signals, login/logout |
| `frontend/src/app/core/auth/guards.ts` | `authGuard`, `adminGuard`, `guestGuard` |
| `frontend/src/app/core/feedback/confirm.service.ts` | promise-based confirmations |
| `frontend/src/app/core/feedback/confirm-dialog.ts` | renders the pending confirmation |
| `frontend/src/app/core/feedback/notify.ts` | translated toasts |
| `frontend/src/app/core/errors/app-error-handler.ts` | global error → toast |
| `frontend/src/app/core/version.ts` | `APP_VERSION`, `VersionService` |
| `frontend/src/app/core/init.ts` | `initializeApp()`, `apiHooks()` |
| `frontend/src/app/core/layout/header.ts`, `footer.ts` | shell |
| `frontend/src/app/features/home/home.page.ts` | `/` placeholder (replaced in plan 3) |
| `frontend/src/app/features/login/login.page.ts` | `/login` |
| `frontend/src/app/features/not-found/not-found.page.ts` | `**` |
| `frontend/scripts/check-i18n-keys.mts` | fails on unknown translation keys in `src/app` |
| `src/Core/Attributes/Returns.php`, `Body.php`, `ArrayOf.php` | API contract attributes |
| `src/Api/Dto/LoginRequest.php` | login body DTO |
| `src/Core/Api/TypeScriptGenerator.php` | PHP → TS generator |
| `bin/generate-api.php` | CLI: write or `--check` `generated.ts` |
| `run.sh` | starts PHP API (:5000) + Angular dev server (:4200) |
| `docs/FRONTEND.md` | frontend guide |

---

### Task 1: Angular workspace scaffold

**Files:** Create `frontend/**` (keeping `frontend/src/i18n/*.json`).

- [ ] **Step 1: Generate the workspace around the existing translation files**

```bash
cd /home/hempe/git/tournament-planner
mkdir -p /tmp/tp-i18n && mv frontend/src/i18n/*.json /tmp/tp-i18n/ && rm -rf frontend
npx -y @angular/cli@22.3.0 new tournament-planner-ui --directory=frontend --defaults --skip-git --style=css --ssr=false --routing=true
mkdir -p frontend/src/i18n && mv /tmp/tp-i18n/*.json frontend/src/i18n/
git status --short frontend/src/i18n
```
Expected: the last command prints nothing (translations unchanged).

- [ ] **Step 2: Tailwind, ESLint, Spartan**

```bash
cd frontend
npx ng add tailwindcss --skip-confirmation
npx ng add angular-eslint --skip-confirmation
npm i -D @spartan-ng/cli@1.10.0
npm i @spartan-ng/brain@1.10.0 @angular/cdk@22.3.0
printf '{"componentsPath":"src/app/ui","style":"vega","importAlias":"@ui"}\n' > components.json
npx ng g @spartan-ng/cli:ui-theme --theme=neutral --project=tournament-planner-ui --defaults
for c in button input label field card alert-dialog sonner spinner; do npx ng g @spartan-ng/cli:ui $c --defaults; done
npm uninstall @spartan-ng/cli
npm pkg set "dependencies.@spartan-ng/brain=1.10.0"
npm install
ls src/app/ui
```
Expected `ls`: `alert-dialog button card field input label separator sonner spinner utils`.

Why uninstall the CLI: it pulls in Nx and many transitive packages; the copied components don't need it. `docs/FRONTEND.md` (Task 11) documents how to add more components later. `@spartan-ng/brain` is pinned to an exact version because Spartan ships breaking changes with migration generators.

- [ ] **Step 3: Club theme** — append to `frontend/src/styles.css`:

```css

/* Club theme (GOLF EL FARO) — overrides the generated neutral theme. Light only. */
:root {
  --font-sans: 'Hind Guntur', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  --foreground: #333333;
  --primary: #87b723;
  --primary-foreground: #ffffff;
  --ring: #87b723;
  --destructive: #b6334d;
  --social: #2272b6;
}
```

- [ ] **Step 4: `frontend/src/index.html`** — replace the whole file:

```html
<!doctype html>
<html lang="de">
  <head>
    <meta charset="utf-8" />
    <title>GOLF EL FARO</title>
    <base href="/" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Hind+Guntur:wght@300;400;500;600;700&display=swap"
      rel="stylesheet"
    />
  </head>
  <body class="min-h-dvh bg-background text-foreground antialiased">
    <app-root></app-root>
  </body>
</html>
```

- [ ] **Step 5: Minimal app component** — delete `src/app/app.html` and `src/app/app.css`; replace `src/app/app.ts`:

```ts
import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet],
  template: `<router-outlet />`,
})
export class App {}
```

Replace `src/app/app.spec.ts`:

```ts
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { App } from './app';

describe('App', () => {
  it('creates the app', () => {
    TestBed.configureTestingModule({ providers: [provideRouter([])] });
    const fixture = TestBed.createComponent(App);
    expect(fixture.componentInstance).toBeTruthy();
  });
});
```

- [ ] **Step 6: Dev proxy and scripts**

`frontend/proxy.conf.json`:

```json
{
  "/api": {
    "target": "http://localhost:5000",
    "secure": false
  }
}
```

```bash
node -e "const fs=require('fs');const f='angular.json';const j=JSON.parse(fs.readFileSync(f,'utf8'));const s=j.projects['tournament-planner-ui'].architect.serve;s.options={...(s.options||{}),proxyConfig:'proxy.conf.json'};fs.writeFileSync(f,JSON.stringify(j,null,2)+'\n')"
npm pkg set scripts.test="ng test --watch=false"
```

- [ ] **Step 7: Lint rules** — in `frontend/eslint.config.js`, add `{ ignores: ['src/app/ui/**'] },` as the **first** element of the `defineConfig([...])` array, and add to the `rules` object of the `files: ['**/*.ts']` block:

```js
      'no-restricted-imports': [
        'error',
        {
          paths: [
            { name: 'rxjs', message: 'No RxJS in app code — use signals, resource() and Promises (docs/FRONTEND.md).' },
            { name: 'rxjs/operators', message: 'No RxJS in app code.' },
            { name: '@angular/common/http', message: 'Use ApiClient / the generated API (src/app/core/api) instead of HttpClient.' },
          ],
        },
      ],
```

- [ ] **Step 8: Verify the lint rule bites**

```bash
printf "import { of } from 'rxjs';\nexport const x = of(1);\n" > src/app/lint-probe.ts
npm run lint; echo "exit=$?"
rm src/app/lint-probe.ts
npm run lint
```
Expected: first run reports `No RxJS in app code` and `exit=1`; second run passes.

- [ ] **Step 9: Verify build and tests**

Run: `npx ng build` → Expected: `Application bundle generation complete.`
Run: `npm test` → Expected: `Tests  1 passed`.
Run (repo root): `composer test` → Expected: baseline (506 tests).

- [ ] **Step 10: Commit**

```bash
cd /home/hempe/git/tournament-planner
git add frontend
git status --short | grep -v '^A ' ; echo "(above: anything not staged — should be empty)"
git commit -m "feat(frontend): Scaffold Angular 22 workspace with Tailwind, Spartan UI and lint rules"
```

---

### Task 2: `ApiClient`

**Files:**
- Create: `frontend/src/app/core/api/api-client.ts`
- Test: `frontend/src/app/core/api/api-client.spec.ts`

- [ ] **Step 1: Write the failing tests** — `api-client.spec.ts`:

```ts
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
```

- [ ] **Step 2: Run to verify it fails**

Run: `npx ng test --watch=false --include src/app/core/api/api-client.spec.ts`
Expected: FAIL — cannot resolve `./api-client`.

- [ ] **Step 3: Implement** — `api-client.ts`:

```ts
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

    const payload = (data ?? {}) as { error?: string; fields?: Record<string, FieldError> };
    const error = new ApiError(response.status, payload.error ?? 'errors.general', payload.fields ?? {});

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
```

- [ ] **Step 4: Run to verify it passes**

Run: `npx ng test --watch=false --include src/app/core/api/api-client.spec.ts` → Expected: 12 passed.
Run: `npm run lint` → Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/app/core/api
git commit -m "feat(frontend): Add fetch-based ApiClient with XSRF, error mapping and hooks"
```

---

### Task 3: API contract attributes on the PHP side

**Files:**
- Create: `src/Core/Attributes/Returns.php`, `src/Core/Attributes/Body.php`, `src/Core/Attributes/ArrayOf.php`, `src/Api/Dto/LoginRequest.php`
- Modify: `src/Controllers/Api/AuthController.php`
- Test: `tests/Integration/Api/ApiContractAttributesTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TP\Api\Dto\LoginRequest;
use TP\Api\Dto\SessionDto;
use TP\Controllers\Api\AuthController;
use TP\Core\Attributes\Body;
use TP\Core\Attributes\Returns;

class ApiContractAttributesTest extends TestCase
{
    private function attribute(string $method, string $attribute): ?object
    {
        $found = (new ReflectionMethod(AuthController::class, $method))->getAttributes($attribute);
        return $found === [] ? null : $found[0]->newInstance();
    }

    public function testMeDeclaresSessionDto(): void
    {
        $returns = $this->attribute('me', Returns::class);

        $this->assertSame(SessionDto::class, $returns->type);
        $this->assertFalse($returns->list);
        $this->assertFalse($returns->nullable);
    }

    public function testLoginDeclaresBodyAndResult(): void
    {
        $this->assertSame(LoginRequest::class, $this->attribute('login', Body::class)->type);
        $this->assertSame(SessionDto::class, $this->attribute('login', Returns::class)->type);
    }

    public function testLogoutReturnsNothing(): void
    {
        $this->assertNull($this->attribute('logout', Returns::class));
    }

    public function testLoginRequestShape(): void
    {
        $request = new LoginRequest('jane', 'secret');

        $this->assertSame(['username' => 'jane', 'password' => 'secret'], get_object_vars($request));
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter ApiContractAttributesTest` → Expected: FAIL (class `Returns` not found).

- [ ] **Step 3: Implement**

`src/Core/Attributes/Returns.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Core\Attributes;

use Attribute;

/**
 * Response type of an API route, used by the TypeScript generator.
 * No #[Returns] means the route answers 204 No Content.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Returns
{
    /** @param class-string|'string'|'int'|'float'|'bool' $type */
    public function __construct(
        public readonly string $type,
        public readonly bool $list = false,
        public readonly bool $nullable = false,
    ) {
    }
}
```

`src/Core/Attributes/Body.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Core\Attributes;

use Attribute;

/** Request body DTO of an API route, used by the TypeScript generator. */
#[Attribute(Attribute::TARGET_METHOD)]
final class Body
{
    /** @param class-string $type */
    public function __construct(
        public readonly string $type,
    ) {
    }
}
```

`src/Core/Attributes/ArrayOf.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Core\Attributes;

use Attribute;

/** Element type of an array property on a DTO (PHP has no generics). */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class ArrayOf
{
    /** @param class-string|'string'|'int'|'float'|'bool' $type */
    public function __construct(
        public readonly string $type,
    ) {
    }
}
```

`src/Api/Dto/LoginRequest.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Api\Dto;

final readonly class LoginRequest
{
    public function __construct(
        public string $username,
        public string $password,
    ) {
    }
}
```

In `src/Controllers/Api/AuthController.php`:
- add imports `use TP\Api\Dto\LoginRequest;`, `use TP\Core\Attributes\Body;`, `use TP\Core\Attributes\Returns;`
- add `#[Returns(SessionDto::class)]` under `#[Get('/me')]`
- add `#[Body(LoginRequest::class)]` and `#[Returns(SessionDto::class)]` under `#[Post('/login')]`
- leave `logout` without `#[Returns]`.

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter ApiContractAttributesTest` → Expected: 4 tests OK.
Run: `composer test` → Expected: baseline + 4.

- [ ] **Step 5: Commit**

```bash
git add src/Core/Attributes/Returns.php src/Core/Attributes/Body.php src/Core/Attributes/ArrayOf.php src/Api/Dto/LoginRequest.php src/Controllers/Api/AuthController.php tests/Integration/Api/ApiContractAttributesTest.php
git commit -m "feat(api): Add #[Returns], #[Body] and #[ArrayOf] contract attributes"
```

---

### Task 4: TypeScript generator

**Files:**
- Create: `src/Core/Api/TypeScriptGenerator.php`
- Test: `tests/Integration/Core/Api/TypeScriptGeneratorTest.php`

- [ ] **Step 1: Write the failing test** — `tests/Integration/Core/Api/TypeScriptGeneratorTest.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core\Api;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TP\Core\Api\TypeScriptGenerator;
use TP\Core\Attributes\ArrayOf;
use TP\Core\Attributes\Body;
use TP\Core\Attributes\Delete;
use TP\Core\Attributes\Get;
use TP\Core\Attributes\Post;
use TP\Core\Attributes\Returns;
use TP\Core\Attributes\RoutePrefix;
use TP\Core\Request;
use TP\Core\Response;

enum FixtureColor: string
{
    case Red = 'red';
    case Blue = 'blue';
}

final readonly class FixtureTagDto
{
    public function __construct(public string $label)
    {
    }
}

final readonly class FixtureItemDto
{
    public function __construct(
        public int $id,
        public float $price,
        public ?string $note,
        public bool $active,
        public FixtureColor $color,
        #[ArrayOf(FixtureTagDto::class)] public array $tags,
        #[ArrayOf('string')] public array $names,
        public ?FixtureTagDto $main,
    ) {
    }
}

final readonly class FixtureCreateRequest
{
    public function __construct(public string $name)
    {
    }
}

#[RoutePrefix('/api/items')]
final class FixtureItemsController
{
    #[Get('')]
    #[Returns(FixtureItemDto::class, list: true)]
    public function index(Request $request): Response
    {
        return Response::noContent();
    }

    #[Get('/{id}')]
    #[Returns(FixtureItemDto::class)]
    public function show(Request $request, array $params): Response
    {
        return Response::noContent();
    }

    #[Post('')]
    #[Body(FixtureCreateRequest::class)]
    #[Returns(FixtureItemDto::class)]
    public function create(Request $request): Response
    {
        return Response::noContent();
    }

    #[Delete('/{id}/tags/{tag}')]
    public function removeTag(Request $request, array $params): Response
    {
        return Response::noContent();
    }

    #[Post('/{id}/archive')]
    public function archive(Request $request, array $params): Response
    {
        return Response::noContent();
    }
}

final readonly class FixtureBadDto
{
    public function __construct(public array $untyped)
    {
    }
}

#[RoutePrefix('/api/bad')]
final class FixtureBadController
{
    #[Get('')]
    #[Returns(FixtureBadDto::class)]
    public function index(Request $request): Response
    {
        return Response::noContent();
    }
}

class TypeScriptGeneratorTest extends TestCase
{
    public function testGeneratesInterfacesEnumsAndClient(): void
    {
        $expected = <<<'TS'
// Generated by bin/generate-api.php — do not edit.
// Source: DTOs in src/Api/Dto and route attributes in src/Controllers/Api.

import type { ApiClient, RequestOptions } from './api-client';

export type FixtureColor = 'red' | 'blue';

export interface FixtureCreateRequest {
  name: string;
}

export interface FixtureItemDto {
  id: number;
  price: number;
  note: string | null;
  active: boolean;
  color: FixtureColor;
  tags: FixtureTagDto[];
  names: string[];
  main: FixtureTagDto | null;
}

export interface FixtureTagDto {
  label: string;
}

export function fixtureItemsApi(api: ApiClient) {
  return {
    index: (options?: RequestOptions): Promise<FixtureItemDto[]> =>
      api.get<FixtureItemDto[]>('/items', options),
    show: (id: number | string, options?: RequestOptions): Promise<FixtureItemDto> =>
      api.get<FixtureItemDto>(`/items/${encodeURIComponent(id)}`, options),
    create: (body: FixtureCreateRequest, options?: RequestOptions): Promise<FixtureItemDto> =>
      api.post<FixtureItemDto>('/items', body, options),
    removeTag: (id: number | string, tag: number | string, options?: RequestOptions): Promise<void> =>
      api.delete<void>(`/items/${encodeURIComponent(id)}/tags/${encodeURIComponent(tag)}`, options),
    archive: (id: number | string, options?: RequestOptions): Promise<void> =>
      api.post<void>(`/items/${encodeURIComponent(id)}/archive`, undefined, options),
  };
}

TS;

        $this->assertSame($expected, (new TypeScriptGenerator())->generate([FixtureItemsController::class]));
    }

    public function testUntypedArrayPropertyIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FixtureBadDto::$untyped is an array and needs #[ArrayOf(...)]');

        (new TypeScriptGenerator())->generate([FixtureBadController::class]);
    }

    public function testGeneratesAuthClientFromRealControllers(): void
    {
        $ts = TypeScriptGenerator::forApiControllers();

        $this->assertStringContainsString("export function authApi(api: ApiClient) {", $ts);
        $this->assertStringContainsString("login: (body: LoginRequest, options?: RequestOptions): Promise<SessionDto> =>", $ts);
        $this->assertStringContainsString("logout: (options?: RequestOptions): Promise<void> =>\n      api.post<void>('/auth/logout', undefined, options),", $ts);
        $this->assertStringContainsString("export interface UserDto {\n  id: number;", $ts);
    }
}
```

Note: the heredoc ends with an empty line before `TS;`, so `$expected` ends with `"}\n"` — matching the generator, whose output ends with a newline.

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter TypeScriptGeneratorTest` → Expected: FAIL (class `TypeScriptGenerator` not found).

- [ ] **Step 3: Implement** — `src/Core/Api/TypeScriptGenerator.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Core\Api;

use ReflectionClass;
use ReflectionEnum;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use TP\Core\Attributes\ArrayOf;
use TP\Core\Attributes\Body;
use TP\Core\Attributes\Returns;
use TP\Core\ControllerDiscovery;
use TP\Core\RouteScanner;

/**
 * Generates TypeScript interfaces and typed client functions from API controllers
 * (route attributes plus #[Returns]/#[Body]) and the DTO classes they reference.
 * PHP is the single source of truth for the API contract (spec §4.7).
 */
final class TypeScriptGenerator
{
    private const SCALARS = ['int' => 'number', 'float' => 'number', 'string' => 'string', 'bool' => 'boolean'];

    /** @var array<string, class-string> TypeScript name => PHP class */
    private array $types = [];

    /** Output for every controller in src/Controllers/Api. */
    public static function forApiControllers(): string
    {
        $controllers = (new ControllerDiscovery())->discover(
            __DIR__ . '/../../Controllers/Api',
            'TP\\Controllers\\Api'
        );
        return (new self())->generate($controllers);
    }

    /** @param list<class-string> $controllers */
    public function generate(array $controllers): string
    {
        $this->types = [];
        sort($controllers);

        $clients = [];
        $scanner = new RouteScanner();
        foreach ($controllers as $controller) {
            $routes = $scanner->scan($controller);
            if ($routes !== []) {
                $clients[] = $this->client($controller, $routes);
            }
        }

        // Emitting a type can register further types (nested DTOs), so loop until stable.
        $definitions = [];
        while (($pending = array_diff_key($this->types, $definitions)) !== []) {
            foreach ($pending as $name => $class) {
                $definitions[$name] = $this->definition($class);
            }
        }
        ksort($definitions);

        $out = "// Generated by bin/generate-api.php — do not edit.\n"
            . "// Source: DTOs in src/Api/Dto and route attributes in src/Controllers/Api.\n\n"
            . "import type { ApiClient, RequestOptions } from './api-client';\n";
        foreach ($definitions as $definition) {
            $out .= "\n" . $definition;
        }
        foreach ($clients as $client) {
            $out .= "\n" . $client;
        }
        return $out;
    }

    /** @param array<array{method: string, pattern: string, handler: array{0: class-string, 1: string}}> $routes */
    private function client(string $controller, array $routes): string
    {
        $shortName = (new ReflectionClass($controller))->getShortName();
        $name = lcfirst((string) preg_replace('/Controller$/', '', $shortName)) . 'Api';

        $members = [];
        foreach ($routes as $route) {
            [$class, $methodName] = $route['handler'];
            $method = new ReflectionMethod($class, $methodName);
            $returns = $this->attribute($method, Returns::class);
            $body = $this->attribute($method, Body::class);

            $result = 'void';
            if ($returns !== null) {
                $result = $this->typeRef($returns->type)
                    . ($returns->list ? '[]' : '')
                    . ($returns->nullable ? ' | null' : '');
            }

            $path = (string) preg_replace('#^/api(?=/|$)#', '', $route['pattern']);
            $path = $path === '' ? '/' : $path;
            preg_match_all('/\{(\w+)\}/', $path, $matches);

            $params = array_map(fn(string $param): string => "{$param}: number | string", $matches[1]);
            if ($body !== null) {
                $params[] = 'body: ' . $this->typeRef($body->type);
            }
            $params[] = 'options?: RequestOptions';

            $tsPath = $matches[1] === []
                ? "'{$path}'"
                : '`' . preg_replace('/\{(\w+)\}/', '${encodeURIComponent($1)}', $path) . '`';

            $verb = strtolower($route['method']);
            $args = [$tsPath];
            if (in_array($verb, ['post', 'put', 'patch'], true)) {
                $args[] = $body !== null ? 'body' : 'undefined';
            }
            $args[] = 'options';

            $members[] = "    {$methodName}: (" . implode(', ', $params) . "): Promise<{$result}> =>\n"
                . "      api.{$verb}<{$result}>(" . implode(', ', $args) . "),";
        }

        return "export function {$name}(api: ApiClient) {\n  return {\n"
            . implode("\n", $members)
            . "\n  };\n}\n";
    }

    /** @param class-string $class */
    private function definition(string $class): string
    {
        if (enum_exists($class)) {
            $enum = new ReflectionEnum($class);
            if (!$enum->isBacked()) {
                throw new RuntimeException("Enum {$class} must be a backed enum to be used in the API");
            }
            $values = [];
            foreach ($enum->getCases() as $case) {
                $value = $case->getBackingValue();
                $values[] = is_string($value) ? "'{$value}'" : (string) $value;
            }
            return "export type {$enum->getShortName()} = " . implode(' | ', $values) . ";\n";
        }

        $reflection = new ReflectionClass($class);
        $lines = [];
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $lines[] = "  {$property->getName()}: {$this->propertyType($property)};";
        }
        return "export interface {$reflection->getShortName()} {\n" . implode("\n", $lines) . "\n}\n";
    }

    private function propertyType(ReflectionProperty $property): string
    {
        $where = $property->getDeclaringClass()->getShortName() . '::$' . $property->getName();
        $type = $property->getType();
        if (!$type instanceof ReflectionNamedType) {
            throw new RuntimeException("API property {$where} needs a single (optionally nullable) type");
        }

        $name = $type->getName();
        if ($name === 'array') {
            $of = $this->attribute($property, ArrayOf::class);
            if ($of === null) {
                throw new RuntimeException("API property {$where} is an array and needs #[ArrayOf(...)]");
            }
            $ts = $this->typeRef($of->type) . '[]';
        } elseif ($type->isBuiltin() && !isset(self::SCALARS[$name])) {
            throw new RuntimeException("API property {$where} has unsupported type '{$name}'");
        } else {
            $ts = $this->typeRef($name);
        }

        return $type->allowsNull() ? "{$ts} | null" : $ts;
    }

    /** Map a PHP scalar or class name to a TS type name, registering DTOs/enums for output. */
    private function typeRef(string $type): string
    {
        if (isset(self::SCALARS[$type])) {
            return self::SCALARS[$type];
        }
        if (!class_exists($type)) {
            throw new RuntimeException("Unknown API type '{$type}'");
        }

        $short = (new ReflectionClass($type))->getShortName();
        if (isset($this->types[$short]) && $this->types[$short] !== $type) {
            throw new RuntimeException("API types {$this->types[$short]} and {$type} share the name '{$short}'");
        }
        $this->types[$short] = $type;
        return $short;
    }

    /**
     * @template T of object
     * @param class-string<T> $attribute
     * @return T|null
     */
    private function attribute(ReflectionMethod|ReflectionProperty $target, string $attribute): ?object
    {
        $found = $target->getAttributes($attribute);
        return $found === [] ? null : $found[0]->newInstance();
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter TypeScriptGeneratorTest` → Expected: 3 tests OK. If the first test fails only on whitespace, compare with `php -r` dumping the generator output and fix the generator (not the expected string) unless the expected string contradicts the spec.

- [ ] **Step 5: Commit**

```bash
git add src/Core/Api/TypeScriptGenerator.php tests/Integration/Core/Api/TypeScriptGeneratorTest.php
git commit -m "feat(api): Generate TypeScript types and clients from PHP DTOs and routes"
```

---

### Task 5: Generator CLI, scripts and the committed `generated.ts`

**Files:**
- Create: `bin/generate-api.php`, `frontend/src/app/core/api/generated.ts` (generated), `tests/Integration/Api/GeneratedApiTest.php`
- Modify: `composer.json` (scripts), `frontend/package.json` (scripts)

- [ ] **Step 1: Write the failing test** — `tests/Integration/Api/GeneratedApiTest.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use PHPUnit\Framework\TestCase;
use TP\Core\Api\TypeScriptGenerator;

class GeneratedApiTest extends TestCase
{
    private const FILE = __DIR__ . '/../../../frontend/src/app/core/api/generated.ts';

    public function testCommittedGeneratedFileIsUpToDate(): void
    {
        $this->assertFileExists(self::FILE);
        $this->assertSame(
            TypeScriptGenerator::forApiControllers(),
            file_get_contents(self::FILE),
            'frontend/src/app/core/api/generated.ts is out of date — run: composer api:generate'
        );
    }
}
```

Run: `vendor/bin/phpunit --filter GeneratedApiTest` → Expected: FAIL (file does not exist).

- [ ] **Step 2: Create `bin/generate-api.php`**

```php
<?php

declare(strict_types=1);

/**
 * Writes frontend/src/app/core/api/generated.ts from the API controllers and DTOs.
 *   php bin/generate-api.php           write the file
 *   php bin/generate-api.php --check   exit 1 if the committed file is out of date
 */

require __DIR__ . '/../vendor/autoload.php';

use TP\Core\Api\TypeScriptGenerator;

$target = __DIR__ . '/../frontend/src/app/core/api/generated.ts';
$generated = TypeScriptGenerator::forApiControllers();

if (in_array('--check', $argv, true)) {
    if (!is_file($target) || file_get_contents($target) !== $generated) {
        fwrite(STDERR, "frontend/src/app/core/api/generated.ts is out of date — run: composer api:generate\n");
        exit(1);
    }
    echo "generated.ts is up to date\n";
    exit(0);
}

file_put_contents($target, $generated);
echo "Wrote frontend/src/app/core/api/generated.ts\n";
```

(`vendor/autoload.php` is used instead of `bootstrap.php` because `bootstrap.php` boots the application — session and database.)

- [ ] **Step 3: Scripts**

```bash
composer config scripts.api:generate "php bin/generate-api.php"
composer config scripts.api:check "php bin/generate-api.php --check"
cd frontend
npm pkg set scripts.api="php ../bin/generate-api.php"
npm pkg set scripts.prestart="npm run api"
npm pkg set scripts.prebuild="npm run api"
cd ..
composer api:generate
```
Expected: `Wrote frontend/src/app/core/api/generated.ts`.

- [ ] **Step 4: Verify the generated file**

```bash
sed -n 1,40p frontend/src/app/core/api/generated.ts
composer api:check
```
Expected: interfaces `LoginRequest`, `SessionDto`, `UserDto`, function `authApi` with `me`, `login`, `logout`; `composer api:check` prints `generated.ts is up to date`.

Run: `vendor/bin/phpunit --filter GeneratedApiTest` → Expected: OK.
Run (in `frontend/`): `npx ng build && npm run lint` → Expected: both succeed (the generated file type-checks against `ApiClient`).

- [ ] **Step 5: Verify the stale check bites**

```bash
echo "// stale" >> frontend/src/app/core/api/generated.ts
vendor/bin/phpunit --filter GeneratedApiTest; composer api:check; echo "exit=$?"
composer api:generate
git diff --stat frontend/src/app/core/api/generated.ts
```
Expected: PHPUnit FAILS with the "out of date" message, `exit=1`; after regenerating, `git diff --stat` shows the file as new/untracked only (no stray `// stale`).

- [ ] **Step 6: Commit**

```bash
git add bin/generate-api.php composer.json frontend/package.json frontend/src/app/core/api/generated.ts tests/Integration/Api/GeneratedApiTest.php
git commit -m "feat(api): Add generate-api CLI and commit generated TypeScript client"
```

---

### Task 6: Translations in the frontend

**Files:**
- Create: `frontend/src/app/core/i18n/i18n.ts`, `translate.pipe.ts`, `i18n.spec.ts`, `translate.pipe.spec.ts`, `frontend/scripts/check-i18n-keys.mts`
- Modify: `frontend/src/i18n/{de,en,es}.json` (new key `errors.network`), `frontend/package.json`

- [ ] **Step 1: Add the `errors.network` key** (repo root):

```bash
php -r '
$values = ["de" => "Keine Verbindung zum Server. Bitte versuche es erneut.", "en" => "No connection to the server. Please try again.", "es" => "Sin conexión con el servidor. Inténtalo de nuevo."];
foreach ($values as $l => $text) {
    $f = "frontend/src/i18n/$l.json";
    $d = json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR);
    $d["errors"]["network"] = $text;
    file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n");
}'
vendor/bin/phpunit --filter TranslationValidationTest
```
Expected: OK.

- [ ] **Step 2: Write the failing tests** — `frontend/src/app/core/i18n/i18n.spec.ts`:

```ts
import { TestBed } from '@angular/core/testing';
import { I18n, interpolate, loadLocale, lookup, negotiateLocale } from './i18n';

describe('negotiateLocale', () => {
  it('picks the first supported primary language', () => {
    expect(negotiateLocale(['fr-FR', 'es-ES', 'de'])).toBe('es');
    expect(negotiateLocale(['de-CH'])).toBe('de');
    expect(negotiateLocale(['EN-us'])).toBe('en');
  });

  it('falls back to de', () => {
    expect(negotiateLocale(['fr', 'it'])).toBe('de');
    expect(negotiateLocale([])).toBe('de');
  });
});

describe('lookup / interpolate', () => {
  const table = { events: { unregister: 'Turnier abmelden', nested: { deep: 'x' } } };

  it('resolves dotted keys to strings only', () => {
    expect(lookup(table, 'events.unregister')).toBe('Turnier abmelden');
    expect(lookup(table, 'events.nested')).toBeUndefined();
    expect(lookup(table, 'events.missing')).toBeUndefined();
  });

  it('replaces :name placeholders and keeps unknown ones', () => {
    expect(interpolate(':name abmelden? (:other)', { name: 'Jane' })).toBe('Jane abmelden? (:other)');
    expect(interpolate('min :min', { min: 3 })).toBe('min 3');
  });
});

describe('I18n', () => {
  let i18n: I18n;

  beforeEach(() => {
    i18n = TestBed.inject(I18n);
    i18n.use('de', {
      events: { unregister_user_confirm: ':name abmelden?' },
      count: { players: 'Ein Spieler|:count Spieler', three: 'keine|einer|viele' },
    });
  });

  it('translates with params and returns the key when missing', () => {
    expect(i18n.t('events.unregister_user_confirm', { name: 'Jane' })).toBe('Jane abmelden?');
    expect(i18n.t('does.not.exist')).toBe('does.not.exist');
    expect(i18n.locale()).toBe('de');
  });

  it('chooses plural forms', () => {
    expect(i18n.choice('count.players', 1)).toBe('Ein Spieler');
    expect(i18n.choice('count.players', 4)).toBe('4 Spieler');
    expect(i18n.choice('count.three', 0)).toBe('keine');
    expect(i18n.choice('count.three', 1)).toBe('einer');
    expect(i18n.choice('count.three', 7)).toBe('viele');
  });

  it('loads a locale through the loader', async () => {
    await i18n.load('en', async () => ({ nav: { logout: 'Logout' } }));

    expect(i18n.locale()).toBe('en');
    expect(i18n.t('nav.logout')).toBe('Logout');
  });
});

describe('loadLocale', () => {
  it('loads the real German file', async () => {
    const table = await loadLocale('de');

    expect(lookup(table, 'events.unregister')).toBe('Turnier abmelden');
    expect(lookup(table, 'nav.logout')).toBe('Abmelden');
  });
});
```

`frontend/src/app/core/i18n/translate.pipe.spec.ts`:

```ts
import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { I18n } from './i18n';
import { TranslatePipe } from './translate.pipe';

@Component({
  imports: [TranslatePipe],
  template: `<span>{{ 'events.unregister' | t }}</span><b>{{ 'auth.register_user' | t: { username: 'Jane' } }}</b>`,
})
class Host {}

describe('TranslatePipe', () => {
  it('translates keys in templates', async () => {
    TestBed.inject(I18n).use('de', {
      events: { unregister: 'Turnier abmelden' },
      auth: { register_user: ':username anmelden?' },
    });
    const fixture = TestBed.createComponent(Host);
    await fixture.whenStable();

    expect(fixture.nativeElement.querySelector('span').textContent).toBe('Turnier abmelden');
    expect(fixture.nativeElement.querySelector('b').textContent).toBe('Jane anmelden?');
  });
});
```

- [ ] **Step 3: Run to verify they fail**

Run (in `frontend/`): `npx ng test --watch=false --include 'src/app/core/i18n/**/*.spec.ts'`
Expected: FAIL — cannot resolve `./i18n`.

- [ ] **Step 4: Implement** — `frontend/src/app/core/i18n/i18n.ts`:

```ts
import { Injectable, signal } from '@angular/core';

export const LOCALES = ['de', 'en', 'es'] as const;
export type Locale = (typeof LOCALES)[number];
export type Translations = Record<string, unknown>;
export type Params = Record<string, string | number>;

/** First supported primary language from the browser's list; German otherwise. */
export function negotiateLocale(preferred: readonly string[]): Locale {
  for (const tag of preferred) {
    const primary = tag.slice(0, 2).toLowerCase();
    if ((LOCALES as readonly string[]).includes(primary)) {
      return primary as Locale;
    }
  }
  return 'de';
}

export function lookup(table: Translations, key: string): string | undefined {
  let node: unknown = table;
  for (const part of key.split('.')) {
    if (typeof node !== 'object' || node === null) {
      return undefined;
    }
    node = (node as Record<string, unknown>)[part];
  }
  return typeof node === 'string' ? node : undefined;
}

/** Replace `:name` placeholders (same syntax as the PHP Translator). */
export function interpolate(text: string, params: Params): string {
  return text.replace(/:([A-Za-z_]+)/g, (match: string, name: string) =>
    name in params ? String(params[name]) : match,
  );
}

/** Each language file becomes its own lazy chunk. */
export async function loadLocale(locale: Locale): Promise<Translations> {
  const module = (await import(`../../../i18n/${locale}.json`)) as { default: Translations };
  return module.default;
}

@Injectable({ providedIn: 'root' })
export class I18n {
  private readonly _locale = signal<Locale>('de');
  readonly locale = this._locale.asReadonly();
  private table: Translations = {};

  async load(locale: Locale, loader: (locale: Locale) => Promise<Translations> = loadLocale): Promise<void> {
    this.use(locale, await loader(locale));
  }

  /** Install a translation table directly (used by load() and by tests). */
  use(locale: Locale, table: Translations): void {
    this.table = table;
    this._locale.set(locale);
  }

  t(key: string, params?: Params): string {
    const text = lookup(this.table, key) ?? key;
    return params ? interpolate(text, params) : text;
  }

  /** Plural forms: "one|many" or "zero|one|many"; `:count` is always available. */
  choice(key: string, count: number, params: Params = {}): string {
    const forms = (lookup(this.table, key) ?? key).split('|');
    let form: string;
    if (forms.length === 1) {
      form = forms[0];
    } else if (forms.length === 2) {
      form = forms[count === 1 ? 0 : 1];
    } else {
      form = forms[Math.min(count, 2)] ?? forms[0];
    }
    return interpolate(form, { count, ...params });
  }
}
```

`frontend/src/app/core/i18n/translate.pipe.ts`:

```ts
import { Pipe, PipeTransform, inject } from '@angular/core';
import { I18n, Params } from './i18n';

/** `{{ 'events.unregister' | t }}` — pure: the locale is fixed once the app has started. */
@Pipe({ name: 't' })
export class TranslatePipe implements PipeTransform {
  private readonly i18n = inject(I18n);

  transform(key: string, params?: Params): string {
    return this.i18n.t(key, params);
  }
}
```

- [ ] **Step 5: Run to verify they pass**

Run: `npx ng test --watch=false --include 'src/app/core/i18n/**/*.spec.ts'` → Expected: all passed.

- [ ] **Step 6: Key check script** — `frontend/scripts/check-i18n-keys.mts`:

```ts
// Fails when code in src/app references a translation key that does not exist in de.json.
// Run with: node scripts/check-i18n-keys.mts   (Node runs TypeScript directly)
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const src = new URL('../src/', import.meta.url).pathname;
const translations = JSON.parse(readFileSync(join(src, 'i18n/de.json'), 'utf8')) as Record<string, unknown>;
const generatedUi = join(src, 'app/ui');

function exists(key: string): boolean {
  let node: unknown = translations;
  for (const part of key.split('.')) {
    if (typeof node !== 'object' || node === null || !(part in node)) {
      return false;
    }
    node = (node as Record<string, unknown>)[part];
  }
  return typeof node === 'string';
}

function sourceFiles(dir: string): string[] {
  return readdirSync(dir).flatMap((name) => {
    const path = join(dir, name);
    if (statSync(path).isDirectory()) {
      return path === generatedUi ? [] : sourceFiles(path);
    }
    return /\.(ts|html)$/.test(name) && !name.endsWith('.spec.ts') ? [path] : [];
  });
}

const patterns = [
  /\bt\(\s*'([a-z0-9_.]+)'/g, // i18n.t('key')
  /'([a-z0-9_.]+)'\s*\|\s*t\b/g, // {{ 'key' | t }}
  /\b(?:success|error|info)\(\s*'([a-z0-9_.]+)'/g, // notify.error('key')
];

const missing: string[] = [];
for (const file of sourceFiles(join(src, 'app'))) {
  const source = readFileSync(file, 'utf8');
  for (const pattern of patterns) {
    for (const match of source.matchAll(pattern)) {
      if (!exists(match[1])) {
        missing.push(`${relative(src, file)}: ${match[1]}`);
      }
    }
  }
}

if (missing.length > 0) {
  console.error(`Unknown translation keys:\n${missing.join('\n')}`);
  process.exit(1);
}
console.log('All translation keys used in src/app exist.');
```

```bash
cd frontend
npm pkg set scripts.check:i18n="node scripts/check-i18n-keys.mts"
npm run check:i18n
printf "export const probe = (t: (k: string) => string) => t('nope.missing');\n" > src/app/i18n-probe.ts
npm run check:i18n; echo "exit=$?"
rm src/app/i18n-probe.ts
```
Expected: first run `All translation keys used in src/app exist.`; probe run lists `app/i18n-probe.ts: nope.missing` and `exit=1`.

- [ ] **Step 7: Lint, then commit**

Run: `npm run lint` → Expected: no errors (if ESLint complains about `process`/`console` in `scripts/`, add `{ files: ['scripts/**/*.ts'], languageOptions: { globals: { process: 'readonly', console: 'readonly' } } }` to `eslint.config.js`).

```bash
cd /home/hempe/git/tournament-planner
git add frontend/src/app/core/i18n frontend/scripts frontend/package.json frontend/src/i18n frontend/eslint.config.js
git commit -m "feat(frontend): Add i18n service, t pipe and translation key check"
```

---

### Task 7: Session store and route guards

**Files:**
- Create: `frontend/src/app/core/auth/session.store.ts`, `guards.ts`, `session.store.spec.ts`, `guards.spec.ts`

- [ ] **Step 1: Write the failing tests**

`session.store.spec.ts`:

```ts
import { TestBed } from '@angular/core/testing';
import { ApiClient, ApiError } from '../api/api-client';
import { UserDto } from '../api/generated';
import { SessionStore } from './session.store';

const jane: UserDto = {
  id: 7, username: 'jane', firstName: 'Jane', lastName: 'Doe', displayName: 'Jane Doe', isAdmin: false,
};

describe('SessionStore', () => {
  let api: { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn> };
  let store: SessionStore;

  beforeEach(() => {
    api = { get: vi.fn(), post: vi.fn() };
    TestBed.configureTestingModule({ providers: [{ provide: ApiClient, useValue: api }] });
    store = TestBed.inject(SessionStore);
  });

  it('starts logged out', () => {
    expect(store.user()).toBeNull();
    expect(store.loggedIn()).toBe(false);
    expect(store.isAdmin()).toBe(false);
  });

  it('refresh() loads the current user from /auth/me', async () => {
    api.get.mockResolvedValue({ user: { ...jane, isAdmin: true } });

    await store.refresh();

    expect(api.get).toHaveBeenCalledWith('/auth/me', undefined);
    expect(store.loggedIn()).toBe(true);
    expect(store.isAdmin()).toBe(true);
  });

  it('refresh() treats API errors as logged out', async () => {
    api.get.mockRejectedValue(new ApiError(0, 'errors.network'));

    await store.refresh();

    expect(store.user()).toBeNull();
  });

  it('login() posts credentials and stores the user', async () => {
    api.post.mockResolvedValue({ user: jane });

    await store.login('jane', 'secret');

    expect(api.post).toHaveBeenCalledWith('/auth/login', { username: 'jane', password: 'secret' }, undefined);
    expect(store.user()).toEqual(jane);
  });

  it('logout() clears the user', async () => {
    api.post.mockResolvedValueOnce({ user: jane }).mockResolvedValueOnce(undefined);
    await store.login('jane', 'secret');

    await store.logout();

    expect(api.post).toHaveBeenLastCalledWith('/auth/logout', undefined, undefined);
    expect(store.user()).toBeNull();
  });
});
```

`guards.spec.ts`:

```ts
import { computed, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot, UrlTree, provideRouter } from '@angular/router';
import { UserDto } from '../api/generated';
import { adminGuard, authGuard, guestGuard } from './guards';
import { SessionStore } from './session.store';

function setup(user: Partial<UserDto> | null) {
  const current = signal(user as UserDto | null);
  TestBed.configureTestingModule({
    providers: [
      provideRouter([]),
      {
        provide: SessionStore,
        useValue: {
          user: current,
          loggedIn: computed(() => current() !== null),
          isAdmin: computed(() => current()?.isAdmin === true),
        },
      },
    ],
  });
  const router = TestBed.inject(Router);
  const run = (guard: typeof authGuard, url = '/events/3') =>
    TestBed.runInInjectionContext(() =>
      guard({} as ActivatedRouteSnapshot, { url } as RouterStateSnapshot),
    );
  const serialize = (result: unknown) => router.serializeUrl(result as UrlTree);
  return { run, serialize };
}

describe('guards', () => {
  it('authGuard lets logged-in users through', () => {
    const { run } = setup({ isAdmin: false });
    expect(run(authGuard)).toBe(true);
  });

  it('authGuard sends anonymous users to /login with returnUrl', () => {
    const { run, serialize } = setup(null);
    expect(serialize(run(authGuard))).toBe('/login?returnUrl=%2Fevents%2F3');
  });

  it('adminGuard allows admins, sends members home and anonymous to login', () => {
    expect(setup({ isAdmin: true }).run(adminGuard)).toBe(true);

    TestBed.resetTestingModule();
    const member = setup({ isAdmin: false });
    expect(member.serialize(member.run(adminGuard))).toBe('/');

    TestBed.resetTestingModule();
    const anonymous = setup(null);
    expect(anonymous.serialize(anonymous.run(adminGuard, '/users'))).toBe('/login?returnUrl=%2Fusers');
  });

  it('guestGuard sends logged-in users home', () => {
    const member = setup({ isAdmin: false });
    expect(member.serialize(member.run(guestGuard, '/login'))).toBe('/');

    TestBed.resetTestingModule();
    expect(setup(null).run(guestGuard, '/login')).toBe(true);
  });
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `npx ng test --watch=false --include 'src/app/core/auth/**/*.spec.ts'` → Expected: FAIL (cannot resolve `./session.store`).

- [ ] **Step 3: Implement**

`session.store.ts`:

```ts
import { Injectable, computed, inject, signal } from '@angular/core';
import { ApiClient, ApiError } from '../api/api-client';
import { UserDto, authApi } from '../api/generated';

/** The logged-in user as signals. Loaded once at startup (see core/init.ts). */
@Injectable({ providedIn: 'root' })
export class SessionStore {
  private readonly auth = authApi(inject(ApiClient));
  private readonly _user = signal<UserDto | null>(null);

  readonly user = this._user.asReadonly();
  readonly loggedIn = computed(() => this._user() !== null);
  readonly isAdmin = computed(() => this._user()?.isAdmin === true);

  async refresh(): Promise<void> {
    try {
      this._user.set((await this.auth.me()).user);
    } catch (error) {
      if (!(error instanceof ApiError)) {
        throw error;
      }
      this._user.set(null);
    }
  }

  async login(username: string, password: string): Promise<void> {
    const session = await this.auth.login({ username, password });
    this._user.set(session.user);
  }

  async logout(): Promise<void> {
    await this.auth.logout();
    this._user.set(null);
  }

  /** Forget the user locally (e.g. after a 401). */
  clear(): void {
    this._user.set(null);
  }
}
```

`guards.ts`:

```ts
import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { SessionStore } from './session.store';

// The session is loaded before the first navigation (core/init.ts), so the guards can be synchronous.
// They only shape the UX; the server enforces access.

export const authGuard: CanActivateFn = (_route, state) => {
  if (inject(SessionStore).loggedIn()) {
    return true;
  }
  return inject(Router).createUrlTree(['/login'], { queryParams: { returnUrl: state.url } });
};

export const adminGuard: CanActivateFn = (_route, state) => {
  const session = inject(SessionStore);
  const router = inject(Router);
  if (!session.loggedIn()) {
    return router.createUrlTree(['/login'], { queryParams: { returnUrl: state.url } });
  }
  return session.isAdmin() ? true : router.createUrlTree(['/']);
};

export const guestGuard: CanActivateFn = () =>
  inject(SessionStore).loggedIn() ? inject(Router).createUrlTree(['/']) : true;
```

- [ ] **Step 4: Run to verify they pass**

Run: `npx ng test --watch=false --include 'src/app/core/auth/**/*.spec.ts'` → Expected: all passed.
Run: `npm run lint` → Expected: clean.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/app/core/auth
git commit -m "feat(frontend): Add session store and auth/admin/guest guards"
```

---

### Task 8: Confirmations, toasts and global error handling

**Files:**
- Create: `frontend/src/app/core/feedback/confirm.service.ts`, `confirm-dialog.ts`, `notify.ts`, `confirm.service.spec.ts`, `confirm-dialog.spec.ts`, `notify.spec.ts`
- Create: `frontend/src/app/core/errors/app-error-handler.ts`, `app-error-handler.spec.ts`

- [ ] **Step 1: Write the failing tests**

`confirm.service.spec.ts`:

```ts
import { TestBed } from '@angular/core/testing';
import { ConfirmService } from './confirm.service';

describe('ConfirmService', () => {
  it('resolves with the answer and clears the pending request', async () => {
    const confirm = TestBed.inject(ConfirmService);

    const answer = confirm.ask('Turnier abmelden?', { danger: true });
    expect(confirm.pending()).toEqual({ message: 'Turnier abmelden?', danger: true });

    confirm.answer(true);
    await expect(answer).resolves.toBe(true);
    expect(confirm.pending()).toBeNull();
  });

  it('a new question cancels the previous one', async () => {
    const confirm = TestBed.inject(ConfirmService);

    const first = confirm.ask('first?');
    const second = confirm.ask('second?');
    confirm.answer(true);

    await expect(first).resolves.toBe(false);
    await expect(second).resolves.toBe(true);
  });

  it('answering twice is harmless', async () => {
    const confirm = TestBed.inject(ConfirmService);
    const answer = confirm.ask('q?');

    confirm.answer(true);
    confirm.answer(false);

    await expect(answer).resolves.toBe(true);
  });
});
```

`confirm-dialog.spec.ts`:

```ts
import { TestBed } from '@angular/core/testing';
import { I18n } from '../i18n/i18n';
import { ConfirmDialog } from './confirm-dialog';
import { ConfirmService } from './confirm.service';

describe('ConfirmDialog', () => {
  function button(text: string): HTMLButtonElement {
    return Array.from(document.querySelectorAll('button')).find((b) => b.textContent?.trim() === text)!;
  }

  beforeEach(() => {
    TestBed.inject(I18n).use('de', {
      calendar: { confirmation: 'Bestätigung' },
      actions: { cancel: 'Abbrechen', confirm: 'Bestätigen' },
    });
  });

  it('shows the pending question and resolves true on confirm', async () => {
    const fixture = TestBed.createComponent(ConfirmDialog);
    const confirm = TestBed.inject(ConfirmService);
    await fixture.whenStable();

    const answer = confirm.ask('Möchtest du dich wirklich von diesem Turnier abmelden?');
    await fixture.whenStable();

    expect(document.body.textContent).toContain('Bestätigung');
    expect(document.body.textContent).toContain('Möchtest du dich wirklich von diesem Turnier abmelden?');
    button('Bestätigen').click();
    await expect(answer).resolves.toBe(true);
  });

  it('resolves false on cancel', async () => {
    const fixture = TestBed.createComponent(ConfirmDialog);
    const confirm = TestBed.inject(ConfirmService);
    await fixture.whenStable();

    const answer = confirm.ask('Sicher?');
    await fixture.whenStable();
    button('Abbrechen').click();

    await expect(answer).resolves.toBe(false);
  });
});
```

`notify.spec.ts`:

```ts
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
});
```

`app-error-handler.spec.ts`:

```ts
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
```

- [ ] **Step 2: Run to verify they fail**

Run: `npx ng test --watch=false --include 'src/app/core/{feedback,errors}/**/*.spec.ts'` → Expected: FAIL (modules not found).

- [ ] **Step 3: Implement**

`frontend/src/app/core/feedback/confirm.service.ts`:

```ts
import { Injectable, signal } from '@angular/core';

export interface ConfirmRequest {
  message: string;
  danger: boolean;
}

/** `if (await confirm.ask(t('…'))) { … }` — rendered by <app-confirm-dialog> in the app shell. */
@Injectable({ providedIn: 'root' })
export class ConfirmService {
  private readonly _pending = signal<ConfirmRequest | null>(null);
  readonly pending = this._pending.asReadonly();
  private resolve: ((answer: boolean) => void) | null = null;

  ask(message: string, options: { danger?: boolean } = {}): Promise<boolean> {
    this.resolve?.(false);
    return new Promise<boolean>((resolve) => {
      this.resolve = resolve;
      this._pending.set({ message, danger: options.danger ?? false });
    });
  }

  answer(answer: boolean): void {
    const resolve = this.resolve;
    this.resolve = null;
    this._pending.set(null);
    resolve?.(answer);
  }
}
```

`frontend/src/app/core/feedback/confirm-dialog.ts`:

```ts
import { Component, computed, inject } from '@angular/core';
import { HlmAlertDialogImports } from '@ui/alert-dialog';
import { TranslatePipe } from '../i18n/translate.pipe';
import { ConfirmService } from './confirm.service';

@Component({
  selector: 'app-confirm-dialog',
  imports: [HlmAlertDialogImports, TranslatePipe],
  template: `
    <hlm-alert-dialog [state]="state()" (closed)="confirm.answer(false)">
      <hlm-alert-dialog-content *hlmAlertDialogPortal>
        <hlm-alert-dialog-header>
          <h2 hlmAlertDialogTitle>{{ 'calendar.confirmation' | t }}</h2>
          <p hlmAlertDialogDescription>{{ confirm.pending()?.message }}</p>
        </hlm-alert-dialog-header>
        <hlm-alert-dialog-footer>
          <button hlmAlertDialogCancel (click)="confirm.answer(false)">{{ 'actions.cancel' | t }}</button>
          <button
            hlmAlertDialogAction
            [variant]="confirm.pending()?.danger ? 'destructive' : 'default'"
            (click)="confirm.answer(true)"
          >
            {{ 'actions.confirm' | t }}
          </button>
        </hlm-alert-dialog-footer>
      </hlm-alert-dialog-content>
    </hlm-alert-dialog>
  `,
})
export class ConfirmDialog {
  protected readonly confirm = inject(ConfirmService);
  protected readonly state = computed(() => (this.confirm.pending() ? 'open' : 'closed'));
}
```

`frontend/src/app/core/feedback/notify.ts`:

```ts
import { Injectable, InjectionToken, inject } from '@angular/core';
import { toast } from '@spartan-ng/brain/sonner';
import { I18n, Params } from '../i18n/i18n';

export interface Toaster {
  success(message: string): unknown;
  error(message: string): unknown;
  info(message: string): unknown;
}

export const TOASTER = new InjectionToken<Toaster>('TOASTER', { factory: () => toast });

/** Translated toasts, rendered by <hlm-toaster> in the app shell. */
@Injectable({ providedIn: 'root' })
export class Notify {
  private readonly i18n = inject(I18n);
  private readonly toaster = inject(TOASTER);

  success(key: string, params?: Params): void {
    this.toaster.success(this.i18n.t(key, params));
  }

  error(key: string, params?: Params): void {
    this.toaster.error(this.i18n.t(key, params));
  }

  info(key: string, params?: Params): void {
    this.toaster.info(this.i18n.t(key, params));
  }
}
```

`frontend/src/app/core/errors/app-error-handler.ts`:

```ts
import { ErrorHandler, Injectable, inject } from '@angular/core';
import { ApiError } from '../api/api-client';
import { Notify } from '../feedback/notify';

/** Last line of defence: unhandled errors become a translated toast. */
@Injectable()
export class AppErrorHandler implements ErrorHandler {
  private readonly notify = inject(Notify);

  handleError(error: unknown): void {
    if ((error as Error)?.name === 'AbortError') {
      return;
    }
    if (error instanceof ApiError) {
      // 401 is handled by the API hooks (redirect to /login).
      if (error.status !== 401) {
        this.notify.error(error.error);
      }
      return;
    }
    this.notify.error('errors.general');
    console.error(error);
  }
}
```

- [ ] **Step 4: Run to verify they pass**

Run: `npx ng test --watch=false --include 'src/app/core/{feedback,errors}/**/*.spec.ts'` → Expected: all passed.
Run: `npm run lint && npm run check:i18n` → Expected: clean.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/app/core/feedback frontend/src/app/core/errors
git commit -m "feat(frontend): Add confirm dialog, translated toasts and global error handler"
```

---

### Task 9: App shell, version, startup and routes

**Files:**
- Create: `frontend/src/app/core/version.ts`, `version.spec.ts`, `frontend/src/app/core/init.ts`, `init.spec.ts`
- Create: `frontend/src/app/core/layout/header.ts`, `footer.ts`, `header.spec.ts`
- Create: `frontend/src/app/features/home/home.page.ts`, `frontend/src/app/features/not-found/not-found.page.ts`, `not-found.page.spec.ts`
- Modify: `frontend/src/app/app.ts`, `app.spec.ts`, `app.config.ts`, `app.routes.ts`

- [ ] **Step 1: Write the failing tests**

`frontend/src/app/core/version.spec.ts`:

```ts
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
});
```

`frontend/src/app/core/init.spec.ts`:

```ts
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { API_HOOKS } from './api/api-client';
import { SessionStore } from './auth/session.store';
import { apiHooks } from './init';
import { VersionService } from './version';

describe('apiHooks', () => {
  it('forwards the server version and handles 401', async () => {
    const session = { clear: vi.fn() };
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: SessionStore, useValue: session },
        { provide: API_HOOKS, useFactory: apiHooks },
      ],
    });
    const hooks = TestBed.inject(API_HOOKS);
    const router = TestBed.inject(Router);
    const navigate = vi.spyOn(router, 'navigate').mockResolvedValue(true);

    hooks.onServerVersion?.('2026-12-04-a1b2c3d');
    hooks.onUnauthorized?.();

    expect(TestBed.inject(VersionService).serverVersion()).toBe('2026-12-04-a1b2c3d');
    expect(session.clear).toHaveBeenCalled();
    expect(navigate).toHaveBeenCalledWith(['/login'], { queryParams: { returnUrl: '/' } });
  });
});
```

`frontend/src/app/core/layout/header.spec.ts`:

```ts
import { computed, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { UserDto } from '../api/generated';
import { SessionStore } from '../auth/session.store';
import { Notify } from '../feedback/notify';
import { I18n } from '../i18n/i18n';
import { Header } from './header';

describe('Header', () => {
  const user = signal<UserDto | null>(null);
  const session = {
    user,
    loggedIn: computed(() => user() !== null),
    isAdmin: computed(() => user()?.isAdmin === true),
    logout: vi.fn().mockResolvedValue(undefined),
  };

  beforeEach(() => {
    user.set(null);
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        { provide: SessionStore, useValue: session },
        { provide: Notify, useValue: { success: vi.fn(), error: vi.fn() } },
      ],
    });
    TestBed.inject(I18n).use('de', {
      app: { name: 'GOLF EL FARO' },
      nav: { events: 'Anlässe', users: 'Benutzer', logout: 'Abmelden' },
      auth: { login: 'Mitglieder Anmeldung', logout_success: 'Erfolgreich abgemeldet' },
    });
  });

  it('shows only the login link when logged out', async () => {
    const fixture = TestBed.createComponent(Header);
    await fixture.whenStable();
    const text = fixture.nativeElement.textContent as string;

    expect(text).toContain('Mitglieder Anmeldung');
    expect(text).not.toContain('Abmelden');
    expect(text).not.toContain('Anlässe');
  });

  it('shows name, events and logout for members; users only for admins', async () => {
    user.set({ id: 1, username: 'jane', firstName: 'Jane', lastName: 'Doe', displayName: 'Jane Doe', isAdmin: false });
    const fixture = TestBed.createComponent(Header);
    await fixture.whenStable();
    let text = fixture.nativeElement.textContent as string;

    expect(text).toContain('Jane Doe');
    expect(text).toContain('Anlässe');
    expect(text).toContain('Abmelden');
    expect(text).not.toContain('Benutzer');

    user.set({ ...user()!, isAdmin: true });
    await fixture.whenStable();
    text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Benutzer');
  });

  it('logs out via the session store', async () => {
    user.set({ id: 1, username: 'jane', firstName: null, lastName: null, displayName: 'jane', isAdmin: false });
    const fixture = TestBed.createComponent(Header);
    await fixture.whenStable();

    const logout = Array.from(fixture.nativeElement.querySelectorAll('button') as NodeListOf<HTMLButtonElement>)
      .find((b) => b.textContent?.trim() === 'Abmelden')!;
    logout.click();
    await fixture.whenStable();

    expect(session.logout).toHaveBeenCalled();
  });
});
```

`frontend/src/app/features/not-found/not-found.page.spec.ts`:

```ts
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { I18n } from '../../core/i18n/i18n';
import { NotFoundPage } from './not-found.page';

describe('NotFoundPage', () => {
  it('explains and links home', async () => {
    TestBed.configureTestingModule({ providers: [provideRouter([])] });
    TestBed.inject(I18n).use('de', {
      errors: { '404_title': 'Seite nicht gefunden', not_found: 'Die angeforderte Seite wurde nicht gefunden.' },
      navigation: { home: 'Zur Startseite' },
    });
    const fixture = TestBed.createComponent(NotFoundPage);
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;

    expect(el.textContent).toContain('Seite nicht gefunden');
    expect(el.querySelector('a')?.getAttribute('href')).toBe('/');
  });
});
```

Replace `frontend/src/app/app.spec.ts`:

```ts
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { App } from './app';
import { I18n } from './core/i18n/i18n';

describe('App', () => {
  it('renders the shell with header and footer', async () => {
    TestBed.configureTestingModule({ providers: [provideRouter([])] });
    TestBed.inject(I18n).use('de', { app: { name: 'GOLF EL FARO' }, auth: { login: 'Mitglieder Anmeldung' } });
    const fixture = TestBed.createComponent(App);
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;

    expect(el.querySelector('app-header')?.textContent).toContain('GOLF EL FARO');
    expect(el.querySelector('app-footer')?.textContent).toContain('dev');
  });
});
```

- [ ] **Step 2: Run to verify they fail**

Run: `npm test` → Expected: FAIL (modules `./version`, `./init`, `./header`, `./not-found.page` not found; App spec fails).

- [ ] **Step 3: Implement**

`frontend/src/app/core/version.ts`:

```ts
import { Injectable, InjectionToken, computed, inject, signal } from '@angular/core';

/** Release of this frontend build ("dev" when served by ng serve). Plan 5 injects the real value. */
export const APP_VERSION = new InjectionToken<string>('APP_VERSION', { factory: () => 'dev' });

@Injectable({ providedIn: 'root' })
export class VersionService {
  readonly clientVersion = inject(APP_VERSION);
  private readonly _serverVersion = signal<string | null>(null);
  readonly serverVersion = this._serverVersion.asReadonly();

  /** True when the server runs a different release than this app (drives the update prompt in plan 5). */
  readonly outdated = computed(() => {
    const server = this._serverVersion();
    return server !== null && server !== 'dev' && this.clientVersion !== 'dev' && server !== this.clientVersion;
  });

  reportServerVersion(version: string): void {
    this._serverVersion.set(version);
  }
}
```

`frontend/src/app/core/init.ts`:

```ts
import { Injector, inject } from '@angular/core';
import { Router } from '@angular/router';
import { ApiHooks } from './api/api-client';
import { SessionStore } from './auth/session.store';
import { I18n, negotiateLocale } from './i18n/i18n';
import { VersionService } from './version';

/** Runs before the first navigation: language first, then the session. */
export async function initializeApp(): Promise<void> {
  const i18n = inject(I18n);
  const session = inject(SessionStore);

  const locale = negotiateLocale(navigator.languages);
  document.documentElement.lang = locale;
  await i18n.load(locale);
  await session.refresh();
}

/** Wiring for ApiClient. SessionStore is resolved lazily: it depends on ApiClient itself. */
export function apiHooks(): ApiHooks {
  const injector = inject(Injector);
  const router = inject(Router);
  const version = inject(VersionService);

  return {
    onServerVersion: (serverVersion) => version.reportServerVersion(serverVersion),
    onUnauthorized: () => {
      injector.get(SessionStore).clear();
      if (!router.url.startsWith('/login')) {
        void router.navigate(['/login'], { queryParams: { returnUrl: router.url } });
      }
    },
  };
}
```

`frontend/src/app/core/layout/header.ts`:

```ts
import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { SessionStore } from '../auth/session.store';
import { Notify } from '../feedback/notify';
import { TranslatePipe } from '../i18n/translate.pipe';

@Component({
  selector: 'app-header',
  imports: [RouterLink, RouterLinkActive, HlmButtonImports, TranslatePipe],
  template: `
    <header class="border-b border-border bg-background">
      <nav class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
        <a routerLink="/" class="font-semibold tracking-wide">{{ 'app.name' | t }}</a>
        @if (session.loggedIn()) {
          <a routerLink="/events" routerLinkActive="text-primary">{{ 'nav.events' | t }}</a>
          @if (session.isAdmin()) {
            <a routerLink="/users" routerLinkActive="text-primary">{{ 'nav.users' | t }}</a>
          }
          <span class="ml-auto text-sm text-muted-foreground">{{ session.user()?.displayName }}</span>
          <button hlmBtn variant="ghost" size="sm" type="button" (click)="logout()">{{ 'nav.logout' | t }}</button>
        } @else {
          <a hlmBtn size="sm" class="ml-auto" routerLink="/login">{{ 'auth.login' | t }}</a>
        }
      </nav>
    </header>
  `,
})
export class Header {
  protected readonly session = inject(SessionStore);
  private readonly notify = inject(Notify);
  private readonly router = inject(Router);

  protected async logout(): Promise<void> {
    await this.session.logout();
    this.notify.success('auth.logout_success');
    await this.router.navigateByUrl('/');
  }
}
```

`frontend/src/app/core/layout/footer.ts`:

```ts
import { Component, inject } from '@angular/core';
import { TranslatePipe } from '../i18n/translate.pipe';
import { APP_VERSION } from '../version';

@Component({
  selector: 'app-footer',
  imports: [TranslatePipe],
  template: `
    <footer class="border-t border-border py-4 text-center text-xs text-muted-foreground">
      {{ 'app.name' | t }} · {{ version }}
    </footer>
  `,
})
export class Footer {
  protected readonly version = inject(APP_VERSION);
}
```

`frontend/src/app/features/home/home.page.ts`:

```ts
import { Component, inject } from '@angular/core';
import { SessionStore } from '../../core/auth/session.store';
import { TranslatePipe } from '../../core/i18n/translate.pipe';

/** Placeholder start page; replaced by the event overview in plan 3. */
@Component({
  selector: 'app-home-page',
  imports: [TranslatePipe],
  template: `
    <h1 class="text-2xl font-semibold">{{ 'app.welcome' | t }}</h1>
    @if (session.user(); as user) {
      <p class="mt-2 text-muted-foreground">{{ user.displayName }}</p>
    }
  `,
})
export class HomePage {
  protected readonly session = inject(SessionStore);
}
```

`frontend/src/app/features/not-found/not-found.page.ts`:

```ts
import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { TranslatePipe } from '../../core/i18n/translate.pipe';

@Component({
  selector: 'app-not-found-page',
  imports: [RouterLink, HlmButtonImports, TranslatePipe],
  template: `
    <h1 class="text-2xl font-semibold">{{ 'errors.404_title' | t }}</h1>
    <p class="mt-2 text-muted-foreground">{{ 'errors.not_found' | t }}</p>
    <a hlmBtn class="mt-6" routerLink="/">{{ 'navigation.home' | t }}</a>
  `,
})
export class NotFoundPage {}
```

Replace `frontend/src/app/app.ts`:

```ts
import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { HlmToasterImports } from '@ui/sonner';
import { ConfirmDialog } from './core/feedback/confirm-dialog';
import { Footer } from './core/layout/footer';
import { Header } from './core/layout/header';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, Header, Footer, ConfirmDialog, HlmToasterImports],
  template: `
    <div class="flex min-h-dvh flex-col">
      <app-header />
      <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-6">
        <router-outlet />
      </main>
      <app-footer />
    </div>
    <hlm-toaster />
    <app-confirm-dialog />
  `,
})
export class App {}
```

Replace `frontend/src/app/app.routes.ts`:

```ts
import { Routes } from '@angular/router';
import { guestGuard } from './core/auth/guards';

export const routes: Routes = [
  { path: '', loadComponent: () => import('./features/home/home.page').then((m) => m.HomePage) },
  {
    path: 'login',
    canActivate: [guestGuard],
    loadComponent: () => import('./features/login/login.page').then((m) => m.LoginPage),
  },
  { path: '**', loadComponent: () => import('./features/not-found/not-found.page').then((m) => m.NotFoundPage) },
];
```

(`features/login/login.page.ts` is created in Task 10; until then `ng build` fails on this import, so run only `npm test` in this task — the specs don't import the routes.)

Replace `frontend/src/app/app.config.ts`:

```ts
import {
  ApplicationConfig,
  ErrorHandler,
  provideAppInitializer,
  provideBrowserGlobalErrorListeners,
} from '@angular/core';
import { provideRouter, withComponentInputBinding } from '@angular/router';
import { routes } from './app.routes';
import { API_HOOKS } from './core/api/api-client';
import { AppErrorHandler } from './core/errors/app-error-handler';
import { apiHooks, initializeApp } from './core/init';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(routes, withComponentInputBinding()),
    { provide: ErrorHandler, useClass: AppErrorHandler },
    { provide: API_HOOKS, useFactory: apiHooks },
    provideAppInitializer(initializeApp),
  ],
};
```

- [ ] **Step 4: Run to verify they pass**

Run: `npm test` → Expected: all passed.
Run: `npm run lint && npm run check:i18n` → Expected: clean.

- [ ] **Step 5: Commit**

```bash
git add frontend/src/app
git commit -m "feat(frontend): Add app shell, startup, version service and routes"
```

---

### Task 10: Login page

**Files:**
- Create: `frontend/src/app/features/login/login.page.ts`, `login.page.spec.ts`

- [ ] **Step 1: Write the failing tests** — `login.page.spec.ts`:

```ts
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ApiError } from '../../core/api/api-client';
import { SessionStore } from '../../core/auth/session.store';
import { I18n } from '../../core/i18n/i18n';
import { LoginPage, safeReturnUrl } from './login.page';

describe('LoginPage', () => {
  let session: { login: ReturnType<typeof vi.fn> };
  let navigate: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    session = { login: vi.fn().mockResolvedValue(undefined) };
    TestBed.configureTestingModule({
      providers: [provideRouter([]), { provide: SessionStore, useValue: session }],
    });
    TestBed.inject(I18n).use('de', {
      auth: {
        login: 'Mitglieder Anmeldung',
        username: 'Benutzername',
        password: 'Passwort',
        login_failed: 'Anmeldung fehlgeschlagen',
      },
      validation: { required: 'Das Feld :field ist erforderlich.' },
    });
    navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
  });

  async function render(returnUrl?: string) {
    const fixture = TestBed.createComponent(LoginPage);
    if (returnUrl !== undefined) {
      fixture.componentRef.setInput('returnUrl', returnUrl);
    }
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;
    const type = (id: string, value: string) => {
      const input = el.querySelector<HTMLInputElement>(`#${id}`)!;
      input.value = value;
      input.dispatchEvent(new Event('input'));
    };
    // jsdom does not submit forms on button click
    const submit = async () => {
      el.querySelector('form')!.dispatchEvent(new Event('submit', { cancelable: true }));
      await fixture.whenStable();
    };
    return { fixture, el, type, submit };
  }

  it('shows required errors and does not call the API when empty', async () => {
    const { el, submit } = await render();

    await submit();

    expect(session.login).not.toHaveBeenCalled();
    expect(el.textContent).toContain('Das Feld Benutzername ist erforderlich.');
    expect(el.textContent).toContain('Das Feld Passwort ist erforderlich.');
  });

  it('logs in and navigates to the return URL', async () => {
    const { type, submit } = await render('/events/3');

    type('username', 'jane');
    type('password', 'secret');
    await submit();

    expect(session.login).toHaveBeenCalledWith('jane', 'secret');
    expect(navigate).toHaveBeenCalledWith('/events/3');
  });

  it('shows the server message for wrong credentials', async () => {
    session.login.mockRejectedValue(new ApiError(422, 'auth.login_failed'));
    const { el, type, submit } = await render();

    type('username', 'jane');
    type('password', 'wrong');
    await submit();

    expect(el.querySelector('[role="alert"]')?.textContent).toContain('Anmeldung fehlgeschlagen');
    expect(navigate).not.toHaveBeenCalled();
  });
});

describe('safeReturnUrl', () => {
  it('only allows same-site paths', () => {
    expect(safeReturnUrl('/events/3')).toBe('/events/3');
    expect(safeReturnUrl(undefined)).toBe('/');
    expect(safeReturnUrl('')).toBe('/');
    expect(safeReturnUrl('https://evil.example')).toBe('/');
    expect(safeReturnUrl('//evil.example')).toBe('/');
  });
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `npx ng test --watch=false --include src/app/features/login/login.page.spec.ts` → Expected: FAIL (cannot resolve `./login.page`).

- [ ] **Step 3: Implement** — `login.page.ts`:

```ts
import { Component, inject, input, signal } from '@angular/core';
import { FormField, form, required, submit } from '@angular/forms/signals';
import { Router } from '@angular/router';
import { HlmButtonImports } from '@ui/button';
import { HlmCardImports } from '@ui/card';
import { HlmInputImports } from '@ui/input';
import { HlmLabelImports } from '@ui/label';
import { ApiError } from '../../core/api/api-client';
import { SessionStore } from '../../core/auth/session.store';
import { I18n } from '../../core/i18n/i18n';
import { TranslatePipe } from '../../core/i18n/translate.pipe';

/** Only same-site paths are allowed as redirect targets (no open redirects). */
export function safeReturnUrl(url: string | undefined): string {
  return url && url.startsWith('/') && !url.startsWith('//') ? url : '/';
}

@Component({
  selector: 'app-login-page',
  imports: [FormField, HlmButtonImports, HlmCardImports, HlmInputImports, HlmLabelImports, TranslatePipe],
  template: `
    <div class="mx-auto mt-8 max-w-sm">
      <section hlmCard>
        <div hlmCardHeader>
          <h1 hlmCardTitle>{{ 'auth.login' | t }}</h1>
        </div>
        <form hlmCardContent class="flex flex-col gap-4" novalidate (submit)="$event.preventDefault(); login()">
          <div class="flex flex-col gap-2">
            <label hlmLabel for="username">{{ 'auth.username' | t }}</label>
            <input hlmInput id="username" autocomplete="username" [formField]="loginForm.username" />
            @if (loginForm.username().touched() && loginForm.username().invalid()) {
              <p class="text-sm text-destructive">{{ requiredMessage('auth.username') }}</p>
            }
          </div>
          <div class="flex flex-col gap-2">
            <label hlmLabel for="password">{{ 'auth.password' | t }}</label>
            <input hlmInput id="password" type="password" autocomplete="current-password" [formField]="loginForm.password" />
            @if (loginForm.password().touched() && loginForm.password().invalid()) {
              <p class="text-sm text-destructive">{{ requiredMessage('auth.password') }}</p>
            }
          </div>
          @if (error(); as key) {
            <p role="alert" class="text-sm text-destructive">{{ key | t }}</p>
          }
          <button hlmBtn type="submit" [disabled]="busy()">{{ 'auth.login' | t }}</button>
        </form>
      </section>
    </div>
  `,
})
export class LoginPage {
  /** From the ?returnUrl= query parameter (withComponentInputBinding). */
  readonly returnUrl = input<string>();

  private readonly session = inject(SessionStore);
  private readonly router = inject(Router);
  private readonly i18n = inject(I18n);

  protected readonly model = signal({ username: '', password: '' });
  protected readonly loginForm = form(this.model, (path) => {
    required(path.username);
    required(path.password);
  });
  protected readonly error = signal<string | null>(null);
  protected readonly busy = signal(false);

  protected requiredMessage(labelKey: string): string {
    return this.i18n.t('validation.required', { field: this.i18n.t(labelKey) });
  }

  protected async login(): Promise<void> {
    this.error.set(null);
    await submit(this.loginForm, async () => {
      this.busy.set(true);
      try {
        const { username, password } = this.model();
        await this.session.login(username, password);
        await this.router.navigateByUrl(safeReturnUrl(this.returnUrl()));
      } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
          this.error.set(error.error);
          return undefined;
        }
        throw error;
      } finally {
        this.busy.set(false);
      }
      return undefined;
    });
  }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `npx ng test --watch=false --include src/app/features/login/login.page.spec.ts` → Expected: 4 passed.
Run: `npm test && npm run lint && npm run check:i18n && npx ng build` → Expected: all succeed; the build lists lazy chunks for `home-page`, `login-page`, `not-found-page`, `de-json`, `en-json`, `es-json`.

- [ ] **Step 5: Manual smoke test against the real API**

```bash
cd /home/hempe/git/tournament-planner
php -S localhost:5000 -c php.ini >/tmp/tp-php.log 2>&1 &
(cd frontend && npx ng serve --port 4200 >/tmp/tp-ng.log 2>&1 &)
sleep 15
curl -s localhost:4200/api/auth/me
curl -s -o /dev/null -w "%{http_code}\n" localhost:4200/login
kill %1 2>/dev/null; pkill -f "ng serve --port 4200"
```
Expected: `{"user":null}` (proxied through the dev server) and `200`.

- [ ] **Step 6: Commit**

```bash
git add frontend/src/app/features/login
git commit -m "feat(frontend): Add login page with signal forms"
```

---

### Task 11: Dev script and documentation

**Files:**
- Modify: `run.sh`, `CLAUDE.md`, `docs/TESTING.md`, `README.md`
- Create: `docs/FRONTEND.md`

- [ ] **Step 1: `run.sh`** — replace the whole file:

```bash
#!/bin/bash
# Development: PHP (API + legacy UI) on http://localhost:5000,
# Angular dev server on http://localhost:4200 (proxies /api to PHP).
set -e
trap 'kill 0' EXIT
ROUTE_CACHE_ENABLED=true php -S localhost:5000 -c php.ini &
cd frontend && npm start
```

- [ ] **Step 2: Create `docs/FRONTEND.md`**

````markdown
# Frontend (Angular)

The new UI lives in `frontend/` — an Angular 22 single-page app that talks to the PHP JSON API
(`docs/API.md`). Node is only needed for development and building; production serves static files.

## Commands (inside `frontend/`)

| Command | What it does |
|---|---|
| `npm start` | regenerate the API client, then `ng serve` on :4200 (proxies `/api` to :5000) |
| `npm test` | Vitest unit/component tests |
| `npm run lint` | ESLint (incl. the RxJS/HttpClient ban) |
| `npm run check:i18n` | fail on translation keys that don't exist |
| `npm run build` | regenerate the API client, then production build |
| `npm run api` | regenerate `src/app/core/api/generated.ts` |

From the repository root, `./run.sh` starts PHP and the Angular dev server together.

## Structure

```
src/app/
├── core/       api/ (ApiClient + generated.ts), auth/, i18n/, feedback/, errors/, layout/, init.ts, version.ts
├── features/   one folder per area, each lazy-loaded from app.routes.ts
└── ui/         Spartan components — copied into the repo, ours to change
src/i18n/       de.json, en.json, es.json (also read by PHP)
```

## Rules

- **No RxJS, no HttpClient** in app code (lint error). Use signals, `computed()`, `resource()` and Promises.
- **Talk to PHP only through the generated client:** `authApi(inject(ApiClient)).me()`. Never hand-write
  API types — change the PHP DTO, run `composer api:generate`, commit `generated.ts`. A PHPUnit test fails
  if the file is stale.
- **All text through i18n:** `{{ 'key' | t }}` in templates, `i18n.t('key')` in code, `notify.error('key')`
  for toasts. API errors are keys too (`ApiError.error`, `ApiError.fields[*].key`).
- **Confirmations:** `if (await confirm.ask(i18n.t('…'), { danger: true })) { … }` (`ConfirmService`).
- **Forms:** signal forms (`form()`, `[formField]`, `submit()`) from `@angular/forms/signals`.
- **Tests:** jsdom does not submit a form when a submit button is clicked — dispatch
  `new Event('submit', { cancelable: true })` on the form instead.

## Spartan UI components

Components in `src/app/ui/` were generated once by the Spartan CLI and are now regular source files.
`@spartan-ng/brain` (the headless primitives underneath) is pinned to an exact version. To add a component:

```bash
cd frontend
npm i -D @spartan-ng/cli@<same version as @spartan-ng/brain>
npx ng g @spartan-ng/cli:ui <name> --defaults      # uses components.json
npm uninstall @spartan-ng/cli
```

Available names: see `npx ng g @spartan-ng/cli:ui --help` while the CLI is installed.
````

- [ ] **Step 3: Update `CLAUDE.md`**

- Under "## Development Commands", after the "Run tests" block, add:

````markdown
**Frontend (Angular, in `frontend/`):**
```bash
./run.sh                   # PHP on :5000 + Angular dev server on :4200
cd frontend && npm test    # Vitest
npm run lint               # includes the no-RxJS / no-HttpClient rule
npm run check:i18n         # unknown translation keys
composer api:generate      # regenerate frontend/src/app/core/api/generated.ts after DTO changes
```
````

- In "### Testing Policy", change `- Run \`composer test\` after every code change` to:
  `- Run \`composer test\` after every PHP change and \`npm test && npm run lint && npm run check:i18n\` (in \`frontend/\`) after every frontend change`
- In "## Documentation", add `- **[Frontend](docs/FRONTEND.md)** - Angular app, rules, generated API client`.

- [ ] **Step 4: Update `docs/TESTING.md` and `README.md`**

- `docs/TESTING.md`: add a section `## Frontend tests` with the command table from `docs/FRONTEND.md` (test, lint, check:i18n), and update the PHP numbers using `composer test 2>&1 | grep '^Tests:'` (replace the old `Tests: 506, Assertions: 1120` line and every `(506 tests)` occurrence).
- `README.md`: update `(506 tests)` with the new PHP count; under "## Requirements" add `- Node.js 26+ (development and build only — not needed on the server)`.
- `CLAUDE.md`: update `with 506 tests` with the new PHP count.

- [ ] **Step 5: Final verification**

```bash
composer test 2>&1 | tail -3
composer api:check
cd frontend && npm test && npm run lint && npm run check:i18n && npm run build
```
Expected: PHP baseline status with the new count; `generated.ts is up to date`; all frontend checks pass.

- [ ] **Step 6: Commit**

```bash
cd /home/hempe/git/tournament-planner
git add run.sh docs/FRONTEND.md CLAUDE.md docs/TESTING.md README.md
git commit -m "docs: Document the Angular frontend and update dev script"
```

---

## Done criteria for plan 2

- `composer test`, `composer api:check`, and in `frontend/`: `npm test`, `npm run lint`, `npm run check:i18n`, `npm run build` all pass.
- `./run.sh`, then http://localhost:4200: header shows "GOLF EL FARO" and "Mitglieder Anmeldung"; logging in as an existing user shows the name and an "Abmelden" button; "Abmelden" logs out with a toast "Erfolgreich abgemeldet"; an unknown URL shows the not-found page; the footer shows `dev`.
- The legacy PHP UI on http://localhost:5000 still works.
