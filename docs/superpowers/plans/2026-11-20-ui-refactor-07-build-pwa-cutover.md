# UI Refactor — Plan 7: Build, PWA, E2E and Cut-over Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship it: version-folder build with a two-step manual upload, Apache config, a real PWA with update prompt, the legacy PHP UI removed, end-to-end tests against a production-like Docker setup, and the deployment docs for the one-time switch.

**Architecture:** `build.sh` builds Angular with `--deploy-url=/app/<version>/` and a compile-time version constant, moves the assets into `app/<version>/`, regenerates `ngsw.json`, assembles the PHP API, and splits the result into `dist/1-upload-first/` and `dist/2-upload-last/`. `.htaccess` routes `/api/*` to PHP, blocks internal folders, redirects stale form POSTs and serves `index.html` for everything else. Playwright runs against `php:8.3-apache` (= production's PHP 8.3) + MariaDB in Docker using the built `dist/`.

**Tech Stack:** Bash, Apache 2.4 (`mod_rewrite`, `mod_headers`), `@angular/service-worker` 22.3, Playwright 1.65, Docker Compose.

**Spec:** `docs/superpowers/specs/2026-11-20-ui-refactor-design.md` (§6, §7.3, §8)

**Series:** (1)–(6) ✅, **(7) build/PWA/E2E/cut-over**.

**Verified beforehand:** `php:8.5-apache` image exists; Docker daemon available; `@angular/build:application` supports `deployUrl` and `define`; `@angular/service-worker@22.3.0`, `@playwright/test@1.65.1` available.

---

## Conventions for every task

- Checks: `composer test`, `composer api:check`; in `frontend/`: `npm test`, `npm run lint`, `npm run check:i18n`, `npx ng build`. From Task 4 on also `./build.sh --dirty` (it must succeed).
- No RxJS in app code — `SwUpdate` is used through its Promise methods only (`checkForUpdate()`, `activateUpdate()`); do not subscribe to `versionUpdates`.
- Commit after each task with the given message; end every message with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`

---

### Task 1: Version constant and update prompt

**Files:** `frontend/src/app/core/version.ts` (+ spec), `frontend/src/app/core/update/update.service.ts` (+ spec), `frontend/src/app/app.ts`, `frontend/src/typings.d.ts` (or `src/global.d.ts`), translation keys `update.available`, `update.reload` (all three languages).

- `declare const APP_BUILD_VERSION: string | undefined;` (ambient). `APP_VERSION` factory: `typeof APP_BUILD_VERSION === 'string' ? APP_BUILD_VERSION : 'dev'`. `ng build --define APP_BUILD_VERSION="'2026-12-04-a1b2c3d'"` sets it (Task 4 passes it).
- `UpdateService` (root):
  - `check()`: if `SwUpdate.isEnabled`, `await swUpdate.checkForUpdate()`; if it resolves `true`, show a persistent toast (`update.available`, action `update.reload`) once.
  - action: `await swUpdate.activateUpdate(); document.location.reload();`
  - when the service worker is disabled (dev) and `VersionService.outdated()` becomes true, show the same toast with action = plain reload.
  - triggers: called from `App` constructor (after startup), on `document` `visibilitychange` → visible, and via `effect()` on `VersionService.outdated()`.
- Toast with action: Spartan sonner supports `toast(message, { action: { label, onClick }, duration: Infinity })` — check `@spartan-ng/brain/sonner` typings; extend `Notify` with `persistent(key, actionKey, onAction)`.
- Tests: fake `SwUpdate` (`isEnabled`, `checkForUpdate`, `activateUpdate` as `vi.fn`): update found → toast shown once (two checks → one toast); action calls `activateUpdate` then reload (stub `document.location.reload` via a small injectable `RELOAD` token); disabled SW + outdated version → toast; `APP_VERSION` default `'dev'`.

Commit: `feat(frontend): Build version constant and update prompt`

---

### Task 2: Service worker and manifest

**Files:** `frontend/ngsw-config.json`, `frontend/angular.json`, `frontend/src/app/app.config.ts`, repo-root `site.webmanifest`, `frontend/src/index.html`.

- `npm i @angular/service-worker@22.3.0`; in `app.config.ts`: `provideServiceWorker('ngsw-worker.js', { enabled: !isDevMode(), registrationStrategy: 'registerWhenStable:30000' })`; `angular.json` production config: `"serviceWorker": "ngsw-config.json"`.
- `ngsw-config.json`:

```json
{
  "$schema": "./node_modules/@angular/service-worker/config/schema.json",
  "index": "/index.html",
  "assetGroups": [
    {
      "name": "core",
      "installMode": "prefetch",
      "updateMode": "prefetch",
      "resources": { "files": ["/index.html", "/site.webmanifest", "/app/*/main-*.js", "/app/*/polyfills-*.js", "/app/*/styles-*.css"] }
    },
    {
      "name": "lazy",
      "installMode": "lazy",
      "updateMode": "lazy",
      "resources": { "files": ["/app/**", "/*.png", "/*.svg", "/*.ico"] }
    }
  ],
  "navigationUrls": ["/**", "!/api/**", "!/**/*.*"]
}
```

  `/api/**` is never cached (no `dataGroups`).
- Root `site.webmanifest`: add `"id": "/"`, `"start_url": "/"`, `"scope": "/"`; keep name, short_name, icons, colours unchanged. `index.html`: `<link rel="manifest" href="/site.webmanifest" />`, `<meta name="theme-color" content="#333333" />`, apple-touch-icon link.
- Test: `src/app/pwa-config.spec.ts` reads `ngsw-config.json` via `import config from '../../ngsw-config.json'` (enable `resolveJsonModule` in `tsconfig.spec.json` if needed) and asserts `/api/**` is excluded from `navigationUrls` and no group matches `/api`.

Commit: `feat(frontend): Service worker config and manifest for existing installs`

---

### Task 3: Remove the legacy PHP UI

Everything the Angular app replaced goes. **Keep:** `src/Core/*` (minus UI-only parts listed below), `src/Models/*`, `src/Middleware/*`, `src/Api/**`, `src/Services/**`, `src/Controllers/Api/**`, `index.php`, `bootstrap.php`, `/health`.

Delete:
- `src/Views/`, `src/Components/`, `src/Layout/`, `src/scripts/`, `styles/`, `src/Core/Component.php` (and its `require` in `bootstrap.php`), `docs/COMPONENTS.md`, `docs/components/`.
- Legacy controllers: `src/Controllers/{Home,Auth,Event,Guest,SocialEvent,User,Admin}Controller.php`. Before deleting `AdminController` (`/admin/migrate-names`), keep the cut-over precondition from spec §8 in `docs/DEPLOYMENT.md` (Task 6).
- Helpers only used by the HTML UI: `flash`, `get_flash`, `has_flash`, `flash_input`, `old`, `csrf_token` (and `Security::generateCsrfToken`, config `csrf_token_name`). Keep `__()` and `logger()`.
- `Response::notFound()`/`forbidden()` HTML rendering → plain JSON-free text responses (`Response::notFound()` returns `new Response('Not Found', 404)`); `Application::handleError` non-API branch → plain text.
- `AuthMiddleware`/`AdminMiddleware`: remove the non-API branches (redirect/HTML) — every route is an API route now.

Tests:
- Delete the tests of deleted code (`tests/Integration/Controllers/*` except anything still exercising kept code, `MonkeyTest`, component/view tests, `LocalizationTest` parts about views). Keep model/repository tests, Core tests, `TranslationValidationTest`, `tests/Integration/Api/*`, `Security/*` (adapt to the API).
- **Coverage audit (required):** before deleting a legacy controller test file, list its test methods and make sure each behaviour has an API test (`tests/Integration/Api/*`) or a model test. Write missing API tests first. Put the mapping (legacy test → replacing test) into the commit message body.
- `composer test:coverage-check` (85 %) must still pass; if it fails, add API tests rather than lowering the threshold.
- `grep -rn "Views/\|Components\\\\\|Layout/\|flash(\|old(\|csrf_token" src tests index.php bootstrap.php` → nothing.

- **Harden `/health`** (in `index.php`): anonymous callers get only `{"status":"ok"}` or `{"status":"error"}` (HTTP 503 when the DB is unreachable); `php_version`, `environment`, `memory_usage`, `locale` and the database detail are returned only to logged-in admins (`User::admin()`). DB error messages are never echoed — log them instead. Tests: anonymous response has exactly the key `status`; admin response contains `php_version`; DB failure → 503 without message (simulate by pointing the check at an invalid port via a test seam, or skip the failure case if no seam exists and say so).

Commit: `refactor!: Remove the server-rendered UI (replaced by the Angular app)`

---

### Task 4: Build, version folder, upload split, `.htaccess`

**Files:** `build.sh` (rewrite), `.htaccess` (rewrite), `.gitignore` (`/releases/`), `bin/verify-dist.php` (new), `config/production.php` if it references removed config.

`build.sh` (bash, `set -euo pipefail`), steps:
1. `--dirty` flag. Version = `$(date +%Y-%m-%d)-$(git rev-parse --short HEAD)`; if `git status --porcelain` is non-empty: without `--dirty` print the dirty files and exit 1; with `--dirty` append `-dirty`.
2. `composer api:check` (fail if `generated.ts` is stale).
3. Frontend: `(cd frontend && npm ci && npx ng build --configuration production --deploy-url=/app/$VERSION/ --define APP_BUILD_VERSION="'$VERSION'")`. Output in `frontend/dist/tournament-planner-ui/browser/`.
4. Stage the frontend in `dist/.stage-frontend/`: `index.html` at the root, everything else under `app/$VERSION/` (exclude `ngsw.json`, `ngsw-worker.js`, `safety-worker.js`, `worker-basic.min.js` — those stay at the root), plus repo-root `site.webmanifest`, `favicon*`, `*.png`, `*.svg`, `browserconfig.xml`. Verify that `index.html` references only `/app/$VERSION/…` assets (fail otherwise).
5. Regenerate the service worker manifest for the final layout: `(cd frontend && npx ngsw-config ../dist/.stage-frontend ngsw-config.json /)`.
6. Backend into `dist/1-upload-first/`: `index.php`, `bootstrap.php`, `.htaccess`, `src/` (without `src/scripts` etc. — they no longer exist), `config/production.php`, `resources/i18n/*.json` (from `frontend/src/i18n`), `vendor/` via `composer install --no-dev --optimize-autoloader` (as today), route cache pre-build (as today), empty `storage/cache/` and `logs/`, `version.txt` → **no**, version.txt goes to step 7.
7. Split: `dist/2-upload-last/` gets `index.html`, `ngsw.json`, `version.txt` (`echo $VERSION`); everything else from the stage (`app/`, `ngsw-worker.js`, `safety-worker.js`, manifest, icons) goes to `dist/1-upload-first/`.
8. `releases/$VERSION/` ← copy of `dist/2-upload-last/` (git-ignored).
9. `php bin/verify-dist.php dist` (structure check: required files exist in the right folder, no `index.html`/`ngsw.json` in step 1, `app/$VERSION/main-*.js` exists, no `src/Views`, no dev files like `tests/`, `frontend/`, `.env`).
10. Print the instructions from spec §6.2 (version, the two upload steps, previous version = newest other folder in `releases/`, check URL).

`.htaccess` (copied into `dist/1-upload-first/`):

```apache
Options -Indexes
DirectoryIndex index.html

<IfModule mod_rewrite.c>
RewriteEngine On

# Internal folders are never served
RewriteRule ^(src|vendor|config|resources|storage|logs)(/|$) - [R=404,L]

# API → PHP front controller
RewriteRule ^api(/.*)?$ index.php [QSA,L]
RewriteRule ^health$ index.php [QSA,L]

# Real files (app/<version>/*, icons, manifest, service worker)
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^ - [L]

# Stale legacy form posts from pages opened before the switch → reload the same URL
RewriteCond %{REQUEST_METHOD} !^(GET|HEAD)$
RewriteRule ^ %{REQUEST_URI} [R=303,L]

# Everything else is an Angular route
RewriteRule ^ index.html [L]
</IfModule>

<IfModule mod_headers.c>
<If "%{REQUEST_URI} =~ m#^/app/#">
    Header set Cache-Control "public, max-age=31536000, immutable"
</If>
<FilesMatch "^(index\.html|ngsw\.json|ngsw-worker\.js|site\.webmanifest|version\.txt)$">
    Header set Cache-Control "no-cache"
</FilesMatch>
</IfModule>
```

PHP side: `X-App-Version` reads `version.txt` (plan 1) — it lives in the web root next to `index.php`, which is where `AppVersion` looks. Verify.

Verification: `./build.sh --dirty` succeeds; `ls dist/1-upload-first dist/2-upload-last`; `grep -o '/app/[^"]*main-[^"]*' dist/2-upload-last/index.html`; `cat dist/2-upload-last/version.txt`.

Commit: `build: Version-folder build with two-step upload and Apache routing`

---

### Task 5: End-to-end tests (Docker + Playwright)

**Files:** `e2e/docker-compose.yml`, `e2e/apache.conf` (enables `mod_rewrite`, `mod_headers`, `AllowOverride All`), `e2e/seed.sql` (admin + member + events/social event in the future), `frontend/playwright.config.ts`, `frontend/e2e/*.spec.ts`, npm script `e2e`.

- Compose: `db` (`mariadb:11`, init with `database/init.sql` + `e2e/seed.sql`), `web` (`php:8.3-apache` — **production runs PHP 8.3.37**, so E2E uses the same minor version with `docker-php-ext-install mysqli pdo_mysql` via a tiny `e2e/Dockerfile`, `a2enmod rewrite headers`), mounts `dist/1-upload-first` + `dist/2-upload-last` merged (copy both into one `e2e/.site/` folder before `docker compose up`), `.env` for the container generated by the script (`APP_ENV=production`, `APP_SECURE=false`, DB credentials of the compose db).
- `npm run e2e` (in `frontend/`): `../build.sh --dirty` → assemble `e2e/.site` → `docker compose -f ../e2e/docker-compose.yml up -d --build --wait` → `npx playwright test` → `docker compose down -v`.
- Specs (Chromium + an iPhone 13 project for the member flows):
  - `auth.spec.ts`: login/logout; header button reads "Abmelden" only for logout.
  - `member.spec.ts`: register with comment from detail page; dinner prompt appears and "Ja, anmelden" navigates to the social event; register for the dinner with menu and table; unregister with "Turnier & Abendessen"; waitlist when full.
  - `guest.spec.ts`: anonymous calendar → guest form → success toast; locked event shows the locked message.
  - `admin.spec.ts`: create event, bulk preview+create, lock/unlock, CSV export download, user create.
  - `transition.spec.ts`: legacy URLs `/events/1`, `/social-events/1`, `/?date=2099-6-1` load the app; `POST /events/1/register` → 303 to `/events/1`; `/site.webmanifest` name/icons unchanged and `start_url` `/`; `/src/Core/Config.php`, `/vendor/autoload.php`, `/logs/` → 404; `/app/<v>/main-*.js` has `immutable`, `/index.html` `no-cache`.
  - `update.spec.ts`: service worker registers in production; after deploying a second build (rebuild with a different `--define`, copy only `2-upload-last` + new `app/<v2>` into `.site`), reloading shows the `update.available` toast and its action loads v2 (footer shows v2). If this proves flaky in CI-less local runs, keep it but tag it `@slow` and document how to run it.

Commit: `test(e2e): Playwright suite against Apache + MariaDB in Docker`

---

### Task 6: Budget, docs, cut-over checklist

- Bundle budget: measure the initial transfer size (`npx ng build --stats-json` or the build summary's "Estimated transfer size"). If the transfer size is < 200 kB, raise the `initial` budget warning to `600kB`/error `1MB` with a comment in `angular.json`; otherwise lazy-load the confirm dialog (`@defer (on idle)`) and re-measure. Note the numbers in the commit body.
- `docs/DEPLOYMENT.md`: rewrite as the manual procedure from spec §6.5 (two uploads, check version, cleanup old `app/` folders, rollback via `releases/<version>/`), plus the **one-time cut-over checklist** from spec §8 (verify `/admin/migrate-names` reported 0 pending *before* this release — the endpoint no longer exists afterwards; DB migrations; deploy; delete legacy server files `styles/`, `src/scripts/`, `src/Views/`, `src/Components/`, `src/Layout/`, `resources/lang/`; open the app on a phone that had the old home-screen shortcut).
- **Production runs PHP 8.3.37** (checked 2026-11-20 via `/health`). Add `"php": ">=8.3"` to `composer.json` `require` and `"config": { "platform": { "php": "8.3.0" } }` so Composer never resolves packages needing a newer PHP; `build.sh` additionally lints `src/`, `bin/`, `index.php`, `bootstrap.php` with `docker run --rm -v "$PWD":/app -w /app php:8.3-cli php -l` when Docker is available (warn and continue when it isn't).
- PHP version: the new code uses `readonly class` (PHP ≥ 8.2). Check the minimum with `grep -rn 'readonly class' src | head -1` and state "PHP 8.2+" (or higher if newer syntax is used — check with `php -l` under the oldest version available via Docker, e.g. `docker run --rm -v "$PWD":/app php:8.2-cli sh -c 'find /app/src -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"'`) in `README.md` and in the cut-over checklist ("check the PHP version in the hosting panel before uploading").
- `README.md` (features, requirements: Node 26 dev-only, Docker for E2E; deploy section → link to DEPLOYMENT.md), `CLAUDE.md` (architecture section: Angular SPA + JSON API; remove component system docs and references to `src/Views`/`Components`; commands incl. `npm run e2e`; testing policy incl. E2E for cross-cutting changes), `docs/TESTING.md` (PHP/Vitest/E2E counts and commands), `docs/FRONTEND.md` (PWA/update prompt section).
- Update the memory-relevant facts in `CLAUDE.md` only (no auto-memory files).
- All checks + `npm run e2e`.

Commit: `docs: Deployment procedure and cut-over checklist for the Angular release`

## Done criteria for plan 7

- `./build.sh` produces `dist/1-upload-first` / `dist/2-upload-last` with the version folder and refuses a dirty tree.
- `npm run e2e` passes against Apache + MariaDB.
- The legacy UI code is gone; PHP coverage ≥ 85 %; all unit/integration checks pass.
- `docs/DEPLOYMENT.md` lets the maintainer do the switch by hand without further help.
