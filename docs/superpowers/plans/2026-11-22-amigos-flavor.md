# Amigos flavour Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** One code base, two build-time flavours (`elfaro`, `amigos`). The Amigos flavour replaces the old plain-PHP "Amigos de Tauro" app and its database `amigoDb` is migrated in place.

**Architecture:** Each flavour lives in `frontend/flavors/<name>/` (`flavor.json`, `theme.css`, `index.html`, `i18n/{de,en,es}.json` overrides, `assets/`). PHP reads the active `flavor.json` through `TP\Core\Flavor`: `config/flavor.json` in a dist, otherwise `APP_FLAVOR` (default `elfaro`). Routes of switched-off features answer 404 via `#[Feature('…')]`. Angular picks the flavour with build configurations (`-c production,amigos`), which swap `active-flavor.ts` and set that flavour's assets, theme and `index.html`. Texts = base `frontend/src/i18n/*.json` deep-merged with the flavour override at runtime, in PHP and in Angular.

**Tech Stack:** PHP 8.3 (strict types, mysqli, PHPUnit 10.5), MariaDB, Angular 22 (signals, signal forms, Vitest), Playwright, Bash.

**Spec:** `docs/superpowers/specs/2026-11-22-amigos-flavor-design.md`.

**Deviations from the spec (both agreed in the conversation on 2026-11-22):**
- Flavour folders live in `frontend/flavors/<name>/`, not in a root `flavors/` folder. Angular builds assets/styles reliably only inside its workspace.
- Text overrides are merged at runtime (PHP `Translator`, Angular `loadLocale`), not generated at build time. There are no generated files.

**Refinements while planning (in the spirit of the spec, documented here):**
- `features.description` is its own switch. The spec turns "prices/description/deadline" off for Amigos.
- `features.userFields` lists *all* optional user fields per flavour: El Faro `["rfeg", "memberNumber"]`, Amigos `["phone", "handicap", "priority"]`. The El Faro-only fields RFEG/member number are therefore hidden for Amigos.
- `flavor.json` has `website` (header logo link, `null` = no link).
- The name-clash "warning" is a non-blocking flag `nameConflict` on `AdminUserDto`. The user list and form show it; saving is never blocked, because two real people may share a name.
- Fields of switched-off features (prices, description, deadline, gender) are only hidden in the UI. The API still accepts them (no data exists for Amigos, YAGNI).

## Ground rules for every task

- Read `CLAUDE.md` first. **Never run PHPUnit twice in parallel, and never alongside `npm run e2e`.**
- PHP tests: `composer test -- --filter <Name>`; the full suite is `composer test`. Frontend (inside `frontend/`): `npm test`, `npm run lint`, `npm run check:i18n`.
- Verify by exit code (`echo $?`), not by grepping summaries.
- After touching DTOs or routes: `composer api:generate`, commit `frontend/src/app/core/api/generated.ts`, then `composer api:check`.
- New translation keys go into **all three** `frontend/src/i18n/{de,en,es}.json`.
- No German in code/templates; everything goes through i18n keys.
- `database/init.sql` is split on `;` by the test harness, so no semicolons inside SQL strings or comments.
- **Personal data:** `/home/hempe/git/amigos/filiusag_amigoDb.sql` must never be copied into the repo and rows must never be printed. Only aggregates.
- Commit after every task with a `feat:`/`test:`/`docs:`/`chore:` message ending in
  `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## File structure

**New (backend):**
- `src/Core/Flavor.php`: loads and validates the active `flavor.json`; feature queries; test overrides.
- `src/Core/Attributes/Feature.php`: route attribute `#[Feature('guests')]`.
- `database/migrations/amigos-legacy.sql`: one-off: name split, legacy-table drop, check list.
- `bin/amigos-dry-run.sh`: loads an export into a throwaway MariaDB, migrates, prints aggregates.
- Tests: `tests/Integration/Core/FlavorTest.php`, `tests/Integration/Core/FeatureRouteTest.php`, `tests/Integration/Core/FlavorTranslationsTest.php`, `tests/Integration/Models/AmigosMigrationTest.php`, `tests/Integration/Models/PriorityWaitlistTest.php`, `tests/Integration/Api/NameLoginApiTest.php`, `tests/Integration/Api/UserFieldsApiTest.php`.

**New (frontend):**
- `frontend/flavors/elfaro/{flavor.json,theme.css,index.html,i18n/{de,en,es}.json,assets/*}`
- `frontend/flavors/amigos/{flavor.json,theme.css,index.html,i18n/{de,en,es}.json,assets/*}`
- `frontend/src/app/core/flavor/flavor.ts`: types, `FLAVOR` token, `Features` service, `featureGuard`.
- `frontend/src/app/core/flavor/active-flavor.ts`, `active-flavor.amigos.ts`: the build-time swap point.
- `frontend/src/app/core/flavor/flavor.spec.ts`
- `frontend/e2e/amigos.spec.ts`, `e2e/seed-amigos.sql`

**Modified (main ones):** `src/Core/{Application,Translator,RouteScanner,Router}.php`, `src/Models/{User,UserRepository,EventRepository,EventRegistration}.php`, `src/Api/Dto/{AdminUserDto,UserCreateRequest,UserUpdateRequest,ParticipantDto}.php`, `src/Controllers/Api/{EventsController,EventAdminController,SocialEventsController,SocialEventAdminController,UserAdminController}.php`, `database/init.sql`, `.htaccess`, `build.sh`, `run.sh`, `bin/verify-dist.php`, `phpunit.xml`, `tests/Integration/IntegrationTestCase.php`, `frontend/angular.json`, `frontend/proxy.conf.json`, `frontend/src/app/core/i18n/i18n.ts`, `frontend/src/styles.css`, `frontend/scripts/check-i18n-keys.mts`, feature components (event form, event admin, calendar card, user form/list, event detail, login, header, routes), `e2e/run.sh`, `e2e/stack.sh`, `frontend/playwright.config.ts`, `.github/workflows/ci.yml`, docs.

**Removed/moved:** root `site.webmanifest`, `browserconfig.xml`, `favicon*`, `apple-touch-icon.png`, `web-app-manifest-*.png` and `frontend/public/favicon.ico` → `frontend/flavors/elfaro/assets/`. `frontend/src/index.html` → `frontend/flavors/elfaro/index.html` (plus the Amigos copy).

---

# Phase 1: Backend flavour core

### Task 1: `Flavor` class and both `flavor.json` files

**Files:**
- Create: `frontend/flavors/elfaro/flavor.json`, `frontend/flavors/amigos/flavor.json`
- Create: `frontend/flavors/elfaro/i18n/{de,en,es}.json`, `frontend/flavors/amigos/i18n/{de,en,es}.json` (all `{}` for now)
- Create: `src/Core/Flavor.php`
- Modify: `phpunit.xml` (env), `tests/Integration/IntegrationTestCase.php` (`setUp`)
- Test: `tests/Integration/Core/FlavorTest.php`

- [ ] **Step 1: Create the flavour files**

`frontend/flavors/elfaro/flavor.json` (today's behaviour; timezone = today's config default):

```json
{
  "name": "GOLF EL FARO",
  "shortName": "el faro",
  "timezone": "Europe/Zurich",
  "usernameOrder": "firstLast",
  "website": "https://www.golfelfaro.es",
  "features": {
    "socialEvents": true,
    "guests": true,
    "prices": true,
    "description": true,
    "deadline": true,
    "genderSplit": true,
    "waitlistOrder": "timestamp",
    "userFields": ["rfeg", "memberNumber"]
  }
}
```

`frontend/flavors/amigos/flavor.json`:

```json
{
  "name": "Amigos de Tauro",
  "shortName": "Amigos",
  "timezone": "Atlantic/Canary",
  "usernameOrder": "lastFirst",
  "website": null,
  "features": {
    "socialEvents": false,
    "guests": false,
    "prices": false,
    "description": false,
    "deadline": false,
    "genderSplit": false,
    "waitlistOrder": "priority",
    "userFields": ["phone", "handicap", "priority"]
  }
}
```

Six i18n files with content `{}` followed by a trailing newline.

- [ ] **Step 2: Write the failing test** `tests/Integration/Core/FlavorTest.php`

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use TP\Core\Flavor;

class FlavorTest extends TestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        Flavor::reset();
    }

    protected function tearDown(): void
    {
        $_ENV['APP_FLAVOR'] = 'elfaro';
        Flavor::reset();
        if ($this->tmp !== '' && is_file($this->tmp)) {
            unlink($this->tmp);
        }
    }

    private function writeJson(string $json): string
    {
        $this->tmp = tempnam(sys_get_temp_dir(), 'flavor') . '.json';
        file_put_contents($this->tmp, $json);
        return $this->tmp;
    }

    public function testDefaultIsElFaroWithEverythingOn(): void
    {
        $this->assertSame('elfaro', Flavor::name());
        foreach (['socialEvents', 'guests', 'prices', 'description', 'deadline', 'genderSplit'] as $feature) {
            $this->assertTrue(Flavor::enabled($feature), $feature);
        }
        $this->assertFalse(Flavor::waitlistByPriority());
        $this->assertTrue(Flavor::hasUserField('rfeg'));
        $this->assertFalse(Flavor::hasUserField('phone'));
        $this->assertSame('Europe/Zurich', Flavor::timezone());
    }

    public function testAmigosFromEnvironment(): void
    {
        $_ENV['APP_FLAVOR'] = 'amigos';
        Flavor::reset();

        $this->assertSame('amigos', Flavor::name());
        $this->assertFalse(Flavor::enabled('guests'));
        $this->assertTrue(Flavor::waitlistByPriority());
        $this->assertTrue(Flavor::hasUserField('priority'));
        $this->assertSame('lastFirst', Flavor::usernameOrder());
        $this->assertSame('Atlantic/Canary', Flavor::timezone());
    }

    public function testUnknownFlavourFailsLoudly(): void
    {
        $_ENV['APP_FLAVOR'] = 'nope';
        Flavor::reset();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown flavour "nope"');
        Flavor::name();
    }

    public function testEveryShippedFlavourIsValid(): void
    {
        foreach (glob(__DIR__ . '/../../../frontend/flavors/*/flavor.json') ?: [] as $file) {
            $this->assertIsArray(Flavor::parse($file), $file);
        }
    }

    public function testBrokenJsonFails(): void
    {
        $this->expectException(RuntimeException::class);
        Flavor::parse($this->writeJson('{ nope'));
    }

    public function testInvalidValuesFail(): void
    {
        $base = json_decode((string) file_get_contents(__DIR__ . '/../../../frontend/flavors/amigos/flavor.json'), true);
        $cases = [
            'timezone' => ['timezone' => 'Mars/Base'],
            'usernameOrder' => ['usernameOrder' => 'middle'],
            'waitlistOrder' => ['features' => ['waitlistOrder' => 'random'] + $base['features']],
            'userFields' => ['features' => ['userFields' => ['shoeSize']] + $base['features']],
            'guests' => ['features' => ['guests' => 'yes'] + $base['features']],
        ];
        foreach ($cases as $field => $change) {
            try {
                Flavor::parse($this->writeJson((string) json_encode($change + $base)));
                $this->fail("$field: expected an exception");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($field, $e->getMessage());
            }
        }
    }

    public function testOverrideAndReset(): void
    {
        Flavor::override(['guests' => false, 'waitlistOrder' => 'priority', 'userFields' => ['handicap']]);

        $this->assertFalse(Flavor::enabled('guests'));
        $this->assertTrue(Flavor::enabled('socialEvents'));
        $this->assertTrue(Flavor::waitlistByPriority());
        $this->assertTrue(Flavor::hasUserField('handicap'));
        $this->assertFalse(Flavor::hasUserField('rfeg'));

        Flavor::reset();
        $this->assertTrue(Flavor::enabled('guests'));
    }
}
```

- [ ] **Step 3: Run it, expect FAIL** (`Class "TP\Core\Flavor" not found`)

Run: `composer test -- --filter FlavorTest`

- [ ] **Step 4: Implement** `src/Core/Flavor.php`

```php
<?php

declare(strict_types=1);

namespace TP\Core;

use RuntimeException;

/**
 * The club flavour this installation runs as (build-time choice, see docs/DEPLOYMENT.md).
 * dist: config/flavor.json (+ resources/i18n/flavor/); repository: frontend/flavors/$APP_FLAVOR (default elfaro).
 */
final class Flavor
{
    public const SWITCHES = ['socialEvents', 'guests', 'prices', 'description', 'deadline', 'genderSplit'];
    public const USER_FIELDS = ['rfeg', 'memberNumber', 'phone', 'handicap', 'priority'];

    /** @var array<string, mixed>|null */
    private static ?array $config = null;
    private static string $flavorName = '';
    private static ?string $textDir = null;
    /** @var array<string, mixed> */
    private static array $overrides = [];

    public static function name(): string
    {
        self::load();
        return self::$flavorName;
    }

    public static function enabled(string $feature): bool
    {
        return self::features()[$feature] === true;
    }

    public static function waitlistByPriority(): bool
    {
        return self::features()['waitlistOrder'] === 'priority';
    }

    public static function hasUserField(string $field): bool
    {
        return in_array($field, self::features()['userFields'], true);
    }

    public static function timezone(): string
    {
        return self::load()['timezone'];
    }

    /** 'firstLast' or 'lastFirst': word order of legacy usernames. */
    public static function usernameOrder(): string
    {
        return self::load()['usernameOrder'];
    }

    /** Folder with the flavour's {locale}.json text overrides, null when there is none. */
    public static function textDir(): ?string
    {
        self::load();
        return self::$textDir;
    }

    /** Tests: change single features (merged over the loaded flavour) until reset(). */
    public static function override(array $features): void
    {
        self::$overrides = $features + self::$overrides;
    }

    public static function reset(): void
    {
        self::$config = null;
        self::$flavorName = '';
        self::$textDir = null;
        self::$overrides = [];
    }

    /** @return array<string, mixed> */
    private static function features(): array
    {
        return self::$overrides + self::load()['features'];
    }

    /** @return array<string, mixed> */
    private static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }
        $root = dirname(__DIR__, 2);
        $dist = "$root/config/flavor.json";
        if (is_file($dist)) {
            self::$config = self::parse($dist);
            self::$flavorName = (string) (self::$config['id'] ?? 'dist');
            self::$textDir = is_dir("$root/resources/i18n/flavor") ? "$root/resources/i18n/flavor" : null;
            return self::$config;
        }

        $name = (string) ($_ENV['APP_FLAVOR'] ?? (getenv('APP_FLAVOR') ?: 'elfaro'));
        $dir = "$root/frontend/flavors/$name";
        if (!preg_match('/^[a-z]+$/', $name) || !is_file("$dir/flavor.json")) {
            throw new RuntimeException("Unknown flavour \"$name\" (frontend/flavors/<name>/flavor.json)");
        }
        self::$config = self::parse("$dir/flavor.json");
        self::$flavorName = $name;
        self::$textDir = is_dir("$dir/i18n") ? "$dir/i18n" : null;
        return self::$config;
    }

    /**
     * Read and validate a flavor.json. Throws with the offending field name.
     *
     * @return array<string, mixed>
     */
    public static function parse(string $file): array
    {
        try {
            $data = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException("Invalid flavor.json ($file): " . $e->getMessage(), 0, $e);
        }
        $fail = static fn(string $field) => throw new RuntimeException("Invalid flavor.json ($file): $field");

        if (!is_array($data)) {
            $fail('root must be an object');
        }
        foreach (['name', 'shortName'] as $field) {
            if (!is_string($data[$field] ?? null) || $data[$field] === '') {
                $fail($field);
            }
        }
        if (!in_array($data['timezone'] ?? null, timezone_identifiers_list(), true)) {
            $fail('timezone');
        }
        if (!in_array($data['usernameOrder'] ?? null, ['firstLast', 'lastFirst'], true)) {
            $fail('usernameOrder');
        }
        $features = $data['features'] ?? null;
        if (!is_array($features)) {
            $fail('features');
        }
        foreach (self::SWITCHES as $switch) {
            if (!is_bool($features[$switch] ?? null)) {
                $fail($switch);
            }
        }
        if (!in_array($features['waitlistOrder'] ?? null, ['timestamp', 'priority'], true)) {
            $fail('waitlistOrder');
        }
        $fields = $features['userFields'] ?? null;
        if (!is_array($fields) || array_diff($fields, self::USER_FIELDS) !== []) {
            $fail('userFields');
        }
        return $data;
    }
}
```

Note: `build.sh` (Task 15) writes `"id": "<flavour>"` into the shipped `config/flavor.json`, so `name()` also works in a dist.

- [ ] **Step 5: Pin the test flavour and reset per test**

In `phpunit.xml`, next to the other `<env>` entries: `<env name="APP_FLAVOR" value="elfaro" force="true"/>`.

In `IntegrationTestCase::setUp()`, after `$_COOKIE = [];`, add `\TP\Core\Flavor::reset();`. This keeps overrides from leaking between tests.

- [ ] **Step 6: Run, expect PASS**

Run: `composer test -- --filter FlavorTest`. Expected: 7 tests pass.

- [ ] **Step 7: Commit**

```bash
git add frontend/flavors src/Core/Flavor.php tests/Integration/Core/FlavorTest.php phpunit.xml tests/Integration/IntegrationTestCase.php
git commit -m "feat: Flavor configuration (elfaro, amigos) with validation"
```

### Task 2: Timezone from the flavour

**Files:** Modify `src/Core/Config.php:76`, `src/Core/Application.php:58`. Test: `tests/Integration/Core/ConfigTest.php`.

- [ ] **Step 1: Failing test** (append to `ConfigTest`)

```php
    public function testTimezoneComesFromTheFlavourUnlessConfigured(): void
    {
        unset($_ENV['APP_TIMEZONE']);
        \TP\Core\Flavor::reset();
        $this->assertSame('Europe/Zurich', \TP\Core\Application::timezone());

        $_ENV['APP_TIMEZONE'] = 'UTC';
        $this->assertSame('UTC', \TP\Core\Application::timezone());
        unset($_ENV['APP_TIMEZONE']);
    }
```

- [ ] **Step 2: Run, expect FAIL** (`Call to undefined method …::timezone()`): `composer test -- --filter ConfigTest`

- [ ] **Step 3: Implement**

In `Config.php`, change the default `'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Europe/Zurich',` to `'timezone' => $_ENV['APP_TIMEZONE'] ?? null,`. Check other readers first: `grep -rn "app.timezone" src tests` must only find `Application.php` (adapt any others to `Application::timezone()`).

In `Application.php`, replace `date_default_timezone_set($this->config->get('app.timezone', 'UTC'));` with `date_default_timezone_set(self::timezone());` and add:

```php
    /** APP_TIMEZONE wins (server override), otherwise the flavour's timezone. */
    public static function timezone(): string
    {
        $configured = $_ENV['APP_TIMEZONE'] ?? null;
        return is_string($configured) && $configured !== '' ? $configured : Flavor::timezone();
    }
```

- [ ] **Step 4: Run** `composer test -- --filter ConfigTest`. Expect PASS. Then run the full `composer test`; expect 425 + new tests passing.

- [ ] **Step 5: Commit** `feat: Timezone comes from the flavour`

### Task 3: Text overrides per flavour (PHP + i18n check)

**Files:** Modify `src/Core/Translator.php:139-152`, `frontend/scripts/check-i18n-keys.mts`. Test: `tests/Integration/Core/FlavorTranslationsTest.php`.

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use PHPUnit\Framework\TestCase;
use TP\Core\Translator;

class FlavorTranslationsTest extends TestCase
{
    /** @return list<string> dotted keys of string leaves */
    private static function keys(array $table, string $prefix = ''): array
    {
        $keys = [];
        foreach ($table as $k => $v) {
            $keys = is_array($v) ? [...$keys, ...self::keys($v, "$prefix$k.")] : [...$keys, "$prefix$k"];
        }
        return $keys;
    }

    public function testEveryOverrideKeyExistsInTheBaseTexts(): void
    {
        $root = __DIR__ . '/../../..';
        foreach (glob("$root/frontend/flavors/*/i18n/*.json") ?: [] as $file) {
            $base = json_decode((string) file_get_contents("$root/frontend/src/i18n/" . basename($file)), true);
            $override = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $unknown = array_diff(self::keys($override), self::keys($base));
            $this->assertSame([], array_values($unknown), "$file overrides keys that do not exist");
        }
    }

    public function testMergeReplacesOnlyOverriddenLeaves(): void
    {
        $merged = Translator::merge(
            ['app' => ['name' => 'A', 'welcome' => 'W'], 'x' => 'y'],
            ['app' => ['name' => 'B']],
        );
        $this->assertSame(['app' => ['name' => 'B', 'welcome' => 'W'], 'x' => 'y'], $merged);
    }
}
```

- [ ] **Step 2: Run, expect FAIL** (`Translator::merge` missing): `composer test -- --filter FlavorTranslationsTest`

- [ ] **Step 3: Implement** in `Translator.php`. Replace the body of `loadTranslations` after the early return:

```php
        $translations = self::readJson(self::langDir() . "/{$locale}.json");
        $flavorDir = Flavor::textDir();
        if ($flavorDir !== null) {
            $translations = self::merge($translations, self::readJson("$flavorDir/{$locale}.json"));
        }
        $this->translations[$locale] = $translations;
        $this->loadedLocales[] = $locale;
```

and add:

```php
    /** @return array<string, mixed> */
    private static function readJson(string $file): array
    {
        return file_exists($file)
            ? (array) json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)
            : [];
    }

    /** Deep merge: string leaves of $override replace those of $base (same rule as the frontend). */
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $base[$key] = is_array($value) && is_array($base[$key] ?? null) ? self::merge($base[$key], $value) : $value;
        }
        return $base;
    }
```

Also: `langDir()` must not pick the flavour subfolder when globbing locales. `glob(langDir().'/*.json')` only matches files, so `resources/i18n/flavor/` is safe.

- [ ] **Step 4: Extend `check-i18n-keys.mts`.** Append before the final `if (missing.length > 0)`:

```ts
// Flavour overrides may only replace existing keys
const flavorsDir = new URL('../flavors/', import.meta.url).pathname;
function leaves(node: Record<string, unknown>, prefix = ''): string[] {
  return Object.entries(node).flatMap(([k, v]) =>
    typeof v === 'object' && v !== null ? leaves(v as Record<string, unknown>, `${prefix}${k}.`) : [`${prefix}${k}`],
  );
}
for (const flavor of readdirSync(flavorsDir)) {
  for (const file of readdirSync(join(flavorsDir, flavor, 'i18n'))) {
    const base = JSON.parse(readFileSync(join(src, 'i18n', file), 'utf8')) as Record<string, unknown>;
    const override = JSON.parse(readFileSync(join(flavorsDir, flavor, 'i18n', file), 'utf8')) as Record<string, unknown>;
    const known = new Set(leaves(base));
    for (const key of leaves(override)) {
      if (!known.has(key)) {
        missing.push(`flavors/${flavor}/i18n/${file}: ${key} (not in base texts)`);
      }
    }
  }
}
```

- [ ] **Step 5: Run** `composer test -- --filter 'FlavorTranslationsTest|TranslatorTest|LocalizationTest'` and `cd frontend && npm run check:i18n`. Expect both exit 0.

- [ ] **Step 6: Commit** `feat: Flavour text overrides merged over the base texts`

### Task 4: `#[Feature]`: switched-off routes answer 404

**Files:** Create `src/Core/Attributes/Feature.php`. Modify `src/Core/RouteScanner.php`, `src/Core/Router.php`, the four controllers. Test: `tests/Integration/Core/FeatureRouteTest.php`.

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Core;

use TP\Core\Flavor;
use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class FeatureRouteTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    public function testGuestRoutesAre404WhenGuestsAreOff(): void
    {
        $eventId = DB::$events->add('Open', '2099-05-01', 10);
        $this->assertSame(200, $this->json('GET', "/api/events/{$eventId}/guest-info")->statusCode);

        Flavor::override(['guests' => false]);
        $response = $this->json('GET', "/api/events/{$eventId}/guest-info");
        $this->assertSame(404, $response->statusCode);
        $this->assertSame('errors.not_found', $response->json()['error']);

        $this->apiLoginAs('admin', 'Admin123!');
        $this->assertSame(404, $this->json('GET', "/api/events/{$eventId}/guests/1")->statusCode);
    }

    public function testSocialEventRoutesAre404WhenSocialEventsAreOff(): void
    {
        $this->apiLoginAs('admin', 'Admin123!');
        Flavor::override(['socialEvents' => false]);

        $this->assertSame(404, $this->json('GET', '/api/social-events/1')->statusCode);
        $this->assertSame(404, $this->json('POST', '/api/social-events', ['name' => 'x'])->statusCode);
    }

    public function testFeatureCheckRunsBeforeAuth(): void
    {
        Flavor::override(['socialEvents' => false]);
        // anonymous: would be 401 if the feature were on
        $this->assertSame(404, $this->json('GET', '/api/social-events/1')->statusCode);
    }
}
```

Check the exact name of the login helper in `IntegrationTestCase` (`apiLoginAs` is used by `UserAdminApiTest`). If `/api/events/{id}/guest-info` needs a different precondition for 200 (see `EventsController::guestInfo`), adapt the first assertion so it holds today.

- [ ] **Step 2: Run, expect FAIL** (200 instead of 404): `composer test -- --filter FeatureRouteTest`

- [ ] **Step 3: Implement the attribute** `src/Core/Attributes/Feature.php`

```php
<?php

declare(strict_types=1);

namespace TP\Core\Attributes;

use Attribute;

/** Route only exists when the flavour switch is on (see TP\Core\Flavor); otherwise 404. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Feature
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
```

- [ ] **Step 4: Scanner.** In `RouteScanner::scan()`, collect the class features next to `$classMiddleware`:

```php
        $classFeatures = array_map(fn($a) => $a->newInstance()->name, $reflection->getAttributes(Feature::class));
```

Pass them into `scanMethod(...)` as a new last parameter `array $classFeatures`. Inside it, build `$features = array_values(array_unique([...$classFeatures, ...array_map(fn($a) => $a->newInstance()->name, $method->getAttributes(Feature::class))]));` and add `'features' => $features,` to every route array. Add `use TP\Core\Attributes\Feature;` and update the `@return` shapes (`features: list<string>`).

- [ ] **Step 5: Router.** In `loadRoutes()`, add `'features' => $route['features'] ?? [],` to the stored route. In `addRoute()`, add `'features' => [],`. In `handle()`, directly after `if ($params === null) { continue; }`:

```php
            foreach ($route['features'] ?? [] as $feature) {
                if (!Flavor::enabled($feature)) {
                    return $request->isApi()
                        ? Response::apiError('errors.not_found', HttpStatus::NOT_FOUND)
                        : Response::notFound('Page not found');
                }
            }
```

The route cache stores the arrays through `var_export`, so `features` is cached automatically. Delete `storage/cache/routes.php` locally if it exists.

- [ ] **Step 6: Annotate controllers** (add `use TP\Core\Attributes\Feature;` in each):
- `SocialEventsController`, `SocialEventAdminController`: class-level `#[Feature('socialEvents')]`.
- `SocialEventsController::guestInfo` / `::registerGuest` (the methods at `#[Get('/{id}/guest-info')]` and `#[Post('/{id}/guests')]`): method-level `#[Feature('guests')]`.
- `EventsController`: the methods at `#[Get('/{id}/guest-info')]` and `#[Post('/{id}/guests')]`: `#[Feature('guests')]`.
- `EventAdminController`: the methods at `#[Get('/{id}/guests/{guestId}')]`, `#[Put('/{id}/guests/{guestId}')]`, `#[Delete('/{id}/guests/{guestId}')]`: `#[Feature('guests')]`.

- [ ] **Step 7: Run** `composer test -- --filter 'FeatureRouteTest|RouteCacheTest|RouterLoadRoutesTest|RouteScanner'`. Expect PASS. If `RouteCacheTest`/`RouterLoadRoutesTest` compare exact route arrays, add `'features' => []` to their expectations.

- [ ] **Step 8: Full suite** `composer test`. Expect all green. Then `composer api:check` (attributes don't change the DTOs; expect OK).

- [ ] **Step 9: Commit** `feat: #[Feature] attribute — routes of switched-off features answer 404`

---

# Phase 2: Data

### Task 5: Schema additions in `init.sql`

**Files:** Modify `database/init.sql` (after line 61). Test: `tests/Integration/Models/AmigosMigrationTest.php` (created here, extended in Task 6).

- [ ] **Step 1: Failing test.** It creates its own database `TP_test_migration` (user `TP` has CREATE/DROP on `*.*`), builds the **legacy Amigos schema** with made-up data, applies `init.sql` twice (idempotency), and checks the result.

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Models;

use mysqli;
use PHPUnit\Framework\TestCase;

/** init.sql + database/migrations/amigos-legacy.sql on a copy of the old Amigos schema (made-up data). */
class AmigosMigrationTest extends TestCase
{
    private const DB = 'TP_test_migration';
    private mysqli $conn;

    protected function setUp(): void
    {
        $this->conn = new mysqli(
            $_ENV['DB_HOST'] ?? 'localhost', $_ENV['DB_USERNAME'] ?? 'TP', $_ENV['DB_PASSWORD'] ?? '', '', (int) ($_ENV['DB_PORT'] ?? 3306)
        );
        $this->conn->query('DROP DATABASE IF EXISTS ' . self::DB);
        $this->conn->query('CREATE DATABASE ' . self::DB . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->conn->select_db(self::DB);
        $this->runSql((string) file_get_contents(__DIR__ . '/fixtures/amigos-legacy-schema.sql'));
    }

    protected function tearDown(): void
    {
        $this->conn->query('DROP DATABASE IF EXISTS ' . self::DB);
        $this->conn->close();
    }

    private function runSql(string $sql): void
    {
        $sql = preg_replace('/^\s*(CREATE\s+DATABASE|USE)\b[^;]*;/mi', '', $sql) ?? $sql;
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if (preg_replace('/^(--[^\n]*\n|\s)+/', '', $statement . "\n") === '') {
                continue; // comment-only chunk
            }
            $this->conn->query($statement);
        }
    }

    private function value(string $sql): mixed
    {
        return $this->conn->query($sql)->fetch_row()[0] ?? null;
    }

    private function initSql(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../../database/init.sql');
    }

    public function testInitSqlUpgradesTheLegacySchemaAndKeepsData(): void
    {
        $this->runSql($this->initSql());
        $this->runSql($this->initSql()); // idempotent

        $this->assertSame(4, (int) $this->value('SELECT COUNT(*) FROM users'));
        $this->assertSame(2, (int) $this->value('SELECT COUNT(*) FROM events'));
        $this->assertSame(3, (int) $this->value('SELECT COUNT(*) FROM event_users'));
        $this->assertSame('1200', (string) $this->value("SELECT prio FROM users WHERE username = 'Muster Max'"));
        $this->assertSame('12.4', (string) $this->value("SELECT handicap FROM users WHERE username = 'Muster Max'"));
        $this->assertSame('decimal(4,1)', (string) $this->value(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . self::DB . "' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'handicap'"
        ));
        $this->assertSame(1, (int) $this->value(
            "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '" . self::DB . "' AND TABLE_NAME = 'event_users' AND INDEX_NAME = 'unique_user_event' AND SEQ_IN_INDEX = 1"
        ));
        foreach (['event_guests', 'social_events', 'social_registrations'] as $table) {
            $this->assertSame(1, (int) $this->value(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . self::DB . "' AND TABLE_NAME = '$table'"
            ), $table);
        }
        $this->assertSame(1, (int) $this->value('SELECT MIN(mixed) FROM events'));
    }
}
```

Create the fixture `tests/Integration/Models/fixtures/amigos-legacy-schema.sql`. It holds the exact legacy table definitions from `/home/hempe/git/amigos/init.sql` (structure only, **no data from the export**) plus **made-up** rows:

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    admin TINYINT(1) NOT NULL,
    phone VARCHAR(255) DEFAULT NULL,
    handicap DECIMAL(3,1) DEFAULT NULL,
    prio INT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    capacity INT NOT NULL,
    timestamp DATETIME NOT NULL,
    locked TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE event_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userId INT NOT NULL,
    eventId INT NOT NULL,
    comment VARCHAR(2048) DEFAULT NULL,
    state INT NOT NULL,
    timestamp DATETIME DEFAULT NULL,
    KEY FK_EVENT_USERS (userId),
    KEY FK_USER_EVENTS (eventId),
    CONSTRAINT FK_EVENT_USERS FOREIGN KEY (userId) REFERENCES users (id) ON DELETE CASCADE ON UPDATE NO ACTION,
    CONSTRAINT FK_USER_EVENTS FOREIGN KEY (eventId) REFERENCES events (id) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userId INT NOT NULL,
    state INT NOT NULL,
    name VARCHAR(255) NOT NULL UNIQUE,
    handicap DECIMAL(3,1) DEFAULT NULL,
    prio INT DEFAULT NULL,
    CONSTRAINT FK_USERS FOREIGN KEY (userId) REFERENCES users (id) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE event_registration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    playerId INT NOT NULL,
    eventId INT NOT NULL,
    comment VARCHAR(2048) DEFAULT NULL,
    state INT NOT NULL,
    TIMESTAMP DATETIME DEFAULT NULL,
    CONSTRAINT FK_EVENTS FOREIGN KEY (eventId) REFERENCES events (id) ON DELETE CASCADE ON UPDATE NO ACTION,
    CONSTRAINT FK_PLAYERS FOREIGN KEY (playerId) REFERENCES players (id) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- made-up data: two-word, one-word and three-word usernames, one user without priority
INSERT INTO users (id, username, password, admin, phone, handicap, prio) VALUES
 (1, 'Muster Max', '$2y$10$abcdefghijklmnopqrstuuJ8rS3vS7m3K8eR3mOq6QWm5Hk3xY2W', 0, NULL, 12.4, 1200),
 (2, 'Arno', '$2y$10$abcdefghijklmnopqrstuuJ8rS3vS7m3K8eR3mOq6QWm5Hk3xY2W', 1, '+34 600 000 000', NULL, 1000),
 (3, 'De la Fuente Juan', '$2y$10$abcdefghijklmnopqrstuuJ8rS3vS7m3K8eR3mOq6QWm5Hk3xY2W', 0, NULL, 30.0, 1500),
 (4, 'Beispiel Bea', '$2y$10$abcdefghijklmnopqrstuuJ8rS3vS7m3K8eR3mOq6QWm5Hk3xY2W', 0, NULL, NULL, NULL);
INSERT INTO events (id, name, date, capacity, timestamp, locked) VALUES
 (1, 'Turnier A', '2099-03-01', 2, '2099-01-01 10:00:00', 0),
 (2, 'Turnier B', '2099-03-08', 10, '2099-01-01 10:00:00', 1);
INSERT INTO event_users (userId, eventId, comment, state, timestamp) VALUES
 (1, 1, '', 1, '2099-01-02 10:00:00'), (3, 1, 'spät', 1, '2099-01-02 11:00:00'), (4, 2, NULL, 1, '2099-01-03 10:00:00');
INSERT INTO players (id, userId, state, name, handicap, prio) VALUES (1, 1, 1, 'Muster Max', 12.4, 1200);
INSERT INTO event_registration (playerId, eventId, comment, state, TIMESTAMP) VALUES (1, 1, '', 1, '2025-02-11 19:05:17');
```

- [ ] **Step 2: Run, expect FAIL** (handicap still `decimal(3,1)`, no `unique_user_event`): `composer test -- --filter AmigosMigrationTest`

- [ ] **Step 3: Extend `database/init.sql`.** After the line `ALTER TABLE events ADD COLUMN IF NOT EXISTS registration_close DATETIME NULL;` add:

```sql
-- Amigos flavour fields (unused by El Faro): phone and handicap admin-only, prio = waitlist priority (lower first)
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS handicap DECIMAL(4,1) NULL;
ALTER TABLE users MODIFY COLUMN handicap DECIMAL(4,1) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS prio INT NULL;
-- Older databases (the Amigos app) lack the key the registration upsert relies on
ALTER TABLE event_users ADD UNIQUE KEY IF NOT EXISTS unique_user_event (userId, eventId);
```

- [ ] **Step 4: Run** `composer test -- --filter AmigosMigrationTest`. Expect PASS. Then the full `composer test` (the test DB is built from `init.sql`).

- [ ] **Step 5: Commit** `feat(db): phone, handicap, prio on users; unique registration key for old databases`

### Task 6: `database/migrations/amigos-legacy.sql`

**Files:** Create `database/migrations/amigos-legacy.sql`. Modify `tests/Integration/Models/AmigosMigrationTest.php`, `database/README.md`.

- [ ] **Step 1: Failing test** (add to `AmigosMigrationTest`)

```php
    private function legacySql(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../../database/migrations/amigos-legacy.sql');
    }

    public function testLegacyScriptSplitsNamesDropsOldTablesAndIsRerunnable(): void
    {
        $this->runSql($this->initSql());
        $this->runSql($this->legacySql());
        $this->conn->query("UPDATE users SET first_name = 'Juan', last_name = 'De la Fuente' WHERE id = 3"); // admin fix
        $this->runSql($this->legacySql()); // must not overwrite the fix

        $names = [];
        foreach ($this->conn->query('SELECT username, first_name, last_name FROM users ORDER BY id')->fetch_all() as [$u, $f, $l]) {
            $names[$u] = [$f, $l];
        }
        $this->assertSame([
            'Muster Max' => ['Max', 'Muster'],
            'Arno' => ['', 'Arno'],
            'De la Fuente Juan' => ['Juan', 'De la Fuente'],
            'Beispiel Bea' => ['Bea', 'Beispiel'],
        ], $names);

        foreach (['players', 'event_registration'] as $table) {
            $this->assertSame(0, (int) $this->value(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . self::DB . "' AND TABLE_NAME = '$table'"
            ), $table);
        }
        $this->assertSame(3, (int) $this->value('SELECT COUNT(*) FROM event_users'));
    }

    public function testCheckListShowsOneAndThreePlusWordUsernames(): void
    {
        $this->runSql($this->initSql());
        $sql = $this->legacySql();
        $this->runSql($sql);
        // the script's last statement is the check list
        $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));
        $rows = $this->conn->query(end($statements))->fetch_all(MYSQLI_ASSOC);

        $this->assertSame(['Arno', 'De la Fuente Juan'], array_column($rows, 'username'));
    }
```

- [ ] **Step 2: Run, expect FAIL** (file missing): `composer test -- --filter AmigosMigrationTest`

- [ ] **Step 3: Write the script** `database/migrations/amigos-legacy.sql`

```sql
-- One-off migration of the old Amigos de Tauro database (run once, AFTER database/init.sql).
-- Back up the database first (phpMyAdmin: Export). Safe to run again.
-- 1. Names: Amigos logins are "Lastname Firstname" - first word = last name, rest = first name.
--    Only users without names are touched, so fixes made in the app are kept.
UPDATE users
SET last_name = SUBSTRING_INDEX(TRIM(username), ' ', 1),
    first_name = TRIM(SUBSTRING(TRIM(username), CHAR_LENGTH(SUBSTRING_INDEX(TRIM(username), ' ', 1)) + 1))
WHERE (first_name IS NULL OR first_name = '') AND (last_name IS NULL OR last_name = '');

-- 2. Tables of the abandoned "players" model (last written 2025-02-11)
DROP TABLE IF EXISTS event_registration;
DROP TABLE IF EXISTS players;

-- 3. Check list: one-word and multi-part names need a look (fix them under Benutzer in the app)
SELECT id, username, first_name, last_name
FROM users
WHERE TRIM(username) NOT LIKE '% %'
   OR CHAR_LENGTH(TRIM(username)) - CHAR_LENGTH(REPLACE(TRIM(username), ' ', '')) >= 2
ORDER BY username
```

The last statement intentionally has no `;`, so phpMyAdmin shows its result and the test can pick it as the last statement. The test's `explode(';')` must not hit `;` inside comments: keep comments free of semicolons.

One-word usernames: `SUBSTRING_INDEX('Arno',' ',1)` = `'Arno'` and the rest is `''`, which gives the expected `['', 'Arno']`.

- [ ] **Step 4: Run** `composer test -- --filter AmigosMigrationTest`. Expect PASS.

- [ ] **Step 5: Document** in `database/README.md`: a section "Amigos (one-off)" that explains the order `init.sql` → `migrations/amigos-legacy.sql` and points to `docs/DEPLOYMENT.md` (Task 22).

- [ ] **Step 6: Commit** `feat(db): One-off Amigos migration (name split, drop legacy tables, check list)`

### Task 7: `bin/amigos-dry-run.sh`

**Files:** Create `bin/amigos-dry-run.sh` (executable).

- [ ] **Step 1: Write the script**

```bash
#!/bin/bash
# Dry run of the Amigos migration against a phpMyAdmin export, in a throwaway MariaDB container.
#   bin/amigos-dry-run.sh /path/to/amigoDb-export.sql
# Prints only counts and the name check list - never other rows. The export is not copied anywhere.
set -euo pipefail
EXPORT="${1:?usage: bin/amigos-dry-run.sh <export.sql>}"
[ -f "$EXPORT" ] || { echo "Not a file: $EXPORT" >&2; exit 2; }
cd "$(dirname "$0")/.."
NAME="tp-amigos-dry-run-$$"
SQL=(docker exec -i "$NAME" mariadb -uroot -pdryrun amigoDb)

cleanup() { docker rm -f "$NAME" >/dev/null 2>&1 || true; }
trap cleanup EXIT

docker run -d --rm --name "$NAME" -e MARIADB_ROOT_PASSWORD=dryrun -e MARIADB_DATABASE=amigoDb mariadb:11 >/dev/null
for _ in $(seq 60); do
    docker exec "$NAME" mariadb -uroot -pdryrun -e 'SELECT 1' >/dev/null 2>&1 && break
    sleep 1
done

counts() {
    "${SQL[@]}" -t -e "SELECT (SELECT COUNT(*) FROM users) users, (SELECT COUNT(*) FROM events) events,
        (SELECT COUNT(*) FROM event_users) registrations, (SELECT COUNT(*) FROM event_users WHERE state = 2) waitlist"
}

"${SQL[@]}" < "$EXPORT"
echo "Before:"; counts
BEFORE="$(counts)"

# init.sql without CREATE DATABASE / USE (we stay in amigoDb)
sed -E '/^\s*(CREATE DATABASE|USE)\b/d' database/init.sql | "${SQL[@]}"
echo "Name check list (fix these in the app after the cut-over):"
"${SQL[@]}" -t < database/migrations/amigos-legacy.sql

echo "After:"; counts
AFTER="$(counts)"
[ "$BEFORE" = "$AFTER" ] || { echo "COUNTS CHANGED - do not migrate" >&2; exit 1; }
"${SQL[@]}" -t -e "SELECT COUNT(*) users_without_last_name FROM users WHERE last_name IS NULL OR last_name = ''"
echo "Dry run OK."
```

- [ ] **Step 2: Run it against the real export** (the output contains only counts plus the 9-name check list, which is shown to the **user**). **Do not paste the check list rows into commits or docs.**

Run: `chmod +x bin/amigos-dry-run.sh && bin/amigos-dry-run.sh /home/hempe/git/amigos/filiusag_amigoDb.sql; echo "exit $?"`
Expected: before = after = `111 | 64 | 636 | 0`, `users_without_last_name = 0`, `Dry run OK.`, exit 0. When reporting to the user, mention only the counts and how many names are on the check list (expected 9).

- [ ] **Step 3: Commit** `feat: bin/amigos-dry-run.sh — rehearse the Amigos migration on an export`

---

# Phase 3: Amigos behaviour (backend)

### Task 8: User fields phone, handicap, priority (and gating of rfeg/memberNumber)

**Files:** Modify `src/Models/User.php`, `src/Models/UserRepository.php`, `src/Api/Dto/{AdminUserDto,UserCreateRequest,UserUpdateRequest}.php`, `src/Controllers/Api/UserAdminController.php`. Test: `tests/Integration/Api/UserFieldsApiTest.php`.

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Core\Flavor;
use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class UserFieldsApiTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
        $this->apiLoginAs('admin', 'Admin123!');
    }

    private function body(array $override = []): array
    {
        return $override + [
            'male' => true, 'username' => 'Muster Max', 'password' => 'Secret123!', 'firstName' => 'Max', 'lastName' => 'Muster',
            'rfeg' => 'R1', 'memberNumber' => 'M1', 'phone' => '+34 600', 'handicap' => 12.4, 'priority' => 1200,
        ];
    }

    public function testAmigosStoresPhoneHandicapPriorityAndIgnoresRfeg(): void
    {
        Flavor::override(['userFields' => ['phone', 'handicap', 'priority']]);

        $data = $this->json('POST', '/api/users', $this->body())->json();

        $this->assertSame('+34 600', $data['phone']);
        $this->assertSame(12.4, $data['handicap']);
        $this->assertSame(1200, $data['priority']);
        $this->assertNull($data['rfeg']);
        $this->assertNull($data['memberNumber']);
    }

    public function testElFaroIgnoresAndHidesAmigosFields(): void
    {
        $data = $this->json('POST', '/api/users', $this->body())->json();

        $this->assertSame('R1', $data['rfeg']);
        $this->assertNull($data['phone']);
        $this->assertNull($data['handicap']);
        $this->assertNull($data['priority']);
    }

    public function testUpdateKeepsHiddenFieldsUntouched(): void
    {
        $id = DB::$users->create('jane', 'Pass123!', true, 'R1', 'M1', 'Jane', 'Doe');
        Flavor::override(['userFields' => ['phone', 'handicap', 'priority']]);

        $this->json('PUT', "/api/users/{$id}", $this->body(['username' => 'jane', 'password' => '', 'rfeg' => null, 'memberNumber' => null]));

        Flavor::reset();
        $user = DB::$users->get($id);
        $this->assertSame('R1', $user->rfeg, 'El Faro field must survive an Amigos-style save');
        $this->assertSame(1200, $user->priority);
    }

    public function testValidation(): void
    {
        Flavor::override(['userFields' => ['phone', 'handicap', 'priority']]);

        $response = $this->json('POST', '/api/users', $this->body(['handicap' => 99.9, 'priority' => -1]));

        $this->assertSame(422, $response->statusCode);
        $this->assertArrayHasKey('handicap', $response->json()['fields']);
        $this->assertArrayHasKey('priority', $response->json()['fields']);
    }

    public function testGenderIsOptionalWhenGenderSplitIsOff(): void
    {
        Flavor::override(['genderSplit' => false]);
        $body = $this->body();
        unset($body['male']);

        $this->assertSame(200, $this->json('POST', '/api/users', $body)->statusCode);
    }
}
```

- [ ] **Step 2: Run, expect FAIL**: `composer test -- --filter UserFieldsApiTest`

- [ ] **Step 3: Model.** In `User.php`, add constructor params after `$lastName`:

```php
        public readonly ?string $phone = null,
        public readonly ?float $handicap = null,
        public readonly ?int $priority = null,
```

- [ ] **Step 4: Repository.** In `UserRepository`:
- Add a private constant `COLUMNS = 'id, username, admin, male, rfeg, member_number, first_name, last_name, phone, handicap, prio'` and a mapper:

```php
    private static function map(array $row): User
    {
        return new User(
            (int) $row['id'], $row['username'], (bool) $row['admin'], (bool) $row['male'], $row['rfeg'], $row['member_number'],
            $row['first_name'] ?? null, $row['last_name'] ?? null, $row['phone'] ?? null,
            $row['handicap'] === null ? null : (float) $row['handicap'], $row['prio'] === null ? null : (int) $row['prio'],
        );
    }
```

- Use `COLUMNS`/`map()` in `get()`, `all()` and `getWithPassword()`. For `getWithPassword`, select `self::COLUMNS . ', password'`.
- Add:

```php
    /** Flavour-specific fields; only the ones listed in $fields are written. */
    public function updateExtra(int $id, array $fields): void
    {
        $columns = ['rfeg' => 'rfeg', 'memberNumber' => 'member_number', 'phone' => 'phone', 'handicap' => 'handicap', 'priority' => 'prio'];
        foreach (array_intersect_key($fields, $columns) as $key => $value) {
            $stmt = $this->conn->prepare("UPDATE users SET {$columns[$key]} = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare statement failed: " . $this->conn->error);
            }
            $stmt->bind_param('si', $value, $id);
            $stmt->execute();
            $stmt->close();
        }
    }
```

(`'s'` binding works for DECIMAL/INT/NULL in MariaDB. `null` stays NULL.)

- Change `update()` to no longer write `rfeg`/`member_number` (new signature `update(int $id, bool $male, string $username, ?string $firstName, ?string $lastName)`). `create()` keeps its signature (rfeg/memberNumber stay optional args). Fix all callers: `grep -rn 'users->update(' src tests`.

- [ ] **Step 5: DTOs.**
- `AdminUserDto`: add `public ?string $phone, public ?float $handicap, public ?int $priority,` after `memberNumber`. `fromUser` fills each field only when `Flavor::hasUserField(...)` is true, else `null`. Apply the same to `rfeg`/`memberNumber`:

```php
            Flavor::hasUserField('rfeg') ? $u->rfeg : null,
            Flavor::hasUserField('memberNumber') ? $u->memberNumber : null,
            Flavor::hasUserField('phone') ? $u->phone : null,
            Flavor::hasUserField('handicap') ? $u->handicap : null,
            Flavor::hasUserField('priority') ? $u->priority : null,
```

- `UserCreateRequest`/`UserUpdateRequest`: make `male` `?bool`, add `public ?string $phone, public ?float $handicap, public ?int $priority`.

- [ ] **Step 6: Controller.** In `UserAdminController`:
- `validate()`: require `male` only when `Flavor::enabled('genderSplit')`. Add rules when the field is enabled:

```php
        if (Flavor::hasUserField('handicap') && $request->getString('handicap') !== '') {
            $rules[] = new ValidationRule('handicap', ['numeric', 'min' => -10, 'max' => 54]);
        }
        if (Flavor::hasUserField('priority') && $request->getString('priority') !== '') {
            $rules[] = new ValidationRule('priority', ['integer', 'min' => 0, 'max' => 99999]);
        }
        if (Flavor::hasUserField('phone')) {
            $rules[] = new ValidationRule('phone', ['string', 'max' => 255]);
        }
```

Check `src/Core/Validator.php` for the exact rule names (`numeric`, `integer`, `min`, `max`) and adapt. Then add:

```php
    /** @return array<string, string|null> flavour fields from the request, only the enabled ones */
    private function extraFields(Request $request): array
    {
        $extra = [];
        foreach (Flavor::USER_FIELDS as $field) {
            if (Flavor::hasUserField($field)) {
                $extra[$field] = $this->nullable($request, $field);
            }
        }
        return $extra;
    }
```

- `create()`: `DB::$users->create($username, $password, Flavor::enabled('genderSplit') ? $request->getBool('male') : true, null, null, firstName, lastName)` followed by `DB::$users->updateExtra($id, $this->extraFields($request));`.
- `update()`: `DB::$users->update($user->id, Flavor::enabled('genderSplit') ? $request->getBool('male') : $user->male, $username, firstName, lastName)` plus `updateExtra(...)`.

- [ ] **Step 7: Regenerate the API types**: `composer api:generate && composer api:check`. The frontend compile will break on `AdminUserDto` literals in specs. Fix them in Task 18; for now run `cd frontend && npx tsc -p tsconfig.spec.json --noEmit` to list them, and add `phone: null, handicap: null, priority: null` to each fixture so `npm test` stays green.

- [ ] **Step 8: Run** `composer test -- --filter 'UserFieldsApiTest|UserAdminApiTest|MemberDtoTest|ParityApiTest'`, then `composer test`, then `cd frontend && npm test`. Expect all green.

- [ ] **Step 9: Commit** `feat: Flavour user fields (phone, handicap, priority; rfeg/member number per flavour)`

### Task 9: Priority waitlist

**Files:** Modify `src/Models/EventRepository.php` (`fix()` around line 283, `registrations()` line 149). Test: `tests/Integration/Models/PriorityWaitlistTest.php`.

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Models;

use TP\Core\Flavor;
use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class PriorityWaitlistTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    private function user(string $name, ?int $priority): int
    {
        $id = DB::$users->create($name, 'Pass123!', true, null, null, $name, $name);
        DB::$users->updateExtra($id, ['priority' => $priority === null ? null : (string) $priority]);
        return $id;
    }

    /** Registers in the given order with distinct timestamps; returns the event id. */
    private function eventWith(int $capacity, array $userIds): int
    {
        $eventId = DB::$events->add('Prio', '2099-05-01', $capacity);
        foreach ($userIds as $i => $userId) {
            DB::$events->register($eventId, $userId, '');
            DB::$events->setRegistrationTimestamp($eventId, $userId, sprintf('2099-01-01 10:00:%02d', $i));
        }
        return $eventId;
    }

    private function states(int $eventId): array
    {
        $out = [];
        foreach (DB::$events->registrations($eventId) as $r) {
            $out[] = [$r->name, $r->state];
        }
        return $out;
    }

    public function testPromotionByPriorityThenTimeWithNullLast(): void
    {
        Flavor::override(['waitlistOrder' => 'priority']);
        $first = $this->user('First', 5000);
        $none = $this->user('None', null);
        $late = $this->user('Late', 2000);
        $vip = $this->user('Vip', 1000);
        $eventId = $this->eventWith(1, [$first, $none, $late, $vip]);

        // waitlist shown in promotion order: Vip (1000), Late (2000), None (no priority)
        $this->assertSame([['First First', 1], ['Vip Vip', 2], ['Late Late', 2], ['None None', 2]], $this->states($eventId));

        DB::$events->unregister($eventId, $first);
        $this->assertSame(['Vip Vip', 1], $this->states($eventId)[0]);
    }

    public function testCapacityReductionDemotesLowestPriorityFirst(): void
    {
        Flavor::override(['waitlistOrder' => 'priority']);
        $a = $this->user('Aaa', 1000);
        $b = $this->user('Bbb', 3000);
        $c = $this->user('Ccc', 2000);
        $eventId = $this->eventWith(3, [$a, $b, $c]);

        DB::$events->update($eventId, 'Prio', 2);

        $waiting = array_values(array_filter($this->states($eventId), fn($s) => $s[1] === 2));
        $this->assertSame([['Bbb Bbb', 2]], $waiting);
    }

    public function testElFaroStaysFirstComeFirstServed(): void
    {
        $vip = $this->user('Vip', 1000);
        $first = $this->user('First', 5000);
        $second = $this->user('Second', 9000);
        $eventId = $this->eventWith(1, [$first, $second, $vip]);

        DB::$events->unregister($eventId, $first);

        $this->assertSame(['Second Second', 1], $this->states($eventId)[0]);
    }
}
```

`EventRegistration` has public `name`/`state` (check `src/Models/EventRegistration.php`; adapt the property names if they differ).

- [ ] **Step 2: Add the test helper.** Timestamps from `NOW()` collide within a second, so add to `EventRepository`:

```php
    /** Tests and data fixes: set the registration time used for waitlist ordering. */
    public function setRegistrationTimestamp(int $eventId, int $userId, string $timestamp): void
    {
        $this->executeUpdateQuery(
            "UPDATE event_users SET timestamp = ? WHERE eventId = ? AND userId = ?",
            "sii",
            [$timestamp, $eventId, $userId]
        );
        $this->fix($eventId);
    }
```

Note: in `eventWith()`, every `register()` already runs `fix()` with `NOW()` timestamps. The later `setRegistrationTimestamp(...)` + `fix()` don't re-sort people who are already joined. That is correct behaviour: whoever is joined stays joined. The tests are built so that only the waitlist order matters.

- [ ] **Step 3: Run, expect FAIL**: `composer test -- --filter PriorityWaitlistTest`

- [ ] **Step 4: Implement.** In `EventRepository`:

```php
    /** Waitlist promotion order (first = promoted first). */
    private static function promotionOrder(string $registration = 'eu_inner', string $user = 'u'): string
    {
        return Flavor::waitlistByPriority()
            ? "{$user}.prio IS NULL, {$user}.prio ASC, {$registration}.timestamp ASC"
            : "{$registration}.timestamp ASC";
    }

    /** Demotion order when capacity shrinks (first = demoted first): the reverse. */
    private static function demotionOrder(): string
    {
        return Flavor::waitlistByPriority()
            ? "u.prio IS NULL DESC, u.prio DESC, eu_inner.timestamp DESC"
            : "eu_inner.timestamp DESC";
    }
```

In `fix()`, replace `ORDER BY eu_inner.timestamp ASC` with `ORDER BY " . self::promotionOrder() . "` and `ORDER BY eu_inner.timestamp DESC` with `ORDER BY " . self::demotionOrder() . "` (the query strings become concatenations).

In `registrations()`, replace `ORDER BY r.state, u.male DESC, u.last_name, u.first_name` with:

```php
            ORDER BY r.state, " . (Flavor::waitlistByPriority()
                ? "IF(r.state = 2, u.prio IS NULL, 0), IF(r.state = 2, u.prio, 0), IF(r.state = 2, r.timestamp, 0), "
                : "") . "u.male DESC, u.last_name, u.first_name";
```

Add `use TP\Core\Flavor;`.

- [ ] **Step 5: Run** `composer test -- --filter 'PriorityWaitlistTest|EventRepositoryTest|EventsApiTest|EventAdminApiTest'`. Expect PASS.

- [ ] **Step 6: Commit** `feat: Priority waitlist (Amigos) — promotion by priority, then registration time`

### Task 10: Handicap in the participant list

**Files:** Modify `src/Models/EventRegistration.php`, `src/Models/EventRepository.php::registrations()`, `src/Api/Dto/ParticipantDto.php`. Test: add to `tests/Integration/Api/EventsApiTest.php`.

- [ ] **Step 1: Failing test** (in `EventsApiTest`; reuse its existing login helpers)

```php
    public function testParticipantHandicapOnlyWhenTheFlavourHasIt(): void
    {
        $userId = DB::$users->create('hcp', 'Pass123!', true, null, null, 'Hans', 'Hcp');
        DB::$users->updateExtra($userId, ['handicap' => '12.4']);
        $eventId = DB::$events->add('Hcp', '2099-05-01', 10);
        DB::$events->register($eventId, $userId, '');
        $this->apiLoginAs('hcp', 'Pass123!');

        $this->assertNull($this->json('GET', "/api/events/{$eventId}")->json()['participants'][0]['handicap']);

        \TP\Core\Flavor::override(['userFields' => ['handicap']]);
        $this->assertSame(12.4, $this->json('GET', "/api/events/{$eventId}")->json()['participants'][0]['handicap']);
    }
```

- [ ] **Step 2: Run, expect FAIL**: `composer test -- --filter testParticipantHandicap`

- [ ] **Step 3: Implement.** Add `public readonly ?float $handicap = null` as the last constructor param of `EventRegistration`. In `registrations()`, select `u.handicap` and pass `$row['handicap'] === null ? null : (float) $row['handicap']`. In `ParticipantDto`, add `public ?float $handicap` and in `fromRegistration` use `Flavor::hasUserField('handicap') ? $r->handicap : null`.

- [ ] **Step 4: Regenerate**: `composer api:generate && composer api:check`. Fix the `ParticipantDto` literals in frontend specs (`handicap: null`).

- [ ] **Step 5: Run** `composer test -- --filter 'EventsApiTest|ParityApiTest'` and `cd frontend && npm test`. Expect PASS.

- [ ] **Step 6: Commit** `feat: Handicap in participant lists when the flavour has it`

### Task 11: Login by first and last name (both flavours)

**Files:** Modify `src/Models/UserRepository.php`, `src/Models/User.php::authenticate`. Test: `tests/Integration/Api/NameLoginApiTest.php`.

- [ ] **Step 1: Failing test**

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class NameLoginApiTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
        DB::$users->create('mmuster', 'Pass123!', true, null, null, 'Max', 'Muster');
    }

    private function login(string $name): int
    {
        $_SESSION = [];
        return $this->json('POST', '/api/auth/login', ['username' => $name, 'password' => 'Pass123!'])->statusCode;
    }

    public function testUsernameStillWorks(): void
    {
        $this->assertSame(200, $this->login('mmuster'));
    }

    public function testFirstLastAndLastFirstCaseAndSpaceInsensitive(): void
    {
        $this->assertSame(200, $this->login('Max Muster'));
        $this->assertSame(200, $this->login('muster   max'));
        $this->assertSame(200, $this->login('  MAX MUSTER '));
    }

    public function testAmbiguousNameNeverLogsIn(): void
    {
        DB::$users->create('max2', 'Pass123!', true, null, null, 'Max', 'Muster');
        $this->assertSame(422, $this->login('Max Muster'));
    }

    public function testExactUsernameWinsOverANameMatch(): void
    {
        // another user's login IS "Max Muster": exact username match is tried first
        DB::$users->create('Max Muster', 'Other123!', true, null, null, 'Someone', 'Else');
        $this->assertSame(422, $this->login('Max Muster')); // password belongs to mmuster, not to the exact match
    }

    public function testWrongPasswordFails(): void
    {
        $_SESSION = [];
        $this->assertSame(422, $this->json('POST', '/api/auth/login', ['username' => 'Max Muster', 'password' => 'nope'])->statusCode);
    }
}
```

- [ ] **Step 2: Run, expect FAIL**: `composer test -- --filter NameLoginApiTest`

- [ ] **Step 3: Repository**

```php
    /**
     * Users whose "first last" or "last first" equals $name (case-insensitive, whitespace-normalised).
     *
     * @return list<array{0: User, 1: string}> [user, password hash]
     */
    public function findByName(string $name): array
    {
        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
        if ($normalized === '' || !str_contains($normalized, ' ')) {
            return [];
        }
        $stmt = $this->conn->prepare(
            "SELECT " . self::COLUMNS . ", password FROM users
             WHERE first_name <> '' AND last_name <> ''
               AND (LOWER(CONCAT(TRIM(first_name), ' ', TRIM(last_name))) = ?
                 OR LOWER(CONCAT(TRIM(last_name), ' ', TRIM(first_name))) = ?)"
        );
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param('ss', $normalized, $normalized);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(fn(array $row) => [self::map($row), $row['password']], $rows);
    }
```

Names with inner double spaces in the DB won't match. That's acceptable; the admin form trims input.

- [ ] **Step 4: `User::authenticate`.** Replace the lookup:

```php
        [$user, $hashedPassword] = $userRepo->getWithPassword($username);
        if ($user === null) {
            $matches = $userRepo->findByName($username);
            if (count($matches) === 1) {
                [$user, $hashedPassword] = $matches[0];
            }
        }
```

The rate-limit key in `AuthController` already uses the typed name, so nothing changes there.

- [ ] **Step 5: Login label.** Change the texts of `auth.username` in `frontend/src/i18n/{de,en,es}.json` to "Benutzername oder Vor- und Nachname" / "Username or first and last name" / "Usuario o nombre y apellido". Keep the key, so no code change is needed.

- [ ] **Step 6: Run** `composer test -- --filter 'NameLoginApiTest|AuthApiTest'`, then `cd frontend && npm test && npm run check:i18n`. Expect PASS. Login spec texts come from fixtures, so they're unaffected.

- [ ] **Step 7: Commit** `feat: Log in with first and last name (both flavours, ambiguous names never log in)`

### Task 12: `nameConflict` flag for the user admin

**Files:** Modify `src/Models/UserRepository.php`, `src/Api/Dto/AdminUserDto.php`, `src/Controllers/Api/UserAdminController.php`. Test: add to `UserAdminApiTest`.

- [ ] **Step 1: Failing test**

```php
    public function testNameConflictFlag(): void
    {
        $this->admin();
        $a = DB::$users->create('max1', 'Pass123!', true, null, null, 'Max', 'Muster');
        $b = DB::$users->create('max2', 'Pass123!', true, null, null, 'Max', 'Muster');
        $c = DB::$users->create('Bea Beispiel', 'Pass123!', true, null, null, 'X', 'Y');
        $d = DB::$users->create('bea', 'Pass123!', true, null, null, 'Bea', 'Beispiel');
        $e = DB::$users->create('solo', 'Pass123!', true, null, null, 'Solo', 'Single');

        $flags = array_column($this->json('GET', '/api/users')->json(), 'nameConflict', 'id');

        $this->assertTrue($flags[$a]);
        $this->assertTrue($flags[$b]);
        $this->assertTrue($flags[$d], 'name equals another user\'s login');
        $this->assertFalse($flags[$e]);
        $this->assertTrue($this->json('GET', "/api/users/{$d}")->json()['nameConflict']);
    }
```

- [ ] **Step 2: Run, expect FAIL**: `composer test -- --filter testNameConflictFlag`

- [ ] **Step 3: Repository**

```php
    /**
     * Ids of users whose name ("first last" / "last first") would make a name login ambiguous:
     * another user has the same name pair, or another user's username equals it.
     *
     * @return array<int, true>
     */
    public function nameConflicts(): array
    {
        $result = $this->conn->query(
            "SELECT DISTINCT a.id FROM users a JOIN users b ON a.id <> b.id
             WHERE a.first_name <> '' AND a.last_name <> '' AND (
                (LOWER(TRIM(a.first_name)) = LOWER(TRIM(b.first_name)) AND LOWER(TRIM(a.last_name)) = LOWER(TRIM(b.last_name)))
                OR LOWER(TRIM(b.username)) IN (
                    LOWER(CONCAT(TRIM(a.first_name), ' ', TRIM(a.last_name))),
                    LOWER(CONCAT(TRIM(a.last_name), ' ', TRIM(a.first_name)))))"
        );
        $ids = [];
        foreach ($result ? $result->fetch_all() : [] as [$id]) {
            $ids[(int) $id] = true;
        }
        return $ids;
    }
```

- [ ] **Step 4: DTO and controller.** Add `public bool $nameConflict` as the last `AdminUserDto` field. Change `fromUser(User $u, bool $nameConflict = false)`. In `UserAdminController`, add a helper `private function dto(User $u): AdminUserDto { return AdminUserDto::fromUser($u, isset(DB::$users->nameConflicts()[$u->id])); }`. In `index()`, compute `$conflicts = DB::$users->nameConflicts()` once and map with `fn(User $u) => AdminUserDto::fromUser($u, isset($conflicts[$u->id]))`. Replace every other `AdminUserDto::fromUser(...)` call with `$this->dto(...)`.

- [ ] **Step 5: Regenerate** `composer api:generate && composer api:check`. Add `nameConflict: false` to the frontend fixtures.

- [ ] **Step 6: Run** `composer test -- --filter UserAdminApiTest` and `cd frontend && npm test`. Expect PASS.

- [ ] **Step 7: Commit** `feat: Flag users whose name would make a name login ambiguous`

---

# Phase 4: Frontend

### Task 13: Flavour token, `active-flavor` swap point, text merge

**Files:** Create `frontend/src/app/core/flavor/{flavor.ts,active-flavor.ts,active-flavor.amigos.ts,flavor.spec.ts}`. Modify `frontend/src/app/core/i18n/i18n.ts` (`loadLocale`), `frontend/src/app/core/i18n/i18n.spec.ts`.

- [ ] **Step 1: Failing spec** `flavor.spec.ts`

```ts
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { ACTIVE_FLAVOR } from './active-flavor';
import { FLAVOR, FlavorConfig, Features, featureGuard, provideFlavor } from './flavor';
import { mergeTexts } from '../i18n/i18n';

const amigos: FlavorConfig = {
  name: 'Amigos de Tauro', shortName: 'Amigos', timezone: 'Atlantic/Canary', usernameOrder: 'lastFirst', website: null,
  features: {
    socialEvents: false, guests: false, prices: false, description: false, deadline: false, genderSplit: false,
    waitlistOrder: 'priority', userFields: ['phone', 'handicap', 'priority'],
  },
};

describe('flavor', () => {
  it('defaults to the El Faro build (tests always compile the default configuration)', () => {
    expect(ACTIVE_FLAVOR.name).toBe('GOLF EL FARO');
    expect(TestBed.inject(FLAVOR)).toBe(ACTIVE_FLAVOR);
    expect(TestBed.inject(Features).has('guests')).toBe(true);
  });

  it('answers feature and user-field questions from the injected flavour', () => {
    TestBed.configureTestingModule({ providers: [provideFlavor(amigos)] });
    const features = TestBed.inject(Features);
    expect(features.has('guests')).toBe(false);
    expect(features.userField('handicap')).toBe(true);
    expect(features.userField('rfeg')).toBe(false);
  });

  it('featureGuard sends switched-off routes to the not-found page', () => {
    TestBed.configureTestingModule({ providers: [provideRouter([]), provideFlavor(amigos)] });
    const result = TestBed.runInInjectionContext(() => featureGuard('guests')({} as never, {} as never));
    expect(TestBed.inject(Router).serializeUrl(result as never)).toBe('/not-found');
  });

  it('mergeTexts replaces only overridden leaves', () => {
    expect(mergeTexts({ app: { name: 'A', welcome: 'W' }, x: 'y' }, { app: { name: 'B' } })).toEqual({ app: { name: 'B', welcome: 'W' }, x: 'y' });
  });
});
```

- [ ] **Step 2: Run, expect FAIL** (modules missing): `cd frontend && npm test`

- [ ] **Step 3: Implement** `flavor.ts`

```ts
import { Injectable, InjectionToken, Provider, inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { ACTIVE_FLAVOR } from './active-flavor';

export type FeatureSwitch = 'socialEvents' | 'guests' | 'prices' | 'description' | 'deadline' | 'genderSplit';
export type UserField = 'rfeg' | 'memberNumber' | 'phone' | 'handicap' | 'priority';

/** Shape of frontend/flavors/<name>/flavor.json (validated on the PHP side, see TP\Core\Flavor). */
export interface FlavorConfig {
  name: string;
  shortName: string;
  timezone: string;
  usernameOrder: 'firstLast' | 'lastFirst';
  website: string | null;
  features: Record<FeatureSwitch, boolean> & { waitlistOrder: 'timestamp' | 'priority'; userFields: UserField[] };
}

/** The flavour this bundle was built for (angular.json configuration `elfaro` / `amigos`). */
export const FLAVOR = new InjectionToken<FlavorConfig>('FLAVOR', { providedIn: 'root', factory: () => ACTIVE_FLAVOR });

export function provideFlavor(flavor: FlavorConfig): Provider {
  return { provide: FLAVOR, useValue: flavor };
}

@Injectable({ providedIn: 'root' })
export class Features {
  private readonly flavor = inject(FLAVOR);

  has(feature: FeatureSwitch): boolean {
    return this.flavor.features[feature];
  }

  userField(field: UserField): boolean {
    return this.flavor.features.userFields.includes(field);
  }
}

/** Route guard: switched-off features look like unknown pages. */
export function featureGuard(feature: FeatureSwitch): CanActivateFn {
  return () => inject(Features).has(feature) || inject(Router).parseUrl('/not-found');
}
```

`active-flavor.ts` (the file `angular.json` replaces per flavour):

```ts
import flavor from '../../../../flavors/elfaro/flavor.json';
import type { FlavorConfig } from './flavor';
import type { Locale, Translations } from '../i18n/i18n';

/** Build-time flavour. angular.json configuration `amigos` swaps this file for active-flavor.amigos.ts. */
export const ACTIVE_FLAVOR = flavor as FlavorConfig;

export async function loadFlavorTexts(locale: Locale): Promise<Translations> {
  const module = (await import(`../../../../flavors/elfaro/i18n/${locale}.json`)) as { default: Translations };
  return module.default;
}
```

`active-flavor.amigos.ts`: identical, with `elfaro` → `amigos` in both paths.

The `import type` from `./flavor` and the value import in `flavor.ts` form a cycle; type-only imports are erased, so it's fine. If `npm run lint` complains (`import/no-cycle`), move the `FlavorConfig` types into `flavor.types.ts`.

If TypeScript rejects the JSON import, add `"resolveJsonModule": true` to `frontend/tsconfig.json` `compilerOptions`.

- [ ] **Step 4: Text merge in `i18n.ts`.** Replace `loadLocale`:

```ts
/** Deep merge: string leaves of `override` replace those of `base` (same rule as PHP Translator::merge). */
export function mergeTexts(base: Translations, override: Translations): Translations {
  const out: Translations = { ...base };
  for (const [key, value] of Object.entries(override)) {
    const current = out[key];
    out[key] =
      typeof value === 'object' && value !== null && typeof current === 'object' && current !== null
        ? mergeTexts(current as Translations, value as Translations)
        : value;
  }
  return out;
}

/** Each language file (and its flavour override) becomes its own lazy chunk. */
export async function loadLocale(locale: Locale): Promise<Translations> {
  const [base, override] = await Promise.all([
    import(`../../../i18n/${locale}.json`).then((m: { default: Translations }) => m.default),
    loadFlavorTexts(locale),
  ]);
  return mergeTexts(base, override);
}
```

with `import { loadFlavorTexts } from '../flavor/active-flavor';`.

- [ ] **Step 5: Run** `cd frontend && npm test && npm run lint`. Expect PASS.

- [ ] **Step 6: Commit** `feat(frontend): Flavour token, feature guard and flavour text overrides`

### Task 14: Angular build configurations, El Faro assets/theme/index moved into the flavour

**Files:**
- Move (git mv): root `site.webmanifest`, `browserconfig.xml`, `favicon.ico`, `favicon.svg`, `favicon-96x96.png`, `apple-touch-icon.png`, `web-app-manifest-192x192.png`, `web-app-manifest-512x512.png` → `frontend/flavors/elfaro/assets/`. Delete `frontend/public/favicon.ico` (identical role; keep the root one). Check with `cmp` first. If they differ, keep the root one and mention it in the commit message.
- Move: `frontend/src/index.html` → `frontend/flavors/elfaro/index.html`
- Create: `frontend/flavors/elfaro/theme.css` (the "Club theme" blocks cut from `frontend/src/styles.css` lines 100–128)
- Modify: `frontend/angular.json`, `frontend/proxy.conf.json`, `frontend/src/app/core/layout/header.ts`

- [ ] **Step 1: Move the files** with `git mv`. Before moving, list every root `*.png`/`*.svg`/`favicon*` file (`ls *.png *.svg favicon* 2>/dev/null`) so nothing else that `build.sh` copied gets lost.

- [ ] **Step 2: Theme.** Cut the three `:root { … }` blocks after the comment `/* Club theme (GOLF EL FARO) … */` (font, colours, `--primary-light`, `--social-*`, `--page-bg`) and the `html, body { font-family }` rule out of `styles.css` into `frontend/flavors/elfaro/theme.css` unchanged. `styles.css` keeps the generated neutral theme only.

- [ ] **Step 3: `angular.json`.** In `build.options`: remove `assets` (the `public` folder is now empty; delete it) and keep `"styles": ["src/styles.css"]`. Add two configurations:

```jsonc
            "elfaro": {
              "index": "flavors/elfaro/index.html",
              "assets": [{ "glob": "**/*", "input": "flavors/elfaro/assets" }],
              "styles": ["src/styles.css", "flavors/elfaro/theme.css"]
            },
            "amigos": {
              "index": "flavors/amigos/index.html",
              "assets": [{ "glob": "**/*", "input": "flavors/amigos/assets" }],
              "styles": ["src/styles.css", "flavors/amigos/theme.css"],
              "fileReplacements": [
                { "replace": "src/app/core/flavor/active-flavor.ts", "with": "src/app/core/flavor/active-flavor.amigos.ts" }
              ]
            }
```

Set `"defaultConfiguration": "production,elfaro"` for `build`. In `serve.configurations`, add `"development-elfaro": { "buildTarget": "tournament-planner-ui:build:development,elfaro" }`, `"development-amigos": { "buildTarget": "tournament-planner-ui:build:development,amigos" }` and `"production"` → `build:production,elfaro`. Set `"defaultConfiguration": "development-elfaro"`. `"start": "ng serve"` stays.

The `test` builder (`@angular/build:unit-test`) uses the build target's options. If tests then miss `theme.css`/assets, that's fine; components don't depend on them.

Until Task 19 creates `flavors/amigos/{index.html,theme.css,assets/}`, put placeholders there: copy the El Faro `index.html`, an empty `theme.css` and an empty `assets/.gitkeep`, so `ng build -c production,amigos` already compiles.

- [ ] **Step 4: Proxy.** The dev server now serves the icons and the manifest itself. Remove every entry except `/api` from `frontend/proxy.conf.json`.

- [ ] **Step 5: Header logo and link from the flavour.** In `header.ts`, inject `FLAVOR` and replace the logo block:

```html
          @if (flavor.website; as website) {
            <a [href]="website" class="shrink-0"><img src="/favicon.svg" [alt]="flavor.name" class="size-[75px] md:size-[100px]" /></a>
          } @else {
            <a routerLink="/" class="shrink-0"><img src="/favicon.svg" [alt]="flavor.name" class="size-[75px] md:size-[100px]" /></a>
          }
```

and add `protected readonly flavor = inject(FLAVOR);`. Each flavour ships its own `favicon.svg`. Check `header.spec.ts` (it may assert the alt `EL FARO GOLF`); update it to `GOLF EL FARO` (from `flavor.json`).

- [ ] **Step 6: Verify both builds compile and El Faro output is unchanged in content.** Run:

```bash
cd frontend && npx ng build -c production,elfaro && ls dist/tournament-planner-ui/browser | sort > /tmp/elfaro.txt && cat /tmp/elfaro.txt
npx ng build -c production,amigos; echo "exit $?"
npm test && npm run lint
```

Expected: the El Faro browser folder contains `favicon.svg`, `site.webmanifest`, `web-app-manifest-*.png`, `index.html`. The Amigos build exits 0. Tests and lint pass.

- [ ] **Step 7: Commit** `refactor(frontend): El Faro branding moves into frontend/flavors/elfaro; Angular configurations per flavour`

### Task 15: `build.sh --flavor`, `run.sh <flavor>`, `verify-dist` per flavour

**Files:** Modify `build.sh`, `run.sh`, `bin/verify-dist.php`, `e2e/run.sh` (call sites).

- [ ] **Step 1: `build.sh`.**
- Arguments: add `--flavor <name>` (also `--flavor=<name>`). It is **required**; without it: `echo "Missing --flavor <name> (one of: $(ls frontend/flavors | tr '\n' ' '))" >&2; exit 2`. An unknown name (no `frontend/flavors/$FLAVOR/flavor.json`) also exits 2 with that list. Parse with a `while [ $# -gt 0 ]; do case "$1" in --dirty) …; --flavor) FLAVOR="${2:-}"; shift;; --flavor=*) FLAVOR="${1#*=}";; *) unknown; esac; shift; done` loop.
- `VERSION=".../$FLAVOR"`: append `-$FLAVOR` to the computed version (before `-dirty`): `VERSION="${BUILD_VERSION:-$(date +%Y-%m-%d)-$(git rev-parse --short HEAD)}-$FLAVOR"`.
- `DIST="dist/$FLAVOR"`. Every later path uses `$DIST`. `releases/$VERSION` stays (the version carries the flavour).
- Frontend build: `npx ng build --configuration "production,$FLAVOR" …`.
- Replace `cp site.webmanifest browserconfig.xml favicon* *.png *.svg "$STAGE/" 2>/dev/null || true` with nothing. The assets now come out of the Angular build into `$BROWSER` and are copied with the other files. **But** the find loop puts every non-index file under `app/$VERSION/`. Root-level static files must stay at the web root, so copy the flavour's asset files to the root explicitly and exclude them from the versioned copy:

```bash
ASSETS="frontend/flavors/$FLAVOR/assets"
ASSET_NAMES=()
while IFS= read -r -d '' f; do ASSET_NAMES+=("${f#$ASSETS/}"); done < <(find "$ASSETS" -type f ! -name .gitkeep -print0)
```

In the `find . -type f …` loop, add a check that skips paths in `ASSET_NAMES` (compare `${f#./}`). After the loop, run `for a in "${ASSET_NAMES[@]}"; do mkdir -p "$STAGE/$(dirname "$a")"; cp "$BROWSER/$a" "$STAGE/$a"; done`.

- Backend: after `cp config/production.php "$FIRST/config/"`, add:

```bash
php -r '$f = json_decode(file_get_contents($argv[1]), true, 32, JSON_THROW_ON_ERROR); $f["id"] = $argv[2]; echo json_encode($f, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";' \
    "frontend/flavors/$FLAVOR/flavor.json" "$FLAVOR" > "$FIRST/config/flavor.json"
mkdir -p "$FIRST/resources/i18n/flavor"
cp frontend/flavors/"$FLAVOR"/i18n/*.json "$FIRST/resources/i18n/flavor/"
```

- `php bin/verify-dist.php "$DIST" "$FLAVOR"`.
- Instructions at the end: print the flavour, and `Upload to the $FLAVOR host only`.

- [ ] **Step 2: `verify-dist.php` per flavour.** Read `$flavor = $argv[2] ?? ''` (required: error if empty or unknown). Add `config/flavor.json` to the required files and check that its `id` equals `$flavor`. **Foreign assets check:** for every other flavour folder `frontend/flavors/<other>/assets`, compute `md5_file` of each asset. Error when an identical file (same hash) exists anywhere in the dist, unless the same file is also in the own flavour's assets. Also error when `$last/index.html` or `$first/site.webmanifest` contains another flavour's `name` from its `flavor.json`. Code:

```php
$root = dirname(__DIR__);
$own = [];
foreach (glob("$root/frontend/flavors/$flavor/assets/*") ?: [] as $f) {
    $own[md5_file($f)] = true;
}
$shipped = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dist, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    $shipped[md5_file($file->getPathname())] = $file->getPathname();
}
foreach (glob("$root/frontend/flavors/*", GLOB_ONLYDIR) ?: [] as $dir) {
    $other = basename($dir);
    if ($other === $flavor) {
        continue;
    }
    foreach (glob("$dir/assets/*") ?: [] as $asset) {
        $hash = md5_file($asset);
        if (isset($shipped[$hash]) && !isset($own[$hash])) {
            $errors[] = "asset of flavour $other in dist: " . $shipped[$hash];
        }
    }
    $otherName = json_decode((string) file_get_contents("$dir/flavor.json"), true)['name'];
    foreach (["$last/index.html", "$first/site.webmanifest"] as $f) {
        if (is_file($f) && str_contains((string) file_get_contents($f), $otherName)) {
            $errors[] = "$f mentions flavour $other ($otherName)";
        }
    }
}
```

The JS bundle contains the merged texts of the **own** flavour only (the swap replaces the import paths), so no bundle scan is needed.

- [ ] **Step 3: `run.sh` with a positional flavour**

```bash
#!/bin/bash
# Development: PHP (JSON API) on http://localhost:5000,
# Angular dev server on http://localhost:4200 (proxies /api to PHP).
#   ./run.sh            El Faro
#   ./run.sh amigos     Amigos de Tauro
set -e
FLAVOR="${1:-elfaro}"
if [ ! -f "frontend/flavors/$FLAVOR/flavor.json" ]; then
    echo "Unknown flavour: $FLAVOR (one of: $(ls frontend/flavors | tr '\n' ' '))" >&2
    exit 2
fi
trap 'kill 0' EXIT
APP_FLAVOR="$FLAVOR" ROUTE_CACHE_ENABLED=true php -S localhost:5000 -c php.ini &
cd frontend && npm start -- --configuration "development-$FLAVOR"
```

Note: `Config::loadEnvFile` doesn't overwrite existing `$_ENV`, but `APP_FLAVOR` from the process environment lands in `getenv()`, not `$_ENV` (depends on `variables_order`). `Flavor::load()` checks both, so it's fine.

- [ ] **Step 4: Call sites.** `e2e/run.sh`: `BUILD_VERSION=e2e-1 ./build.sh --dirty --flavor elfaro` and `mv dist/elfaro e2e/.dist-v1` (same for v2). The later `mkdir -p dist; cp -r e2e/.dist-v1/. dist/` stays, because `stack.sh` reads `dist/`. Search for other callers: `grep -rn "build.sh" --include=*.sh --include=*.yml --include=*.md .` and update them (docs in Task 22).

- [ ] **Step 5: Verify**

```bash
./build.sh --dirty; echo "exit $?"                    # expect 2 + list
./build.sh --dirty --flavor nope; echo "exit $?"      # expect 2 + list
./build.sh --dirty --flavor elfaro; echo "exit $?"    # expect 0, verify-dist OK, dist/elfaro/…
./build.sh --dirty --flavor amigos; echo "exit $?"    # expect 0 (placeholder assets)
ls dist/elfaro/1-upload-first | head -30               # favicon.svg, site.webmanifest at root; config/flavor.json
```

Expected: the El Faro dist has the same root files as before (`favicon*`, `site.webmanifest`, `browserconfig.xml`, `apple-touch-icon.png`, `web-app-manifest-*.png`), and `config/flavor.json` has `"id": "elfaro"`.

- [ ] **Step 6: Run the E2E suite** (El Faro is unchanged, so it must stay green): `cd frontend && npm run e2e; echo "exit $?"`. Expect 58 passing.

- [ ] **Step 7: Commit** `feat(build): build.sh --flavor (required), run.sh <flavor>, verify-dist rejects other flavours' assets`

### Task 16: Legacy Amigos URLs in `.htaccess`

**Files:** Modify `.htaccess`. Test: E2E `frontend/e2e/transition.spec.ts` (runs against El Faro; the rules are shared).

- [ ] **Step 1: Failing E2E test** (append to `transition.spec.ts`; use the file's existing imports)

```ts
test('old Amigos links redirect permanently', async ({ request }) => {
  const event = await request.get('/event.php?id=1', { maxRedirects: 0 });
  expect(event.status()).toBe(301);
  expect(event.headers()['location']).toMatch(/\/events\/1$/);

  for (const path of ['/index.php', '/login.php', '/add-event.php?date=2099-01-01', '/admin/users.php']) {
    const res = await request.get(path, { maxRedirects: 0 });
    expect(res.status(), path).toBe(301);
    expect(new URL(res.headers()['location'], 'http://x').pathname, path).toBe('/');
  }
});
```

- [ ] **Step 2: Implement.** In `.htaccess`, directly **before** `# Only index.php may run; …`:

```apache
# Links of the old Amigos app (event.php?id=N etc.) → new addresses.
# /index.php only redirects for direct requests (REDIRECT_STATUS empty), never for the internal /api rewrite.
RewriteCond %{QUERY_STRING} (^|&)id=(\d+)(&|$)
RewriteRule ^event\.php$ /events/%2? [R=301,L]
RewriteCond %{ENV:REDIRECT_STATUS} ^$
RewriteRule ^index\.php$ /? [R=301,L]
RewriteRule ^(login|logout|add-event|register|fixEvent)\.php$ /? [R=301,L]
RewriteRule ^admin/[a-z-]+\.php$ /? [R=301,L]
```

- [ ] **Step 3: Run** `cd frontend && npm run e2e -- transition.spec.ts; echo "exit $?"`. Expect PASS. Then run the whole E2E suite once (`npm run e2e`) to prove `/api/*` still works through `index.php`.

- [ ] **Step 4: Commit** `feat: Redirect old Amigos URLs (event.php?id=N → /events/N)`

### Task 17: Hide switched-off features in the UI

**Files:** Modify `frontend/src/app/app.routes.ts`, `features/admin/events/event-form.ts`, `features/admin/events/event-admin.page.ts`, `features/calendar/event-card.ts`, `features/admin/events/bulk-create.page.ts`, plus their specs.

- [ ] **Step 1: Failing specs.** Add to `event-form.spec.ts`:

```ts
  it('hides gender, description, prices and deadline when the flavour has them off', async () => {
    TestBed.configureTestingModule({ providers: [provideFlavor(amigosFlavor)] });
    const fixture = TestBed.createComponent(EventForm);
    await fixture.whenStable();
    const el: HTMLElement = fixture.nativeElement;
    for (const id of ['mixed', 'description', 'priceMembers', 'priceGuests', 'registrationClose']) {
      expect(el.querySelector(`#${id}`), id).toBeNull();
    }
    expect(el.querySelector('#capacity')).not.toBeNull();
  });
```

Put `amigosFlavor` (the `amigos` object from `flavor.spec.ts`) into `frontend/src/app/core/flavor/testing.ts` as `export const amigosFlavor: FlavorConfig = …` and import it in every spec that needs it. It is a test helper, not shipped, and lint allows it in `src/app`. Name it `testing.ts` and keep it out of production imports.

Add to `event-admin.page.spec.ts`: with `provideFlavor(amigosFlavor)`, `[data-guests]` is absent and the link to `/social-events/new` is absent. To `calendar.page.spec.ts`: an anonymous user's open event card links to `/events/3` instead of `/events/3/guests/new`. To `app.routes.spec.ts` (or `flavor.spec.ts`): routes `events/:id/guests/new`, `events/:id/guests/:guestId/edit` and every `social-events…` route have `featureGuard` (assert `canActivate` contains a function, then navigate with `provideFlavor(amigosFlavor)` and expect `/not-found`).

- [ ] **Step 2: Run, expect FAIL**: `cd frontend && npm test`

- [ ] **Step 3: Implement**
- `app.routes.ts`: add `canActivate: [featureGuard('guests')]` to both guest routes (prepend it to existing guards: `[adminGuard, featureGuard('guests')]`). Add `featureGuard('socialEvents')` to the four `social-events` routes; the guest one gets both. Add `{ path: 'not-found', loadComponent: … NotFoundPage }` before `'**'` if it doesn't exist yet.
- `event-form.ts`: `protected readonly features = inject(Features);` and wrap: the mixed checkbox in `@if (features.has('genderSplit'))`, description in `@if (features.has('description'))`, the price grid in `@if (features.has('prices'))`, the deadline in `@if (features.has('deadline'))`. When `genderSplit` is off, `send()` must send `mixed: true`: `mixed: this.features.has('genderSplit') ? m.mixed : true`.
- `bulk-create.page.ts`: same `@if` wrapping for its mixed/price/deadline inputs (read the file; same pattern) and the same `mixed: true` default.
- `event-admin.page.ts`: wrap the social-event block (the `app-social-event-row` / "create social event" link around line 94) in `@if (features.has('socialEvents'))` and the `<section hlmCard data-guests>` in `@if (features.has('guests'))`.
- `event-card.ts:157`: `return !this.closed() && e.available > 0 ? (this.features.has('guests') ? ['/events', String(e.id), 'guests', 'new'] : ['/events', String(e.id)]) : null;` (inject `Features`).
- `header.ts`: no change (the hidden `/social-events` link only drives the active state).

- [ ] **Step 4: Run** `cd frontend && npm test && npm run lint && npm run check:i18n`. Expect PASS.

- [ ] **Step 5: Commit** `feat(frontend): Hide guests, social events, prices, description, deadline and gender split per flavour`

### Task 18: User form/list, handicap column, name-conflict hint

**Files:** Modify `features/admin/users/{user-form.ts,user-list.page.ts}`, `features/events/event-detail.page.ts`, `features/admin/events/event-admin.page.ts` (registrations), i18n (3 files). Specs alongside.

- [ ] **Step 1: New i18n keys** (in all three files, under `users`): `phone` ("Telefon"/"Phone"/"Teléfono"), `handicap` ("Handicap"/"Handicap"/"Hándicap"), `priority` ("Priorität (kleiner = zuerst von der Warteliste)"/"Priority (lower = first off the waitlist)"/"Prioridad (menor = primero de la lista de espera)"), `name_conflict` ("Gleicher Name wie ein anderer Benutzer – Anmeldung mit Namen ist für beide nicht möglich, nur mit Benutzername."/"Same name as another user – they can't log in by name, only by username."/"Mismo nombre que otro usuario – no pueden iniciar sesión con el nombre, solo con el usuario."). Under `events`: `handicap_short` ("HCP"/"HCP"/"HCP").

- [ ] **Step 2: Failing specs.** In `user-form.spec.ts`, with `provideFlavor(amigosFlavor)`: there is no `#male`, `#rfeg` or `#memberNumber`; there is `#phone`, `#handicap`, `#priority`. Typing `12.4`/`1200` and submitting posts `{ …, phone: null, handicap: 12.4, priority: 1200, male: null, rfeg: null, memberNumber: null }`. In the default flavour: `#phone` is absent and the posted body has `phone: null, handicap: null, priority: null`. In `user-list.page.spec.ts`: a user with `nameConflict: true` shows `[data-name-conflict]`. With Amigos, the phone column shows `phone` and the El Faro default doesn't. In `event-detail.page.spec.ts`: a participant with `handicap: 12.4` renders `Max Muster · HCP 12.4`; with `handicap: null` only the name.

- [ ] **Step 3: Implement**
- `user-form.ts`: extend `FormModel` with `phone: string; handicap: string; priority: string`. Seed them from the DTO (`u?.phone ?? ''`, `u?.handicap?.toString() ?? ''`, `u?.priority?.toString() ?? ''`). Wrap the `male` select in `@if (features.has('genderSplit'))` and make `required(path.male, { when: () => this.features.has('genderSplit') })`. Wrap rfeg/memberNumber in `@if (features.userField('rfeg'))` / `('memberNumber')`. Add three fields in the same markup pattern as `memberNumber`: `phone` (`type="tel"`), `handicap` (`type="number" step="0.1" inputmode="decimal"`), `priority` (`type="number" inputmode="numeric"`), each in `@if (features.userField(…))` with an `app-field-error`. The request body:

```ts
        male: this.features.has('genderSplit') ? m.male === 'true' : null,
        phone: m.phone.trim() || null,
        handicap: m.handicap === '' ? null : Number(m.handicap),
        priority: m.priority === '' ? null : Number(m.priority),
```

After a successful save in `user-create.page.ts`/`user-edit.page.ts`: if the returned DTO has `nameConflict`, call `notify.info('users.name_conflict')` in addition to the success toast. Check that `Notify` has `info`; `check-i18n-keys` recognises `info(`.
- `user-list.page.ts`: add a phone cell, shown only when `features.userField('phone')`. Show a priority cell when `userField('priority')`. Next to the name, `@if (u.nameConflict) { <span data-name-conflict appBadge variant="destructive" [title]="'users.name_conflict' | t">!</span> }`. Reuse the badge from `shared/ui/badge.ts`.
- `event-detail.page.ts`: participant groups currently map to `names: string[]`. Change the registered/waitlist mappers to `.map((p) => (p.handicap === null ? p.name : \`${p.name} · ${this.i18n.t('events.handicap_short')} ${p.handicap}\`))`. Keep `ownName` highlighting: compare `name.startsWith(ownName)` instead of equality.
- `event-admin.page.ts`: if its registration rows show names, append the handicap the same way. `AdminRegistrationDto` has no handicap; skip it, the spec only asks for participant lists.

- [ ] **Step 4: Run** `cd frontend && npm test && npm run lint && npm run check:i18n`. Expect PASS.

- [ ] **Step 5: Commit** `feat(frontend): Amigos user fields, handicap in participant lists, name-conflict hint`

### Task 19: Amigos look: assets, theme (light + dark with photo), texts, index

**Files:** `frontend/flavors/amigos/{assets/*,theme.css,index.html,i18n/de.json,i18n/en.json,i18n/es.json}`

- [ ] **Step 1: Assets** (from the user's old app; **only image/manifest files**, never `*.php`/`*.sql`):

```bash
A=/home/hempe/git/amigos; D=frontend/flavors/amigos/assets
rm -f $D/.gitkeep
cp $A/favicon.ico $A/favicon.svg $A/favicon-96x96.png $A/apple-touch-icon.png \
   $A/web-app-manifest-192x192.png $A/web-app-manifest-512x512.png $A/browserconfig.xml \
   $A/background_low_res.png $A/logo.png $D/
```

Write `$D/site.webmanifest` (adds `id`, `start_url`, `scope` like El Faro, no `?v=` query):

```json
{
  "name": "Amigos de Tauro",
  "short_name": "Amigos",
  "id": "/",
  "start_url": "/",
  "scope": "/",
  "icons": [
    { "src": "/web-app-manifest-192x192.png", "sizes": "192x192", "type": "image/png", "purpose": "maskable" },
    { "src": "/web-app-manifest-512x512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
  ],
  "theme_color": "#109063",
  "background_color": "#ffffff",
  "display": "standalone"
}
```

Check `browserconfig.xml` for icon paths that don't exist in `$D` (e.g. `mstile-150x150.png`) and copy those too. Check that `favicon.svg` exists in the old app (it does: `ls` showed it). If it's only a generic favicon, still use it; the header shows it as the logo. If `logo.png` looks better in the header, switch the header `<img>` to a flavour-provided `/logo.png` (then El Faro needs a `logo.png` too). **Decide by looking at both images with the Read tool**, and keep El Faro's header unchanged.

- [ ] **Step 2: `theme.css`** (light = the old app's normal colours, dark = phone dark mode with photo):

```css
/* Club theme (Amigos de Tauro). Light like the old app, dark mode on phones with the background photo. */
:root {
  --font-sans: -apple-system, system-ui, 'Segoe UI', Helvetica, Arial, sans-serif;
  --foreground: #333333;
  --primary: #029062;
  --primary-foreground: #ffffff;
  --ring: #029062;
  --destructive: #b6334d;
  --social: #1c84c9;
  --primary-light: #b1d4ec;
  --social-light: #cce0f5;
  --social-foreground: #ffffff;
  --page-bg: #888a7c;
}

html,
body {
  font-family: var(--font-sans);
}

@media (prefers-color-scheme: dark) and (max-width: 1000px) {
  :root {
    color-scheme: dark;
    --primary: #425b3b;
    --ring: #9bbd91;
    --foreground: rgba(255, 255, 255, 0.9);
    --muted-foreground: rgba(255, 255, 255, 0.7);
    --background: transparent;
    --card: rgba(0, 0, 0, 0.6);
    --card-foreground: rgba(255, 255, 255, 0.9);
    --border: rgba(255, 255, 255, 0.1);
    --input: rgba(255, 255, 255, 0.2);
    --page-bg: #1d2a1a;
  }

  body {
    background: url('/background_low_res.png') center / cover fixed no-repeat, #1d2a1a;
  }
}
```

Check which variables `styles.css` and the Spartan components actually use (`grep -o 'var(--[a-z-]*)' frontend/src/styles.css frontend/src/app -r | sort -u`). Map the old app's dark values onto exactly those. The goal is translucent dark cards over the photo with readable white text. **Check visually** with the dev server (`./run.sh amigos`) and Playwright/Chrome dark-mode emulation on an iPhone-sized viewport, then adjust.

- [ ] **Step 3: `index.html`**: a copy of `flavors/elfaro/index.html` with `<title>Amigos de Tauro</title>` and `<meta name="theme-color" content="#109063" />`. Remove the Hind Guntur font import if the El Faro `index.html` or `main.ts`/`styles.css` imports it for El Faro only. Check where `@fontsource/hind-guntur` is imported; if it's in `styles.css`, move that import into `flavors/elfaro/theme.css`.

- [ ] **Step 4: Texts** `frontend/flavors/amigos/i18n/de.json` (only keys that exist in the base, see the `grep` in the plan header):

```json
{
  "app": {
    "name": "Amigos de Tauro",
    "welcome": "Willkommen bei den Amigos de Tauro",
    "club_name_line1": "AMIGOS",
    "club_name_line2": "DE TAURO"
  },
  "auth": {
    "welcome": "Willkommen bei den Amigos de Tauro"
  },
  "events": {
    "locked_message": "Liste geschlossen, bitte bei kurzfristigen Änderungen oder Kommentaren Arno anrufen!",
    "max_participants": "Max. Spieler",
    "participants": "Spieler"
  }
}
```

`en.json`: `app.name`, `app.welcome`, `app.club_name_line1/2`, `auth.welcome` (English equivalents: "Welcome to Amigos de Tauro"), `events.locked_message` ("List closed – for short-notice changes or comments please call Arno!"), `events.participants` "Players", `events.max_participants` "Max. players". `es.json` likewise ("Bienvenido a Amigos de Tauro", "Lista cerrada: para cambios de última hora o comentarios, llame a Arno.", "Jugadores", "Máx. jugadores"). Then search the base `de.json` for any other "Teilnehmer" that the Amigos UI shows (social-event keys are hidden, so ignore them).

- [ ] **Step 5: Verify**

```bash
cd frontend && npm run check:i18n && cd .. && composer test -- --filter FlavorTranslationsTest
./build.sh --dirty --flavor amigos; echo "exit $?"   # verify-dist: no El Faro asset, no "GOLF EL FARO" in index/manifest
./build.sh --dirty --flavor elfaro; echo "exit $?"
```

Then a manual look: `./run.sh amigos`, open http://localhost:4200 at an iPhone viewport in light and dark mode, log in as `admin`. Calendar, a tournament and the user list must show the Amigos header and theme and none of the guest/dinner UI.

- [ ] **Step 6: Commit** `feat: Amigos de Tauro flavour — logo, theme with dark-mode photo, texts`

---

# Phase 5: CI, E2E, docs

### Task 20: CI builds both flavours

**Files:** Modify `.github/workflows/ci.yml` (frontend job, the line `npx ng build --configuration production`).

- [ ] **Step 1:** Replace that step with:

```yaml
      - run: npx ng build --configuration production,elfaro
      - run: npx ng build --configuration production,amigos
```

The PHP job is unchanged: it pins `APP_FLAVOR=elfaro` via `phpunit.xml`, and the switches are tested by overrides. If the E2E job calls `build.sh`, it goes through `e2e/run.sh`, which is already updated.

- [ ] **Step 2: Commit** `ci: Build both flavours`

### Task 21: E2E for the Amigos build

**Files:** Create `e2e/seed-amigos.sql`, `frontend/e2e/amigos.spec.ts`. Modify `e2e/run.sh`, `e2e/stack.sh` (seed selectable), `frontend/playwright.config.ts`.

- [ ] **Step 1: Seed** `e2e/seed-amigos.sql`. Copy the three users from `e2e/seed.sql` (same argon2 hashes, so `admin`/`member`/`other` keep their passwords). Set Amigos-style data: `UPDATE users SET prio = …, handicap = …`, member = `prio 2000, handicap 12.4`, other = `prio 1000`. Add event `(1, 'E2E Amigos Voll', '2099-06-08', 1, NOW(), 0, 1)`, held by a fourth user `(4, 'filler', <same hash as other>, 0, 1, 'Fritz', 'Filler')` with `prio 3000`, and event `(2, 'E2E Amigos Gesperrt', '2099-06-15', 10, NOW(), 1, 1)`.

- [ ] **Step 2: `stack.sh`**: replace the mounted `e2e/seed.sql` with `${E2E_SEED:-e2e/seed.sql}` (keep the path absolute: `$PWD/${E2E_SEED:-e2e/seed.sql}`).

- [ ] **Step 3: `run.sh`**: after the existing `(cd frontend && npx playwright test "$@")`, add:

```bash
if [ -z "${E2E_SKIP_AMIGOS:-}" ] && [ $# -eq 0 ]; then
    e2e/stack.sh down
    BUILD_VERSION=e2e-a ./build.sh --dirty --flavor amigos
    rm -rf dist.e2e-amigos && mv dist/amigos dist.e2e-amigos && rm -rf dist && mv dist.e2e-amigos dist
    E2E_SEED=e2e/seed-amigos.sql e2e/stack.sh up
    (cd frontend && E2E_FLAVOR=amigos npx playwright test --project amigos-desktop --project amigos-mobile)
fi
```

Add `releases/e2e-a*` to `cleanup()`. The Amigos part is skipped when specific spec arguments are passed (fast single-spec runs) or when `E2E_SKIP_AMIGOS=1`.

- [ ] **Step 4: `playwright.config.ts`**: add `testIgnore: /amigos\.spec\.ts/` to the `desktop` and `mobile` projects. Add:

```ts
    { name: 'amigos-desktop', testMatch: /amigos\.spec\.ts/, use: { ...devices['Desktop Chrome'] } },
    {
      name: 'amigos-mobile',
      testMatch: /amigos\.spec\.ts/,
      use: { ...devices['iPhone 13'], browserName: 'chromium', defaultBrowserType: 'chromium', colorScheme: 'dark' },
    },
```

Plain `npx playwright test` without `--project` must not run the Amigos projects against the El Faro stack. Make the Amigos projects conditional: `...(process.env['E2E_FLAVOR'] === 'amigos' ? [amigosProjects] : [])`. Make the El Faro projects conditional on `E2E_FLAVOR !== 'amigos'`.

- [ ] **Step 5: Spec** `frontend/e2e/amigos.spec.ts`. Reuse the login helper from `support.ts`; check its name:

```ts
import { expect, test } from '@playwright/test';
import { login } from './support';

test('Amigos branding, no El Faro, no guests', async ({ page, request }) => {
  await page.goto('/');
  await expect(page).toHaveTitle('Amigos de Tauro');
  await expect(page.getByTestId('site-name')).toContainText('AMIGOS');
  expect(await (await request.get('/site.webmanifest')).json()).toMatchObject({ name: 'Amigos de Tauro' });
  expect((await request.get('/api/events/1/guest-info')).status()).toBe(404);
  expect((await request.get('/api/social-events/1')).status()).toBe(404);
});

test('login by "Lastname Firstname", handicap shown, waitlist by priority', async ({ page }) => {
  await login(page, 'Muster Max', 'Member123!');
  await page.goto('/events/1');
  await page.getByRole('button', { name: /warteliste/i }).click();
  await expect(page.locator('[data-participants]')).toContainText('Max Muster · HCP 12.4');
});

test('locked text override', async ({ page }) => {
  await login(page, 'member', 'Member123!');
  await page.goto('/events/2');
  await expect(page.getByText('Arno anrufen')).toBeVisible();
});

test('dark mode on phones shows the photo', async ({ page }, testInfo) => {
  test.skip(testInfo.project.name !== 'amigos-mobile', 'dark-mode check runs on the phone project');
  await page.goto('/');
  const bg = await page.evaluate(() => getComputedStyle(document.body).backgroundImage);
  expect(bg).toContain('background_low_res.png');
});
```

Add one more test for the priority order: log in as `other` (prio 1000) and join the waitlist of event 1. The admin page (`/events/1/admin`) must list Olga Other before Max Muster on the waitlist, even though Max registered first. Do it in a single test that registers both (member first, then other) through the UI, so it doesn't depend on test order.

- [ ] **Step 6: Run** `cd frontend && npm run e2e; echo "exit $?"`. Expect the 58 El Faro tests plus the new transition test plus the Amigos tests to pass. Note the new total.

- [ ] **Step 7: Commit** `test(e2e): Amigos build — branding, 404 for switched-off APIs, name login, priority waitlist, dark mode`

### Task 22: Docs

**Files:** `docs/DEPLOYMENT.md`, `README.md`, `docs/FRONTEND.md`, `docs/API.md`, `docs/TESTING.md`, `CLAUDE.md`, `database/README.md`, new `docs/WHATS-NEW-AMIGOS.md`, the spec status line.

- [ ] **Step 1: `DEPLOYMENT.md`**
- Release procedure: `./build.sh --flavor elfaro|amigos`, output in `dist/<flavor>/…`. Upload each flavour **only to its own host**; the version carries the flavour.
- `.env`: both hosts; `APP_TIMEZONE` optional (the flavour has a default).
- New section **"Amigos: one-time cut-over"**, the checklist from spec section 4: backup (phpMyAdmin export + FTP download) → `bin/amigos-dry-run.sh <export>` → `.env` → phpMyAdmin: `init.sql`, then `database/migrations/amigos-legacy.sql`, review the check list → build + upload → delete old files (`*.php` except `index.php`, `admin/`, `classes/`, `layout/`, `styles/`, `private/`, `init.sql`, `o.db.php`, `logo.jpg`, `logo_full.png`, `mstile-150x150.png` if unused) → checks (`/health`, login with name, `event.php?id=` link, phone shortcut, dark mode) → rollback. Note: `php.ini` stays.
- [ ] **Step 2: `README.md`**: a short "Flavours" section (El Faro, Amigos de Tauro; what Amigos has and lacks; build/run commands). Mention login by name under features.
- [ ] **Step 3: `FRONTEND.md`**: flavour folders, `FLAVOR`/`Features`/`featureGuard`, `active-flavor.ts` swap, text overrides (only existing keys), theme files, rule "never import `flavors/<name>` directly except in `active-flavor*.ts`".
- [ ] **Step 4: `API.md`**: `#[Feature]` → 404 (list the affected routes), user fields `phone`/`handicap`/`priority` (only per flavour, otherwise `null`), `nameConflict`, `ParticipantDto.handicap`, login by name.
- [ ] **Step 5: `CLAUDE.md` + `docs/TESTING.md`**: commands (`./run.sh [elfaro|amigos]`, `./build.sh --flavor <name>`, `bin/amigos-dry-run.sh`, `E2E_SKIP_AMIGOS=1`), architecture line about flavours, new test counts (take them from the final runs in Task 23). Update the memory file counts too.
- [ ] **Step 6: `docs/WHATS-NEW-AMIGOS.md`** (German, short, for Amigos members): new look (including dark mode on phones), login also with "Vorname Nachname", installable as an app (home screen), the waitlist is still by priority, old links keep working.
- [ ] **Step 7:** Spec status line → "implemented (2026-…)". Commit `docs: Flavours and the Amigos cut-over`.

### Task 23: Final verification

- [ ] **Step 1:** `composer test; echo "exit $?"` (expect 0). `composer test:coverage-check; echo "exit $?"` (expect 0, ≥ 85 %). `composer api:check; echo "exit $?"`.
- [ ] **Step 2:** `cd frontend && npm test; echo $?; npm run lint; echo $?; npm run check:i18n; echo $?`. All 0.
- [ ] **Step 3:** `./build.sh --flavor elfaro` and `./build.sh --flavor amigos` on a **clean** tree (commit first). Both exit 0 with verify-dist OK.
- [ ] **Step 4:** `cd frontend && npm run e2e; echo "exit $?"` → 0.
- [ ] **Step 5:** `bin/amigos-dry-run.sh /home/hempe/git/amigos/filiusag_amigoDb.sql` → `Dry run OK.` Report only the counts to the user.
- [ ] **Step 6:** Update the test counts in `CLAUDE.md`, `docs/TESTING.md` and the memory file. Commit.
- [ ] **Step 7:** Use superpowers:finishing-a-development-branch.
