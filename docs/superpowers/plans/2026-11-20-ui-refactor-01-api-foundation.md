# UI Refactor — Plan 1: API Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the PHP app a working JSON API layer under `/api/*` (JSON in/out, uniform errors, CSRF protection, version header, login endpoints) and move translations to JSON — without breaking the existing server-rendered UI.

**Architecture:** API controllers live in `src/Controllers/Api/` next to the old HTML controllers (deleted in plan 5). Cross-cutting API behaviour is keyed off `Request::isApi()` (path starts with `/api/`): JSON 404/500, JSON 401/403 from the existing middlewares, `X-App-Version` header. A new `CsrfMiddleware` (class-level on every API controller) issues an `XSRF-TOKEN` cookie and validates the `X-XSRF-TOKEN` header on non-GET requests. Translations move from `resources/lang/*.php` to `frontend/src/i18n/*.json`, which the PHP `Translator` reads (and the Angular app will read in plan 2).

**Tech Stack:** PHP 8.5 (strict types), PHPUnit 10.5, MySQL. No new dependencies.

**Spec:** `docs/superpowers/specs/2026-11-20-ui-refactor-design.md`

**Series:** Plan 1 of 5 — (1) API foundation, (2) frontend foundation, (3) member features, (4) admin features, (5) build/PWA/E2E/cut-over.

**Spec refinements made in this plan:**
- Field errors are objects, not bare keys: `"fields": { "username": { "key": "validation.required" } }`, with optional `"params"` (e.g. `{ "min": 3 }`), because the existing `validation.*` texts contain placeholders.
- `GET /api/auth/me` returns `{ "user": {...} }` or `{ "user": null }` (always a JSON object).
- Failed login is `422 auth.login_failed`, not 401, so the frontend's "401 → go to login" rule never fires on the login form itself.
- Logout clears and regenerates the session instead of `session_destroy()`, so a fresh CSRF token is issued in the same response.
- New `APP_SECURE` setting (default `true` in production) — `app.secure` was read by `Application` but never defined, so the session cookie never had the `Secure` flag.

---

## Conventions for every task

- Run single tests with `vendor/bin/phpunit --filter <TestName>`; the full suite with `composer test`.
- `composer test` currently ends with `OK, but there were issues!` (3 warnings from a duplicate `mysqli` module load in the local PHP config, 9 PHPUnit-internal deprecations). That is the baseline; do not introduce new failures, warnings or deprecations.
- Test DB is `TP_test`, admin `admin` / `Admin123!`, created by `IntegrationTestCase`.
- Commit after each task with the message given. End every commit message with:
  `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`

## File map

| File | Change | Responsibility |
|---|---|---|
| `src/Core/Config.php` | modify | `app.secure` setting |
| `src/Core/Attributes/Patch.php` | create | `#[Patch]` route attribute |
| `src/Core/RouteScanner.php` | modify | recognise `#[Patch]` |
| `src/Core/Response.php` | modify | `CONFLICT` status, `apiError()`, `noContent()`, `withHeader()`, `withCookie()`, recording of last sent response |
| `src/Core/Request.php` | modify | `isApi()`, JSON body parsing, invalid-JSON flag, test seam for raw body |
| `src/Core/ValidationError.php` | modify | translation `key` + `params` |
| `src/Core/Validator.php` | modify | fill `key` + `params` |
| `src/Core/ValidationResult.php` | modify | `fieldErrors()` for API responses |
| `src/Core/AppVersion.php` | create | read `version.txt` (fallback `dev`) |
| `src/Core/Application.php` | modify | invalid JSON → 400, `X-App-Version` + `Cache-Control` on API responses, JSON 500 |
| `src/Core/Router.php` | modify | JSON 404 for unmatched `/api/*` |
| `src/Core/Translator.php` | modify | read JSON files from `langDir()` |
| `src/Middleware/AuthMiddleware.php` | modify | JSON 401 for API |
| `src/Middleware/AdminMiddleware.php` | modify | JSON 401/403 for API |
| `src/Middleware/CsrfMiddleware.php` | create | XSRF cookie + header check |
| `src/Controllers/Api/AuthController.php` | create | `/api/auth/me`, `/login`, `/logout` |
| `frontend/src/i18n/{de,en,es}.json` | create | translations (single source) |
| `resources/lang/*.php` | delete | replaced by JSON |
| `build.sh` | modify | copy JSON translations into `dist/resources/i18n/` |
| `tests/Integration/IntegrationTestCase.php` | modify | `json()` helper, headers/cookies on `TestResponse` |
| `tests/Integration/Api/*Test.php` | create | API tests |
| `tests/Integration/Core/*Test.php` | modify | unit tests for core changes |
| `tests/Integration/TranslationValidationTest.php` | modify | read JSON; check API error keys exist |
| `docs/API.md` | create | API conventions |
| `CLAUDE.md`, `docs/TESTING.md`, `README.md`, `.gitignore` | modify | docs, test count, `version.txt` |

---

### Task 1: `APP_SECURE` config setting

**Files:**
- Modify: `src/Core/Config.php` (the `'app' => [...]` block, around line 73)
- Test: `tests/Integration/Core/ConfigTest.php`

- [ ] **Step 1: Write the failing tests** — append to `ConfigTest`:

```php
    public function testAppSecureDefaultsToFalseOutsideProduction(): void
    {
        $_ENV['APP_ENV'] = 'testing';
        unset($_ENV['APP_SECURE']);

        $this->assertFalse(Config::getInstance()->get('app.secure'));
    }

    public function testAppSecureDefaultsToTrueInProduction(): void
    {
        $_ENV['APP_ENV'] = 'production';
        unset($_ENV['APP_SECURE']);
        try {
            $this->assertTrue(Config::getInstance()->get('app.secure'));
        } finally {
            $_ENV['APP_ENV'] = 'testing';
        }
    }

    public function testAppSecureCanBeDisabledExplicitly(): void
    {
        $_ENV['APP_ENV'] = 'production';
        $_ENV['APP_SECURE'] = 'false';
        try {
            $this->assertFalse(Config::getInstance()->get('app.secure'));
        } finally {
            $_ENV['APP_ENV'] = 'testing';
            unset($_ENV['APP_SECURE']);
        }
    }
```

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit --filter 'testAppSecure'`
Expected: FAIL — `get('app.secure')` returns `null`.

- [ ] **Step 3: Implement** — in `src/Core/Config.php`, add to the `'app'` array after `'locale'`:

```php
                'secure' => filter_var(
                    $_ENV['APP_SECURE'] ?? ($this->isProduction() ? 'true' : 'false'),
                    FILTER_VALIDATE_BOOLEAN
                ),
```

- [ ] **Step 4: Run to verify they pass**

Run: `vendor/bin/phpunit --filter 'testAppSecure'` → Expected: 3 tests OK.
Run: `composer test` → Expected: baseline (no new failures).

- [ ] **Step 5: Commit**

```bash
git add src/Core/Config.php tests/Integration/Core/ConfigTest.php
git commit -m "fix(security): Define app.secure so session cookies get the Secure flag in production"
```

---

### Task 2: Move translations to JSON

**Files:**
- Create: `frontend/src/i18n/de.json`, `en.json`, `es.json`
- Delete: `resources/lang/de.php`, `en.php`, `es.php`
- Modify: `src/Core/Translator.php`, `tests/Integration/TranslationValidationTest.php`, `build.sh`
- Test: `tests/Integration/Core/TranslatorTest.php` (create)

- [ ] **Step 1: Convert the files**

```bash
mkdir -p frontend/src/i18n
for l in de en es; do
  php -r '$d = require "resources/lang/'"$l"'.php";
    file_put_contents("frontend/src/i18n/'"$l"'.json",
      json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n");'
done
```

- [ ] **Step 2: Verify the conversion is lossless**

```bash
for l in de en es; do
  php -r '$a = require "resources/lang/'"$l"'.php";
    $b = json_decode(file_get_contents("frontend/src/i18n/'"$l"'.json"), true, 512, JSON_THROW_ON_ERROR);
    echo "'"$l"': ", $a === $b ? "identical" : "DIFFERENT", PHP_EOL;'
done
```
Expected: `de: identical`, `en: identical`, `es: identical`. If any says `DIFFERENT`, stop and investigate (e.g. numeric-string keys).

- [ ] **Step 3: Write the failing test** — create `tests/Integration/Core/TranslatorTest.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\Translator;

class TranslatorTest extends TestCase
{
    public function testLangDirPointsToJsonSourceInRepository(): void
    {
        $dir = realpath(Translator::langDir());

        $this->assertNotFalse($dir);
        $this->assertStringEndsWith('frontend/src/i18n', $dir);
        $this->assertFileExists($dir . '/de.json');
    }

    public function testTranslatesFromJsonFiles(): void
    {
        $translator = Translator::getInstance();
        $translator->setLocale('de');

        $this->assertSame('Turnier abmelden', $translator->translate('events.unregister'));
    }
}
```

- [ ] **Step 4: Delete the PHP files and run to verify failure**

```bash
git rm -q resources/lang/de.php resources/lang/en.php resources/lang/es.php
vendor/bin/phpunit --filter TranslatorTest
```
Expected: FAIL — `Call to undefined method TP\Core\Translator::langDir()`.

- [ ] **Step 5: Implement** — in `src/Core/Translator.php`:

Add this public static method after `getInstance()`:

```php
    /**
     * Directory holding the {locale}.json files.
     * In dist/ the build copies them to resources/i18n; in the repository they
     * live in the Angular workspace (single source of truth).
     */
    public static function langDir(): string
    {
        $dist = __DIR__ . '/../../resources/i18n';
        return is_dir($dist) ? $dist : __DIR__ . '/../../frontend/src/i18n';
    }
```

In `negotiateLocale()`, replace the `$available = array_map(...)` statement with:

```php
        $available = array_map(
            fn($f) => basename($f, '.json'),
            glob(self::langDir() . '/*.json') ?: []
        );
```

Replace the body of `loadTranslations()` with:

```php
        if (in_array($locale, $this->loadedLocales, true)) {
            return;
        }

        $translationFile = self::langDir() . "/{$locale}.json";

        $this->translations[$locale] = file_exists($translationFile)
            ? json_decode((string) file_get_contents($translationFile), true, 512, JSON_THROW_ON_ERROR)
            : [];

        $this->loadedLocales[] = $locale;
```

- [ ] **Step 6: Point `TranslationValidationTest` at the JSON files**

Add this helper method to the class (before `extractKeys`):

```php
    private function localeFile(string $locale): string
    {
        return __DIR__ . "/../../frontend/src/i18n/{$locale}.json";
    }

    private function loadLocale(string $locale): array
    {
        return json_decode((string) file_get_contents($this->localeFile($locale)), true, 512, JSON_THROW_ON_ERROR);
    }
```

Then replace the six file references:

```bash
f=tests/Integration/TranslationValidationTest.php
sed -i 's#\$filePath = __DIR__ \. "/\.\./\.\./resources/lang/{\$locale}\.php";#$filePath = $this->localeFile($locale);#' $f
sed -i 's#require \$filePath#$this->loadLocale($locale)#' $f
sed -i 's#Locale file exists: {\$locale}\.php#Locale file exists: {$locale}.json#' $f
grep -n "resources/lang\|require \$filePath" $f
```
Expected: the final `grep` prints nothing.

- [ ] **Step 7: Update `build.sh`** — replace the line `cp -r resources "$DIST/resources"` with:

```bash
# Translations (single source in the Angular workspace; PHP reads them from resources/i18n)
mkdir -p "$DIST/resources/i18n"
cp frontend/src/i18n/*.json "$DIST/resources/i18n/"
```

- [ ] **Step 8: Run tests**

Run: `vendor/bin/phpunit --filter 'TranslatorTest|TranslationValidationTest|LocalizationTest'` → Expected: all OK.
Run: `composer test` → Expected: baseline.
Run: `./build.sh >/dev/null && ls dist/resources/i18n` → Expected: `de.json  en.json  es.json`.

- [ ] **Step 9: Commit**

```bash
git add -A frontend/src/i18n resources src/Core/Translator.php tests/Integration/Core/TranslatorTest.php tests/Integration/TranslationValidationTest.php build.sh
git commit -m "refactor(i18n): Move translations to JSON in frontend/src/i18n"
```

---

### Task 3: `#[Patch]` route attribute

**Files:**
- Create: `src/Core/Attributes/Patch.php`
- Modify: `src/Core/RouteScanner.php` (imports, `$routeAttributes`, `getHttpMethod()`)
- Test: `tests/Integration/Core/RouteScannerPatchTest.php` (create)

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\Attributes\Patch;
use TP\Core\Attributes\RoutePrefix;
use TP\Core\Request;
use TP\Core\Response;
use TP\Core\RouteScanner;

#[RoutePrefix('/things')]
final class PatchFixtureController
{
    #[Patch('/{id}')]
    public function update(Request $request, array $params): Response
    {
        return Response::ok();
    }
}

class RouteScannerPatchTest extends TestCase
{
    public function testPatchAttributeRegistersPatchRoute(): void
    {
        $routes = (new RouteScanner())->scan(PatchFixtureController::class);

        $this->assertCount(1, $routes);
        $this->assertSame('PATCH', $routes[0]['method']);
        $this->assertSame('/things/{id}', $routes[0]['pattern']);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter RouteScannerPatchTest`
Expected: FAIL — class `TP\Core\Attributes\Patch` not found.

- [ ] **Step 3: Implement** — create `src/Core/Attributes/Patch.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Patch
{
    public function __construct(
        public readonly string $path,
        public readonly string $name = ''
    ) {
    }
}
```

In `src/Core/RouteScanner.php`:
- add `use TP\Core\Attributes\Patch;` after the `Delete` import,
- add `...$method->getAttributes(Patch::class),` after the `Delete` line in `$routeAttributes`,
- add `Patch::class => 'PATCH',` after `Delete::class => 'DELETE',` in `getHttpMethod()`.

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter RouteScannerPatchTest` → Expected: OK.

- [ ] **Step 5: Commit**

```bash
git add src/Core/Attributes/Patch.php src/Core/RouteScanner.php tests/Integration/Core/RouteScannerPatchTest.php
git commit -m "feat(routing): Add #[Patch] route attribute"
```

---

### Task 4: Response helpers for the API

**Files:**
- Modify: `src/Core/Response.php`
- Test: `tests/Integration/Core/ResponseTest.php`

- [ ] **Step 1: Write the failing tests** — append to `ResponseTest`:

```php
    public function testApiErrorWithoutFields(): void
    {
        $response = Response::apiError('errors.not_found', HttpStatus::NOT_FOUND);

        $this->assertSame(HttpStatus::NOT_FOUND, $response->getStatus());
        $this->assertSame(['error' => 'errors.not_found'], json_decode($response->getContent(), true));
    }

    public function testApiErrorWithFields(): void
    {
        $fields = ['name' => ['key' => 'validation.required']];
        $response = Response::apiError('validation.failed', HttpStatus::UNPROCESSABLE_ENTITY, $fields);

        $this->assertSame(
            ['error' => 'validation.failed', 'fields' => $fields],
            json_decode($response->getContent(), true)
        );
    }

    public function testConflictStatusExists(): void
    {
        $this->assertSame(409, HttpStatus::CONFLICT->value);
    }

    public function testNoContent(): void
    {
        $response = Response::noContent();

        $this->assertSame(HttpStatus::NO_CONTENT, $response->getStatus());
        $this->assertSame('', $response->getContent());
    }

    public function testJsonKeepsUnicodeAndSlashesReadable(): void
    {
        $response = Response::json(['text' => 'Apéro / Turnier']);

        $this->assertSame('{"text":"Apéro / Turnier"}', $response->getContent());
    }

    public function testWithHeaderReturnsCopy(): void
    {
        $original = Response::ok('x');
        $copy = $original->withHeader('X-App-Version', '1.0');

        $this->assertSame('1.0', $copy->getHeaders()['X-App-Version']);
        $this->assertArrayNotHasKey('X-App-Version', $original->getHeaders());
    }

    public function testWithCookieStoresOptions(): void
    {
        $response = Response::ok()->withCookie('XSRF-TOKEN', 'abc', httpOnly: false, secure: true);

        $cookie = $response->getCookies()['XSRF-TOKEN'];
        $this->assertSame('abc', $cookie['value']);
        $this->assertFalse($cookie['httponly']);
        $this->assertTrue($cookie['secure']);
        $this->assertSame('Strict', $cookie['samesite']);
        $this->assertSame('/', $cookie['path']);
    }

    public function testSendRecordsLastSentResponse(): void
    {
        Response::resetLastSent();
        $response = Response::json(['a' => 1])->withHeader('X-Test', 'yes')->withCookie('C', 'v');

        ob_start();
        $response->send();
        ob_end_clean();

        $sent = Response::lastSent();
        $this->assertSame(200, $sent['status']);
        $this->assertSame('yes', $sent['headers']['X-Test']);
        $this->assertSame('v', $sent['cookies']['C']['value']);
    }
```

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit --filter ResponseTest`
Expected: FAIL — undefined method `apiError` (and others).

- [ ] **Step 3: Implement** — in `src/Core/Response.php`:

Add `case CONFLICT = 409;` to `HttpStatus` after `METHOD_NOT_ALLOWED`.

Add a static property next to `$cookies`:

```php
    /** @var array{status: int, headers: array<string, string>, cookies: array<string, array>}|null */
    private static ?array $lastSent = null;
```

Replace `json()` with:

```php
    public static function json(array $data, HttpStatus $status = HttpStatus::OK): Response
    {
        return new Response(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * API error response. $key and the keys inside $fields are translation keys;
     * the frontend translates them.
     *
     * @param array<string, array{key: string, params?: array<string, scalar>}> $fields
     */
    public static function apiError(string $key, HttpStatus $status, array $fields = []): Response
    {
        $body = ['error' => $key];
        if ($fields !== []) {
            $body['fields'] = $fields;
        }
        return self::json($body, $status);
    }

    public static function noContent(): Response
    {
        return new Response('', HttpStatus::NO_CONTENT);
    }
```

Add after `getStatus()`:

```php
    public function getContent(): string
    {
        return $this->content;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** @return array<string, array{value: string, path: string, secure: bool, httponly: bool, samesite: string}> */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function withHeader(string $name, string $value): Response
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withCookie(
        string $name,
        string $value,
        bool $httpOnly = true,
        bool $secure = false,
        string $sameSite = 'Strict',
    ): Response {
        $clone = clone $this;
        $clone->cookies[$name] = [
            'value' => $value,
            'path' => '/',
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];
        return $clone;
    }

    /**
     * The last response passed to send(). Used by integration tests, because the
     * CLI SAPI does not expose sent headers.
     *
     * @return array{status: int, headers: array<string, string>, cookies: array<string, array>}|null
     */
    public static function lastSent(): ?array
    {
        return self::$lastSent;
    }

    public static function resetLastSent(): void
    {
        self::$lastSent = null;
    }
```

Replace `send()` with:

```php
    public function send(): void
    {
        self::$lastSent = [
            'status' => $this->status->value,
            'headers' => $this->headers,
            'cookies' => $this->cookies,
        ];

        if (!headers_sent()) {
            http_response_code($this->status->value);

            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }

            foreach ($this->cookies as $name => $options) {
                setcookie($name, $options['value'], [
                    'expires' => 0,
                    'path' => $options['path'],
                    'secure' => $options['secure'],
                    'httponly' => $options['httponly'],
                    'samesite' => $options['samesite'],
                ]);
            }
        }

        echo $this->content;
    }
```

- [ ] **Step 4: Run to verify they pass**

Run: `vendor/bin/phpunit --filter ResponseTest` → Expected: OK.
Run: `composer test` → Expected: baseline.

- [ ] **Step 5: Commit**

```bash
git add src/Core/Response.php tests/Integration/Core/ResponseTest.php
git commit -m "feat(api): Add JSON error, no-content, header and cookie helpers to Response"
```

---

### Task 5: Request — `isApi()` and JSON bodies

**Files:**
- Modify: `src/Core/Request.php`
- Test: `tests/Integration/Core/RequestTest.php`

JSON values go through the existing sanitising, which converts scalars to strings (`true` → `"1"`, `false`/`null` → `""`, `5` → `"5"`). `getInt()`, `getBool()` and the validators already handle strings, so controllers behave the same for form and JSON input.

- [ ] **Step 1: Write the failing tests** — append to `RequestTest`:

```php
    public function testIsApiForApiPaths(): void
    {
        $this->assertTrue($this->makeRequest('GET', '/api/events')->isApi());
        $this->assertTrue($this->makeRequest('GET', '/api')->isApi());
        $this->assertFalse($this->makeRequest('GET', '/events')->isApi());
        $this->assertFalse($this->makeRequest('GET', '/apifoo')->isApi());
    }

    public function testDecodeJsonBodyReturnsArray(): void
    {
        $this->assertSame(['name' => 'Cup', 'tags' => ['a', 'b']], Request::decodeJsonBody('{"name":"Cup","tags":["a","b"]}'));
    }

    public function testDecodeJsonBodyEmptyIsEmptyArray(): void
    {
        $this->assertSame([], Request::decodeJsonBody(''));
        $this->assertSame([], Request::decodeJsonBody('   '));
    }

    public function testDecodeJsonBodyInvalidReturnsNull(): void
    {
        $this->assertNull(Request::decodeJsonBody('{not json'));
        $this->assertNull(Request::decodeJsonBody('"just a string"'));
    }

    public function testCreateFromGlobalsReadsJsonBody(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/things';
        $_SERVER['CONTENT_TYPE'] = 'application/json; charset=utf-8';
        $_GET = [];
        $_POST = [];
        Request::setRawBodyForTesting('{"name":"Cup","capacity":20,"mixed":true}');

        try {
            $req = Request::createFromGlobals();

            $this->assertSame('Cup', $req->getString('name'));
            $this->assertSame(20, $req->getInt('capacity'));
            $this->assertTrue($req->getBool('mixed'));
            $this->assertFalse($req->hasInvalidJsonBody());
        } finally {
            Request::setRawBodyForTesting(null);
            unset($_SERVER['CONTENT_TYPE']);
        }
    }

    public function testCreateFromGlobalsFlagsInvalidJson(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/things';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        Request::setRawBodyForTesting('{broken');

        try {
            $req = Request::createFromGlobals();

            $this->assertTrue($req->hasInvalidJsonBody());
            $this->assertSame([], $req->getPost());
        } finally {
            Request::setRawBodyForTesting(null);
            unset($_SERVER['CONTENT_TYPE']);
        }
    }

    public function testPutAndPatchAndDeleteAreSupportedMethods(): void
    {
        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            $this->assertSame($method, $this->makeRequest($method, '/api/x')->getMethod()->value);
        }
    }
```

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit --filter RequestTest`
Expected: FAIL — undefined method `isApi`.

- [ ] **Step 3: Implement** — in `src/Core/Request.php`:

Add a static property after `$validatedData`:

```php
    private static ?string $rawBodyOverride = null;
```

Add `private readonly bool $invalidJsonBody = false` as the **last** constructor parameter:

```php
    public function __construct(
        private readonly HttpMethod $method,
        private readonly string $uri,
        private readonly array $query,
        private readonly array $post,
        private readonly array $server,
        private readonly array $headers,
        private readonly array $files = [],
        private readonly bool $invalidJsonBody = false,
    ) {
        $this->sanitizeInput();
    }
```

Replace `createFromGlobals()` with:

```php
    public static function createFromGlobals(): Request
    {
        $method = HttpMethod::from($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $query = $_GET ?? [];
        $post = $_POST ?? [];
        $server = $_SERVER ?? [];
        $files = $_FILES ?? [];

        // Extract headers from server variables
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($headerName)] = $value;
            }
        }

        $invalidJsonBody = false;
        $contentType = strtolower((string) ($server['CONTENT_TYPE'] ?? $server['HTTP_CONTENT_TYPE'] ?? ''));
        if (str_starts_with($contentType, 'application/json')) {
            $raw = self::$rawBodyOverride ?? (string) file_get_contents('php://input');
            $decoded = self::decodeJsonBody($raw);
            $invalidJsonBody = $decoded === null;
            $post = $decoded ?? [];
        }

        return new Request($method, $uri, $query, $post, $server, $headers, $files, $invalidJsonBody);
    }

    /**
     * Decode a JSON request body. Returns [] for an empty body and null when the
     * body is not valid JSON or not a JSON object/array.
     */
    public static function decodeJsonBody(string $raw): ?array
    {
        if (trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Integration tests cannot write php://input; they inject the raw body here.
     * Pass null to go back to reading php://input.
     */
    public static function setRawBodyForTesting(?string $body): void
    {
        self::$rawBodyOverride = $body;
    }

    public function hasInvalidJsonBody(): bool
    {
        return $this->invalidJsonBody;
    }

    public function isApi(): bool
    {
        $path = $this->getPath();
        return $path === '/api' || str_starts_with($path, '/api/');
    }
```

- [ ] **Step 4: Run to verify they pass**

Run: `vendor/bin/phpunit --filter RequestTest` → Expected: OK.
Run: `composer test` → Expected: baseline.

- [ ] **Step 5: Commit**

```bash
git add src/Core/Request.php tests/Integration/Core/RequestTest.php
git commit -m "feat(api): Parse JSON request bodies and add Request::isApi()"
```

---

### Task 6: Validation errors carry translation keys

**Files:**
- Modify: `src/Core/ValidationError.php`, `src/Core/Validator.php`, `src/Core/ValidationResult.php`
- Test: `tests/Integration/Core/ValidatorTest.php`

- [ ] **Step 1: Write the failing tests** — append to `ValidatorTest` (it already imports `Validator` and `ValidationRule`; add `use TP\Core\ValidationRule;` / `use TP\Core\Validator;` if missing):

```php
    public function testErrorsCarryTranslationKeys(): void
    {
        $result = (new Validator())->validate(
            ['name' => '', 'code' => 'ab', 'count' => '99', 'kind' => 'x', 'mail' => 'nope'],
            [
                new ValidationRule('name', ['required']),
                new ValidationRule('code', ['min' => 3]),
                new ValidationRule('count', ['max' => 10]),
                new ValidationRule('kind', ['in' => ['a', 'b']]),
                new ValidationRule('mail', ['email']),
            ]
        );

        $this->assertSame(
            [
                'name' => ['key' => 'validation.required'],
                'code' => ['key' => 'validation.min', 'params' => ['min' => 3]],
                'count' => ['key' => 'validation.max', 'params' => ['max' => 10]],
                'kind' => ['key' => 'validation.in', 'params' => ['values' => 'a, b']],
                'mail' => ['key' => 'validation.email'],
            ],
            $result->fieldErrors()
        );
    }

    public function testFieldErrorsKeepsFirstErrorPerField(): void
    {
        $result = (new Validator())->validate(
            ['n' => 'abc'],
            [new ValidationRule('n', ['integer', 'min' => 5])]
        );

        $this->assertSame(['n' => ['key' => 'validation.integer']], $result->fieldErrors());
    }

    public function testExistingMessagesUnchanged(): void
    {
        $result = (new Validator())->validate([], [new ValidationRule('name', ['required'])]);

        $this->assertSame(['The name field is required.'], $result->getErrorMessages());
    }
```

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit --filter ValidatorTest`
Expected: FAIL — undefined method `fieldErrors`.

- [ ] **Step 3: Implement**

`src/Core/ValidationError.php` — replace the constructor:

```php
    /** @param array<string, scalar> $params */
    public function __construct(
        public readonly string $field,
        public readonly string $message,
        public readonly string $key = '',
        public readonly array $params = [],
    ) {
    }
```

`src/Core/Validator.php` — add the key (and params) as extra arguments to every `new ValidationError(...)`:

| Method | Arguments to append |
|---|---|
| `validateRequired` | `, 'validation.required'` |
| `validateString` | `, 'validation.string'` |
| `validateInteger` | `, 'validation.integer'` |
| `validateEmail` | `, 'validation.email'` |
| `validateMin` (both returns) | `, 'validation.min', ['min' => $min]` |
| `validateMax` (both returns) | `, 'validation.max', ['max' => $max]` |
| `validateDate` | `, 'validation.date'` |
| `validateBoolean` | `, 'validation.boolean'` |
| `validateIn` | `, 'validation.in', ['values' => $allowedStr]` |

Example (`validateMin`, string branch):

```php
            return new ValidationError($field, "The {$field} field must be at least {$min} characters.", 'validation.min', ['min' => $min]);
```

`src/Core/ValidationResult.php` — add:

```php
    /**
     * First error per field, in the API's field-error format.
     *
     * @return array<string, array{key: string, params?: array<string, scalar>}>
     */
    public function fieldErrors(): array
    {
        $fields = [];
        foreach ($this->errors as $error) {
            if (isset($fields[$error->field])) {
                continue;
            }
            $entry = ['key' => $error->key];
            if ($error->params !== []) {
                $entry['params'] = $error->params;
            }
            $fields[$error->field] = $entry;
        }
        return $fields;
    }
```

- [ ] **Step 4: Run to verify they pass**

Run: `vendor/bin/phpunit --filter ValidatorTest` → Expected: OK.
Run: `composer test` → Expected: baseline.

- [ ] **Step 5: Commit**

```bash
git add src/Core/ValidationError.php src/Core/Validator.php src/Core/ValidationResult.php tests/Integration/Core/ValidatorTest.php
git commit -m "feat(api): Attach translation keys to validation errors"
```

---

### Task 7: Test harness for JSON API requests

**Files:**
- Modify: `tests/Integration/IntegrationTestCase.php`
- Test: `tests/Integration/Api/HarnessTest.php` (create)

- [ ] **Step 1: Write the failing test** — create `tests/Integration/Api/HarnessTest.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Tests\Integration\IntegrationTestCase;

class HarnessTest extends IntegrationTestCase
{
    public function testJsonHelperExposesStatusHeadersAndDecodedBody(): void
    {
        $response = $this->json('GET', '/health');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('application/json', $response->headers['Content-Type']);
        $this->assertSame('ok', $response->json()['status']);
    }

    public function testRequestDoesNotLeakJsonStateIntoFormRequests(): void
    {
        $this->json('POST', '/health', ['a' => 1]);
        $this->request('GET', '/health');

        $this->assertArrayNotHasKey('CONTENT_TYPE', $_SERVER);
        $this->assertArrayNotHasKey('HTTP_X_XSRF_TOKEN', $_SERVER);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit --filter HarnessTest`
Expected: FAIL — undefined method `json`.

- [ ] **Step 3: Implement** — in `tests/Integration/IntegrationTestCase.php`:

Add imports at the top: `use TP\Core\Request;` and `use TP\Core\Response;`.

At the start of `request()` (before `$_SERVER['REQUEST_METHOD'] = $method;`), reset per-request state:

```php
        // Reset state that a previous json() call may have left behind
        unset($_SERVER['CONTENT_TYPE'], $_SERVER['HTTP_X_XSRF_TOKEN'], $_SERVER['HTTP_X_REQUESTED_WITH']);
        Request::setRawBodyForTesting(null);
        Response::resetLastSent();
```

Replace the `return new TestResponse(...)` line at the end of `request()` with:

```php
        $sent = Response::lastSent();
        return new TestResponse($output, http_response_code(), $sent['headers'] ?? [], $sent['cookies'] ?? []);
```

Add the `json()` helper after `request()`:

```php
    /**
     * Simulate a JSON API request. Sends the session's XSRF token unless
     * $withCsrf is false.
     */
    protected function json(string $method, string $uri, array $data = [], bool $withCsrf = true): TestResponse
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_GET = [];
        $_POST = [];
        Response::resetLastSent();

        $hasBody = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        if ($hasBody) {
            $_SERVER['CONTENT_TYPE'] = 'application/json';
            Request::setRawBodyForTesting(json_encode($data, JSON_THROW_ON_ERROR));
        } else {
            unset($_SERVER['CONTENT_TYPE']);
            Request::setRawBodyForTesting(null);
            $_GET = $data;
        }

        if ($withCsrf && isset($_SESSION['xsrf_token'])) {
            $_SERVER['HTTP_X_XSRF_TOKEN'] = $_SESSION['xsrf_token'];
        } else {
            unset($_SERVER['HTTP_X_XSRF_TOKEN']);
        }

        ob_start();
        try {
            require __DIR__ . '/../../index.php';
            $output = ob_get_clean();
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        } finally {
            Request::setRawBodyForTesting(null);
        }

        $sent = Response::lastSent();
        return new TestResponse($output, http_response_code(), $sent['headers'] ?? [], $sent['cookies'] ?? []);
    }

    /**
     * Log in through the JSON API (fetches a CSRF token first).
     */
    protected function apiLoginAs(string $username, string $password): TestResponse
    {
        $this->json('GET', '/api/auth/me');
        return $this->json('POST', '/api/auth/login', ['username' => $username, 'password' => $password]);
    }
```

Replace the `TestResponse` constructor and add `json()`:

```php
    public function __construct(
        public readonly string $body,
        public readonly int $statusCode,
        /** @var array<string, string> */
        public readonly array $headers = [],
        /** @var array<string, array> */
        public readonly array $cookies = [],
    ) {}

    /** Decoded JSON body; fails the test if the body is not valid JSON. */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException("Response is not JSON: {$this->body}");
        }
        return $decoded;
    }
```

- [ ] **Step 4: Run to verify it passes**

Run: `vendor/bin/phpunit --filter HarnessTest` → Expected: OK.
Run: `composer test` → Expected: baseline.

- [ ] **Step 5: Commit**

```bash
git add tests/Integration/IntegrationTestCase.php tests/Integration/Api/HarnessTest.php
git commit -m "test: Add JSON request helper and response headers to integration harness"
```

---

### Task 8: App version, JSON 404/400/500 and API response headers

**Files:**
- Create: `src/Core/AppVersion.php`
- Modify: `src/Core/Router.php` (end of `handle()`), `src/Core/Application.php` (`run()`, `handleError()`), `frontend/src/i18n/*.json`, `.gitignore`
- Test: `tests/Integration/Core/AppVersionTest.php`, `tests/Integration/Api/ApiBasicsTest.php` (create)

- [ ] **Step 1: Write the failing tests**

`tests/Integration/Core/AppVersionTest.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\AppVersion;

class AppVersionTest extends TestCase
{
    public function testReadsTrimmedVersionFromFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ver');
        file_put_contents($file, "2026-12-04-a1b2c3d\n");

        $this->assertSame('2026-12-04-a1b2c3d', AppVersion::fromFile($file));
        unlink($file);
    }

    public function testFallsBackToDevWhenFileMissingOrEmpty(): void
    {
        $this->assertSame('dev', AppVersion::fromFile('/nonexistent/version.txt'));

        $file = tempnam(sys_get_temp_dir(), 'ver');
        file_put_contents($file, "  \n");
        $this->assertSame('dev', AppVersion::fromFile($file));
        unlink($file);
    }

    public function testCurrentIsDevInRepository(): void
    {
        $this->assertSame('dev', AppVersion::current());
    }
}
```

`tests/Integration/Api/ApiBasicsTest.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Core\Application;
use TP\Core\Request;
use TP\Core\Response;
use TP\Tests\Integration\IntegrationTestCase;

class ApiBasicsTest extends IntegrationTestCase
{
    public function testUnknownApiPathReturnsJson404(): void
    {
        $response = $this->json('GET', '/api/does-not-exist');

        $this->assertSame(404, $response->statusCode);
        $this->assertSame(['error' => 'errors.not_found'], $response->json());
    }

    public function testUnknownHtmlPathStillReturnsHtml404(): void
    {
        $response = $this->request('GET', '/does-not-exist');

        $this->assertSame(404, $response->statusCode);
        $this->assertStringContainsString('<html', $response->body);
    }

    public function testApiResponsesCarryVersionAndNoStoreHeaders(): void
    {
        $response = $this->json('GET', '/api/does-not-exist');

        $this->assertSame('dev', $response->headers['X-App-Version']);
        $this->assertSame('no-store', $response->headers['Cache-Control']);
    }

    public function testHtmlResponsesHaveNoVersionHeader(): void
    {
        $response = $this->request('GET', '/login');

        $this->assertArrayNotHasKey('X-App-Version', $response->headers);
    }

    public function testInvalidJsonBodyReturns400(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/does-not-exist';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        Request::setRawBodyForTesting('{broken');
        Response::resetLastSent();

        ob_start();
        require __DIR__ . '/../../../index.php';
        $body = ob_get_clean();
        Request::setRawBodyForTesting(null);
        unset($_SERVER['CONTENT_TYPE']);

        $this->assertSame(400, http_response_code());
        $this->assertSame(['error' => 'errors.invalid_json'], json_decode($body, true));
    }

    public function testUncaughtExceptionInApiReturnsJson500(): void
    {
        Application::getInstance()->getRouter()->get(
            '/api/test/boom',
            fn(Request $request): Response => throw new \RuntimeException('boom')
        );

        $response = $this->json('GET', '/api/test/boom');

        $this->assertSame(500, $response->statusCode);
        $this->assertSame('errors.server_error', $response->json()['error']);
        $this->assertSame('dev', $response->headers['X-App-Version']);
    }
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit --filter 'AppVersionTest|ApiBasicsTest'`
Expected: FAIL — class `AppVersion` not found; 404 returns HTML.

- [ ] **Step 3: Implement**

Create `src/Core/AppVersion.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Core;

/**
 * Release version written to version.txt by build.sh ("YYYY-MM-DD-<hash>").
 * Falls back to "dev" when running from the repository.
 */
final class AppVersion
{
    private static ?string $current = null;

    public static function current(): string
    {
        return self::$current ??= self::fromFile(__DIR__ . '/../../version.txt');
    }

    public static function fromFile(string $file): string
    {
        if (!is_file($file)) {
            return 'dev';
        }
        $version = trim((string) file_get_contents($file));
        return $version === '' ? 'dev' : $version;
    }
}
```

In `src/Core/Router.php`, replace the final lines of `handle()`:

```php
        // No route matched - return 404
        return Response::notFound('Page not found');
```

with:

```php
        // No route matched - return 404
        if ($request->isApi()) {
            return Response::apiError('errors.not_found', HttpStatus::NOT_FOUND);
        }
        return Response::notFound('Page not found');
```

In `src/Core/Application.php`, replace `run()` with:

```php
    public function run(): void
    {
        $this->boot();
        $request = null;

        try {
            $request = Request::createFromGlobals();
            $this->logger->debug('Processing request', [
                'method' => $request->getMethod()->value,
                'uri' => $request->getUri(),
                'ip' => $request->getIp(),
            ]);

            if ($request->isApi() && $request->hasInvalidJsonBody()) {
                $response = Response::apiError('errors.invalid_json', HttpStatus::BAD_REQUEST);
            } else {
                $response = $this->router->handle($request);
            }

            if ($request->isApi()) {
                $response = $this->withApiHeaders($response);
            }

            $this->logger->debug('Sending response', [
                'status' => $response->getStatus()->value,
                'is_redirect' => $response->isRedirect(),
            ]);

            $response->send();

        } catch (Throwable $e) {
            $this->logger->critical('Application error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->handleError($e, $request);
        }
    }

    private function withApiHeaders(Response $response): Response
    {
        return $response
            ->withHeader('X-App-Version', AppVersion::current())
            ->withHeader('Cache-Control', 'no-store');
    }
```

Replace `handleError()` with:

```php
    private function handleError(Throwable $e, ?Request $request = null): void
    {
        if ($request?->isApi()) {
            $body = ['error' => 'errors.server_error'];
            if ($this->config->isDevelopment()) {
                $body['debug'] = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
            }
            $this->withApiHeaders(Response::json($body, HttpStatus::INTERNAL_SERVER_ERROR))->send();
            return;
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        if ($this->config->isDevelopment()) {
            echo $this->renderDebugError($e);
        } else {
            echo $this->renderProductionError();
        }
    }
```

Add the new translation key `errors.invalid_json`:

```bash
php -r '
$values = ["de" => "Ungültige Anfrage.", "en" => "Invalid request.", "es" => "Solicitud no válida."];
foreach ($values as $l => $text) {
    $f = "frontend/src/i18n/$l.json";
    $d = json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR);
    $d["errors"]["invalid_json"] = $text;
    file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n");
}'
```

Append to `.gitignore`:

```
# Release version (written by build.sh)
/version.txt
```

- [ ] **Step 4: Run to verify they pass**

Run: `vendor/bin/phpunit --filter 'AppVersionTest|ApiBasicsTest'` → Expected: OK.
Run: `composer test` → Expected: baseline.

- [ ] **Step 5: Commit**

```bash
git add src/Core/AppVersion.php src/Core/Router.php src/Core/Application.php frontend/src/i18n .gitignore tests/Integration/Core/AppVersionTest.php tests/Integration/Api/ApiBasicsTest.php
git commit -m "feat(api): JSON 404/400/500 and X-App-Version header for /api responses"
```

---

### Task 9: JSON 401/403 from the auth middlewares

**Files:**
- Modify: `src/Middleware/AuthMiddleware.php`, `src/Middleware/AdminMiddleware.php`
- Test: `tests/Integration/Api/ApiMiddlewareTest.php` (create)

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Core\Application;
use TP\Core\Request;
use TP\Core\Response;
use TP\Middleware\AdminMiddleware;
use TP\Middleware\AuthMiddleware;
use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class ApiMiddlewareTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $router = Application::getInstance()->getRouter();
        $router->get('/api/test/auth-only', fn(Request $r): Response => Response::json(['ok' => true]), [AuthMiddleware::class]);
        $router->get('/api/test/admin-only', fn(Request $r): Response => Response::json(['ok' => true]), [AdminMiddleware::class]);
    }

    public function testAuthMiddlewareReturns401JsonForAnonymous(): void
    {
        $response = $this->json('GET', '/api/test/auth-only');

        $this->assertSame(401, $response->statusCode);
        $this->assertSame(['error' => 'auth.login_required'], $response->json());
    }

    public function testAdminMiddlewareReturns401JsonForAnonymous(): void
    {
        $response = $this->json('GET', '/api/test/admin-only');

        $this->assertSame(401, $response->statusCode);
        $this->assertSame(['error' => 'auth.login_required'], $response->json());
    }

    public function testAdminMiddlewareReturns403JsonForRegularUser(): void
    {
        DB::$users->create('regularuser', 'Pass123!');
        $this->loginAs('regularuser', 'Pass123!');

        $response = $this->json('GET', '/api/test/admin-only');

        $this->assertSame(403, $response->statusCode);
        $this->assertSame(['error' => 'errors.forbidden'], $response->json());
    }

    public function testAdminPasses(): void
    {
        $this->loginAsAdmin();

        $this->assertSame(['ok' => true], $this->json('GET', '/api/test/admin-only')->json());
    }

    public function testHtmlRoutesKeepRedirectBehaviour(): void
    {
        $response = $this->request('GET', '/events');

        $this->assertSame(303, $response->statusCode);
    }
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit --filter ApiMiddlewareTest`
Expected: FAIL — anonymous gets 303 / HTML 403.

- [ ] **Step 3: Implement**

`src/Middleware/AuthMiddleware.php` — replace `handle()`:

```php
    public function handle(Request $request, callable $next): Response
    {
        if (!User::loggedIn()) {
            if ($request->isApi()) {
                return Response::apiError('auth.login_required', HttpStatus::UNAUTHORIZED);
            }
            if ($request->isAjax()) {
                return Response::unauthorized(__('auth.login_required'));
            }
            return Response::redirect('/login');
        }

        return $next($request);
    }
```
Add `use TP\Core\HttpStatus;`.

`src/Middleware/AdminMiddleware.php` — replace `handle()`:

```php
    public function handle(Request $request, callable $next): Response
    {
        if (!User::admin()) {
            if ($request->isApi()) {
                return User::loggedIn()
                    ? Response::apiError('errors.forbidden', HttpStatus::FORBIDDEN)
                    : Response::apiError('auth.login_required', HttpStatus::UNAUTHORIZED);
            }
            return Response::forbidden(__('errors.unauthorized'));
        }

        return $next($request);
    }
```
Add `use TP\Core\HttpStatus;`.

- [ ] **Step 4: Run to verify they pass**

Run: `vendor/bin/phpunit --filter ApiMiddlewareTest` → Expected: OK.
Run: `composer test` → Expected: baseline.

- [ ] **Step 5: Commit**

```bash
git add src/Middleware/AuthMiddleware.php src/Middleware/AdminMiddleware.php tests/Integration/Api/ApiMiddlewareTest.php
git commit -m "feat(api): Return JSON 401/403 from auth middlewares for /api routes"
```

---

### Task 10: CSRF middleware

**Files:**
- Create: `src/Middleware/CsrfMiddleware.php`
- Test: `tests/Integration/Api/CsrfMiddlewareTest.php` (create)

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Core\Application;
use TP\Core\HttpMethod;
use TP\Core\Request;
use TP\Core\Response;
use TP\Middleware\CsrfMiddleware;
use TP\Tests\Integration\IntegrationTestCase;

class CsrfMiddlewareTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $router = Application::getInstance()->getRouter();
        $router->get('/api/test/csrf', fn(Request $r): Response => Response::json(['ok' => true]), [CsrfMiddleware::class]);
        $router->post('/api/test/csrf', fn(Request $r): Response => Response::json(['ok' => true]), [CsrfMiddleware::class]);
        $router->post('/api/test/csrf-clear', function (Request $r): Response {
            $_SESSION = [];
            return Response::noContent();
        }, [CsrfMiddleware::class]);
    }

    public function testGetIssuesReadableTokenCookie(): void
    {
        $response = $this->json('GET', '/api/test/csrf');

        $this->assertSame(200, $response->statusCode);
        $cookie = $response->cookies['XSRF-TOKEN'];
        $this->assertSame($_SESSION['xsrf_token'], $cookie['value']);
        $this->assertSame(64, strlen($cookie['value']));
        $this->assertFalse($cookie['httponly'], 'Angular must be able to read the cookie');
        $this->assertSame('Strict', $cookie['samesite']);
    }

    public function testTokenIsStableWithinSession(): void
    {
        $first = $this->json('GET', '/api/test/csrf')->cookies['XSRF-TOKEN']['value'];
        $second = $this->json('GET', '/api/test/csrf')->cookies['XSRF-TOKEN']['value'];

        $this->assertSame($first, $second);
    }

    public function testPostWithoutHeaderIsRejected(): void
    {
        $this->json('GET', '/api/test/csrf');

        $response = $this->json('POST', '/api/test/csrf', [], withCsrf: false);

        $this->assertSame(403, $response->statusCode);
        $this->assertSame(['error' => 'errors.csrf'], $response->json());
        $this->assertArrayHasKey('XSRF-TOKEN', $response->cookies, 'Rejection re-issues the cookie so the client can retry');
    }

    public function testPostWithValidHeaderPasses(): void
    {
        $this->json('GET', '/api/test/csrf');

        $response = $this->json('POST', '/api/test/csrf');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['ok' => true], $response->json());
    }

    public function testCookieReflectsTokenRegeneratedByHandler(): void
    {
        $this->json('GET', '/api/test/csrf');
        $old = $_SESSION['xsrf_token'];

        $response = $this->json('POST', '/api/test/csrf-clear');

        $this->assertSame(204, $response->statusCode);
        $this->assertNotSame($old, $response->cookies['XSRF-TOKEN']['value']);
        $this->assertSame($_SESSION['xsrf_token'], $response->cookies['XSRF-TOKEN']['value']);
    }

    public function testWrongTokenIsRejected(): void
    {
        $_SESSION['xsrf_token'] = str_repeat('a', 64);
        $request = new Request(HttpMethod::POST, '/api/test/csrf', [], [], [], ['x-xsrf-token' => str_repeat('b', 64)]);

        $response = (new CsrfMiddleware())->handle($request, fn() => Response::json(['ok' => true]));

        $this->assertSame(403, $response->getStatus()->value);
    }
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit --filter CsrfMiddlewareTest`
Expected: FAIL — class `CsrfMiddleware` not found.

- [ ] **Step 3: Implement** — create `src/Middleware/CsrfMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Middleware;

use TP\Core\Config;
use TP\Core\HttpMethod;
use TP\Core\HttpStatus;
use TP\Core\MiddlewareInterface;
use TP\Core\Request;
use TP\Core\Response;

/**
 * Double-submit CSRF protection for the JSON API.
 *
 * Every response carries a readable XSRF-TOKEN cookie holding the session's token.
 * Non-safe requests must echo it in the X-XSRF-TOKEN header.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public const SESSION_KEY = 'xsrf_token';
    public const COOKIE_NAME = 'XSRF-TOKEN';
    public const HEADER_NAME = 'X-XSRF-TOKEN';

    private const SAFE_METHODS = [HttpMethod::GET, HttpMethod::HEAD, HttpMethod::OPTIONS];

    public function handle(Request $request, callable $next): Response
    {
        $expected = self::token();

        if (!in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            $sent = $request->getHeader(self::HEADER_NAME) ?? '';
            if (!hash_equals($expected, $sent)) {
                return $this->withTokenCookie(Response::apiError('errors.csrf', HttpStatus::FORBIDDEN));
            }
        }

        // Read the token again: the handler may have reset the session (logout).
        return $this->withTokenCookie($next($request));
    }

    public static function token(): string
    {
        $token = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
        }
        return $token;
    }

    private function withTokenCookie(Response $response): Response
    {
        return $response->withCookie(
            self::COOKIE_NAME,
            self::token(),
            httpOnly: false,
            secure: (bool) Config::getInstance()->get('app.secure', false),
        );
    }
}
```

- [ ] **Step 4: Run to verify they pass**

Run: `vendor/bin/phpunit --filter CsrfMiddlewareTest` → Expected: 6 tests OK.
Run: `composer test` → Expected: baseline.

- [ ] **Step 5: Commit**

```bash
git add src/Middleware/CsrfMiddleware.php tests/Integration/Api/CsrfMiddlewareTest.php
git commit -m "feat(security): Add CSRF middleware with XSRF-TOKEN cookie for the API"
```

---

### Task 11: Auth API (`/api/auth/me`, `/login`, `/logout`)

**Files:**
- Create: `src/Controllers/Api/AuthController.php`
- Modify: `frontend/src/i18n/*.json` (new key `validation.failed`)
- Test: `tests/Integration/Api/AuthApiTest.php` (create)

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class AuthApiTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    public function testMeReturnsNullForAnonymousAndIssuesCsrfCookie(): void
    {
        $response = $this->json('GET', '/api/auth/me');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['user' => null], $response->json());
        $this->assertArrayHasKey('XSRF-TOKEN', $response->cookies);
    }

    public function testLoginReturnsUserAndSetsSession(): void
    {
        DB::$users->create('jane', 'Pass123!', firstName: 'Jane', lastName: 'Doe');

        $response = $this->apiLoginAs('jane', 'Pass123!');

        $this->assertSame(200, $response->statusCode);
        $user = $response->json()['user'];
        $this->assertSame('jane', $user['username']);
        $this->assertSame('Jane', $user['firstName']);
        $this->assertSame('Doe', $user['lastName']);
        $this->assertSame('Jane Doe', $user['displayName']);
        $this->assertFalse($user['isAdmin']);
        $this->assertIsInt($user['id']);
        $this->assertSame($user['id'], $_SESSION['user_id']);
    }

    public function testMeReturnsLoggedInAdmin(): void
    {
        $this->apiLoginAs('admin', 'Admin123!');

        $user = $this->json('GET', '/api/auth/me')->json()['user'];

        $this->assertSame('admin', $user['username']);
        $this->assertTrue($user['isAdmin']);
    }

    public function testLoginWithWrongPasswordReturns422(): void
    {
        $response = $this->apiLoginAs('admin', 'wrong');

        $this->assertSame(422, $response->statusCode);
        $this->assertSame(['error' => 'auth.login_failed'], $response->json());
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testLoginWithMissingFieldsReturnsFieldErrors(): void
    {
        $this->json('GET', '/api/auth/me');

        $response = $this->json('POST', '/api/auth/login', ['username' => '']);

        $this->assertSame(422, $response->statusCode);
        $this->assertSame(
            [
                'error' => 'validation.failed',
                'fields' => [
                    'username' => ['key' => 'validation.required'],
                    'password' => ['key' => 'validation.required'],
                ],
            ],
            $response->json()
        );
    }

    public function testLoginWithoutCsrfTokenIsRejected(): void
    {
        $this->json('GET', '/api/auth/me');

        $response = $this->json('POST', '/api/auth/login', ['username' => 'admin', 'password' => 'Admin123!'], withCsrf: false);

        $this->assertSame(403, $response->statusCode);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function testLogoutClearsSessionAndIssuesFreshToken(): void
    {
        $this->apiLoginAs('admin', 'Admin123!');
        $oldToken = $_SESSION['xsrf_token'];

        $response = $this->json('POST', '/api/auth/logout');

        $this->assertSame(204, $response->statusCode);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
        $this->assertNotSame($oldToken, $response->cookies['XSRF-TOKEN']['value']);
        $this->assertSame(['user' => null], $this->json('GET', '/api/auth/me')->json());
    }

    public function testCanLogInAgainRightAfterLogout(): void
    {
        $this->apiLoginAs('admin', 'Admin123!');
        $this->json('POST', '/api/auth/logout');

        $response = $this->json('POST', '/api/auth/login', ['username' => 'admin', 'password' => 'Admin123!']);

        $this->assertSame(200, $response->statusCode);
    }

    public function testLogoutAnonymousReturns401(): void
    {
        $this->json('GET', '/api/auth/me');

        $response = $this->json('POST', '/api/auth/logout');

        $this->assertSame(401, $response->statusCode);
        $this->assertSame(['error' => 'auth.login_required'], $response->json());
    }

    public function testMeForDeletedUserReturnsNullAndClearsSession(): void
    {
        $id = DB::$users->create('ghost', 'Pass123!');
        $this->apiLoginAs('ghost', 'Pass123!');
        DB::$users->delete($id);

        $this->assertSame(['user' => null], $this->json('GET', '/api/auth/me')->json());
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `vendor/bin/phpunit --filter AuthApiTest`
Expected: FAIL — 404 `errors.not_found` for `/api/auth/me`.

- [ ] **Step 3: Implement** — create `src/Controllers/Api/AuthController.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Controllers\Api;

use TP\Core\Attributes\Get;
use TP\Core\Attributes\Middleware;
use TP\Core\Attributes\Post;
use TP\Core\Attributes\RoutePrefix;
use TP\Core\HttpStatus;
use TP\Core\Request;
use TP\Core\Response;
use TP\Core\ValidationRule;
use TP\Middleware\AuthMiddleware;
use TP\Middleware\CsrfMiddleware;
use TP\Models\DB;
use TP\Models\User;

#[RoutePrefix('/api/auth')]
#[Middleware(CsrfMiddleware::class)]
final class AuthController
{
    #[Get('/me')]
    public function me(Request $request): Response
    {
        $id = User::id();
        if ($id === null) {
            return Response::json(['user' => null]);
        }

        $user = DB::$users->get($id);
        if ($user === null) {
            // Account was deleted while logged in
            $this->resetSession();
            return Response::json(['user' => null]);
        }

        return Response::json(['user' => self::userJson($user)]);
    }

    #[Post('/login')]
    public function login(Request $request): Response
    {
        $validation = $request->validate([
            new ValidationRule('username', ['required', 'string']),
            new ValidationRule('password', ['required', 'string']),
        ]);

        if (!$validation->isValid) {
            return Response::apiError('validation.failed', HttpStatus::UNPROCESSABLE_ENTITY, $validation->fieldErrors());
        }

        $data = $request->getValidatedData();
        $user = User::authenticate($data['username'], $data['password']);

        if ($user === null) {
            return Response::apiError('auth.login_failed', HttpStatus::UNPROCESSABLE_ENTITY);
        }

        session_regenerate_id(true);
        User::setCurrent($user);

        // Reload so the response has the same shape as /me (names included)
        return Response::json(['user' => self::userJson(DB::$users->get($user->id) ?? $user)]);
    }

    #[Post('/logout')]
    #[Middleware(AuthMiddleware::class)]
    public function logout(Request $request): Response
    {
        $this->resetSession();
        return Response::noContent();
    }

    /**
     * @return array{id: int, username: string, firstName: ?string, lastName: ?string, displayName: string, isAdmin: bool}
     */
    public static function userJson(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'firstName' => $user->firstName,
            'lastName' => $user->lastName,
            'displayName' => $user->displayName(),
            'isAdmin' => $user->isAdmin,
        ];
    }

    /**
     * Clear the session but keep it alive, so CsrfMiddleware can issue a fresh
     * token in the same response (session_destroy() would leave the client with
     * a stale token and make the next POST fail).
     */
    private function resetSession(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
```

Add the translation key `validation.failed`:

```bash
php -r '
$values = ["de" => "Bitte überprüfe deine Eingaben.", "en" => "Please check your input.", "es" => "Por favor, revisa tus datos."];
foreach ($values as $l => $text) {
    $f = "frontend/src/i18n/$l.json";
    $d = json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR);
    $d["validation"]["failed"] = $text;
    file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n");
}'
```

- [ ] **Step 4: Run to verify they pass**

Run: `vendor/bin/phpunit --filter AuthApiTest` → Expected: 10 tests OK.
Run: `composer test` → Expected: baseline. The old `/login` and `/logout` HTML routes must still pass their existing tests.

- [ ] **Step 5: Commit**

```bash
git add src/Controllers/Api/AuthController.php frontend/src/i18n tests/Integration/Api/AuthApiTest.php
git commit -m "feat(api): Add /api/auth/me, /api/auth/login and /api/auth/logout"
```

---

### Task 12: Every API error key exists in all languages

**Files:**
- Modify: `tests/Integration/TranslationValidationTest.php`

- [ ] **Step 1: Write the test** — add to `TranslationValidationTest`:

```php
    /**
     * Every translation key the API can return (apiError('…') calls and the
     * validator's validation.* keys) must exist in every locale.
     */
    public function testAllApiErrorKeysExistInAllLocales(): void
    {
        $keys = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../src'));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            preg_match_all("/apiError\(\s*'([a-z0-9_.]+)'/", $source, $m);
            array_push($keys, ...$m[1]);
            if (str_ends_with($file->getPathname(), 'Core/Validator.php')) {
                preg_match_all("/'(validation\.[a-z_]+)'/", $source, $m);
                array_push($keys, ...$m[1]);
            }
        }
        $keys = array_values(array_unique($keys));

        $this->assertContains('errors.not_found', $keys, 'sanity: scan found the router key');
        $this->assertContains('validation.required', $keys, 'sanity: scan found validator keys');

        foreach (self::LOCALES as $locale) {
            $available = $this->extractKeys($this->loadLocale($locale));
            $missing = array_diff($keys, $available);
            $this->assertEmpty($missing, "Locale '{$locale}' is missing API keys: " . implode(', ', $missing));
        }
    }
```

- [ ] **Step 2: Run it**

Run: `vendor/bin/phpunit --filter testAllApiErrorKeysExistInAllLocales`
Expected: PASS (all keys were added in Tasks 8 and 11; `errors.not_found`, `errors.forbidden`, `errors.csrf`, `errors.server_error`, `auth.login_required`, `auth.login_failed` and `validation.*` already existed). If it fails, add the reported keys to all three JSON files and re-run.

- [ ] **Step 3: Verify the test catches a missing key**

```bash
cp frontend/src/i18n/es.json /tmp/es.json.bak
php -r '$f="frontend/src/i18n/es.json"; $d=json_decode(file_get_contents($f),true); unset($d["errors"]["csrf"]); file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));'
vendor/bin/phpunit --filter testAllApiErrorKeysExistInAllLocales; cp /tmp/es.json.bak frontend/src/i18n/es.json
```
Expected: FAIL mentioning `errors.csrf`; the file is restored afterwards (`git diff --stat frontend/src/i18n` shows nothing).

- [ ] **Step 4: Commit**

```bash
git add tests/Integration/TranslationValidationTest.php
git commit -m "test(i18n): Ensure every API error key exists in all locales"
```

---

### Task 13: Documentation

**Files:**
- Create: `docs/API.md`
- Modify: `CLAUDE.md`, `docs/TESTING.md`, `README.md`

- [ ] **Step 1: Create `docs/API.md`**

````markdown
# JSON API

The Angular frontend talks to PHP through a JSON API under `/api/*`. API controllers live in
`src/Controllers/Api/`. The old HTML controllers remain until the UI cut-over (see
`docs/superpowers/specs/2026-11-20-ui-refactor-design.md`).

## Conventions

- Request and response bodies are JSON (`Content-Type: application/json`). JSON values are read
  through the normal `Request` getters (`getString()`, `getInt()`, `getBool()`, `validate()`).
- Every API controller has `#[Middleware(CsrfMiddleware::class)]` at class level.
- Every `/api/*` response has `X-App-Version` (contents of `version.txt`, `dev` in the
  repository) and `Cache-Control: no-store`.

## Errors

```json
{ "error": "validation.failed",
  "fields": { "username": { "key": "validation.required" },
              "code":     { "key": "validation.min", "params": { "min": 3 } } } }
```

`error` and every `fields.*.key` are translation keys (`frontend/src/i18n/*.json`); the frontend
translates them. Use `Response::apiError($key, $status, $validation->fieldErrors())`.

| Status | Meaning | Typical key |
|---|---|---|
| 400 | Body is not valid JSON | `errors.invalid_json` |
| 401 | Not logged in | `auth.login_required` |
| 403 | Logged in but not allowed, or bad CSRF token | `errors.forbidden`, `errors.csrf` |
| 404 | Unknown route or resource | `errors.not_found` |
| 409 | Conflict (event full, locked, registration closed) | feature-specific |
| 422 | Validation failed / wrong credentials | `validation.failed`, `auth.login_failed` |
| 500 | Unexpected error | `errors.server_error` |

## CSRF

Double-submit token: every API response sets a readable `XSRF-TOKEN` cookie (`SameSite=Strict`,
`Secure` when `APP_SECURE` is true). Requests other than GET/HEAD/OPTIONS must send the same
value in the `X-XSRF-TOKEN` header, otherwise `403 errors.csrf`. A 403 response also carries a
fresh cookie, so clients retry once.

## Auth endpoints

| Method | Path | Body | Response |
|---|---|---|---|
| GET | `/api/auth/me` | – | `{ "user": User \| null }` |
| POST | `/api/auth/login` | `{ "username", "password" }` | `200 { "user": User }`, `422 auth.login_failed` |
| POST | `/api/auth/logout` | – | `204` (requires login) |

`User` = `{ id, username, firstName, lastName, displayName, isAdmin }`.

## Testing

Use `$this->json($method, $uri, $data)` in integration tests; it sends the session's CSRF token
automatically (`withCsrf: false` to test rejection). `$this->apiLoginAs($user, $pass)` logs in
through the API. Responses expose `->json()`, `->headers` and `->cookies`.
````

- [ ] **Step 2: Update `CLAUDE.md`**

- Under "Key Patterns", after the **Controllers** paragraph, add:

```markdown
**JSON API**: New API controllers live in `src/Controllers/Api/` under `/api/*` and return JSON via `Response::json()` / `Response::apiError()`. Errors are translation keys, never translated text. Every API controller needs `#[Middleware(CsrfMiddleware::class)]`. See [docs/API.md](docs/API.md).
```

- Replace the **Translation System** sentence `Translations defined in \`resources/lang/{de,en,es}.php\` (German is the primary language; keep all three in sync).` with:

```markdown
Translations are defined in `frontend/src/i18n/{de,en,es}.json` (single source for PHP and the Angular app; German is the primary language; keep all three in sync).
```

- In the Documentation list, change `- **[Translation System](resources/lang/de.php)** - i18n strings` to `- **[Translation System](frontend/src/i18n/de.json)** - i18n strings` and add `- **[JSON API](docs/API.md)** - API conventions, errors, CSRF`.

- [ ] **Step 3: Update test counts**

Run `composer test 2>&1 | grep '^Tests:'` and note the number `N`. Replace `448` with `N` in `README.md` (`(448 tests)`), `CLAUDE.md` (`with 448 tests`) and `docs/TESTING.md` (both occurrences, and update the `Assertions:` number on the same line from the same output).

- [ ] **Step 4: Run everything**

Run: `composer test` → Expected: baseline status, all tests pass.
Run: `grep -rn "resources/lang" --include=*.md --include=*.php --include=*.sh . | grep -v -e vendor -e dist -e docs/superpowers` → Expected: no output.

- [ ] **Step 5: Commit**

```bash
git add docs/API.md CLAUDE.md docs/TESTING.md README.md
git commit -m "docs: Document JSON API conventions and new translation location"
```

---

## Done criteria for plan 1

- `composer test` passes (baseline warnings/deprecations only), with the new API tests.
- `curl -i localhost:5000/api/auth/me` (with `./run.sh` running) returns `200`, JSON `{"user":null}`, a `Set-Cookie: XSRF-TOKEN=…` header and `X-App-Version: dev`.
- The existing HTML UI works unchanged (manual smoke test: log in, open an event, unregister — the label reads "Turnier abmelden").
