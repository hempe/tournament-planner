# Technical Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The "Technical" ideas from `docs/WHATS-NEW.md`: spam protection for the public guest forms, a login rate limit, the Hind Guntur font hosted with the app instead of Google Fonts, CI on GitHub, frontend error reporting to the server log, and two small clean-ups.

**Architecture:** One small file-based `RateLimiter` (in `storage/cache/`, no DB migration — production deploys are manual and the shared host has no Redis) used by login, guest registration and client error reports. A honeypot field on both guest forms. A public `POST /api/client-errors` endpoint (CSRF-protected like every API route) that writes sanitised reports to the app log. Fonts bundled via `@fontsource/hind-guntur`. GitHub Actions workflow for PHP + frontend checks; E2E as a manually triggered job.

**Tech Stack:** PHP 8.3-compatible code (production runs 8.3.37; dev 8.5), PHPUnit, Angular 22, Vitest, GitHub Actions.

## Conventions

- PHP code must run on **PHP 8.3** (no 8.4/8.5 features). `docker run --rm -v "$PWD":/app -w /app php:8.3-cli php -l <file>` checks syntax.
- Check every command by **exit code** (`cmd > /tmp/x.log 2>&1; echo exit=$?`), not by grepping summary lines. `vendor/bin/phpunit` must end in `OK, but there were issues!` with only the 9 known PHPUnit deprecations; frontend: `npm test`, `npm run lint`, `npm run check:i18n`, `npx ng build` must all exit 0.
- Never run two PHPUnit processes at once (shared test DB).
- New translation keys in all three `frontend/src/i18n/*.json`; then `vendor/bin/phpunit --filter TranslationValidationTest`.
- After route/DTO changes: `composer api:generate` and commit `generated.ts`.
- TDD; one commit per task; commit messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

---

### Task 1: `RateLimiter` and a trustworthy client IP

**Files:** create `src/Core/RateLimiter.php`, `tests/Integration/Core/RateLimiterTest.php`; modify `src/Core/Request.php` (+ `tests/Integration/Core/RequestTest.php`), `src/Core/HttpStatus.php` (already has `TOO_MANY_REQUESTS`).

- `Request::clientIp(): string` → `$server['REMOTE_ADDR'] ?? ''`. Rate limits must use this, **not** `getIp()` (which trusts the spoofable `X-Forwarded-For`). Test: with `HTTP_X_FORWARDED_FOR` set, `clientIp()` still returns `REMOTE_ADDR`.
- `RateLimiter` (`final class`):
  - `__construct(string $directory = <repo>/storage/cache/rate-limits, ?\Closure $clock = null)` — clock returns the current unix time (tests inject a fake clock).
  - `hit(string $key, int $max, int $windowSeconds): bool` — records an attempt; returns `false` when the key already has `$max` attempts inside the window (the attempt is then not recorded).
  - `tooMany(string $key, int $max, int $windowSeconds): bool` — read-only check.
  - `retryAfter(string $key, int $windowSeconds): int` — seconds until the oldest attempt in the window expires (≥ 1 when limited, 0 otherwise).
  - `clear(string $key): void`.
  - Storage: one JSON file per `sha256($key)` holding the attempt timestamps; read/modify/write under `flock(LOCK_EX)`; prune timestamps older than the window on every write; create the directory if missing. Failing storage (unwritable dir) must **not** break requests: log a warning and allow the request.
- Tests: allows up to max, blocks the next, window expiry via fake clock, `retryAfter`, `clear`, separate keys don't interfere, unwritable directory → allows.

Commit: `feat(core): File-based rate limiter and spoof-proof client IP`

### Task 2: Login rate limit

**Files:** modify `src/Controllers/Api/AuthController.php`, `tests/Integration/Api/AuthApiTest.php`; translation key `auth.too_many_attempts` (de: "Zu viele Anmeldeversuche. Bitte in :minutes Minuten erneut versuchen.", en/es equivalents).

- Key `login:<clientIp>:<lowercased username>`; **5 failed attempts per 15 minutes**.
- Before checking the password: if `tooMany` → `429 auth.too_many_attempts` with `params: { minutes: ceil(retryAfter/60) }` (also when the 6th attempt would have the right password — no oracle).
- Failed login → `hit`; successful login → `clear`.
- Tests (use a temp directory for the limiter: make the directory configurable via `$_ENV['RATE_LIMIT_DIR']` read in the controller/factory, set it in the test's `setUp` to a fresh `sys_get_temp_dir()` path and delete it in `tearDown`): 5 wrong attempts → 422 each, 6th (even correct) → 429 with `params.minutes` 15; another username from the same IP is not affected; success clears the counter.

Commit: `feat(security): Rate-limit failed logins`

### Task 3: Guest form spam protection

**Files:** modify `src/Controllers/Api/EventsController.php` and `src/Controllers/Api/SocialEventsController.php` (guest routes), `src/Api/Dto/GuestRegistrationRequest.php` and `SocialGuestRequest.php` (add `?string $website` **last**), tests in `tests/Integration/Api/GuestApiTest.php`; frontend `features/guests/guest-registration.page.ts`, `social-guest-registration.page.ts` (+ specs); translation key `errors.too_many_requests`.

- **Honeypot:** both guest forms get a field `website` that people never see: wrapper `class="absolute -left-[9999px] h-px w-px overflow-hidden"` with `aria-hidden="true"`, the input has `tabindex="-1"` and `autocomplete="off"`, label "Website". Bodies send `website: ''` normally. Server: if `website` is non-empty → return **204 without storing anything** (bots believe it worked).
- **Rate limit:** key `guest:<clientIp>`, **10 per hour** across both guest routes, counted only for non-admins and only after validation passed (typos don't burn attempts) → `429 errors.too_many_requests`.
- Tests: honeypot filled → 204 and nothing stored; 11th registration within an hour → 429, admin not limited; frontend specs: honeypot field exists, is hidden from assistive tech and is sent empty.

Commit: `feat(security): Honeypot and rate limit for public guest registration`

### Task 4: Frontend error reporting

**Files:** create `src/Controllers/Api/ClientErrorController.php`, `src/Api/Dto/ClientErrorReport.php`, `tests/Integration/Api/ClientErrorApiTest.php`; modify `frontend/src/app/core/errors/app-error-handler.ts` (+ spec); regenerate `generated.ts`.

- `POST /api/client-errors` (public, `CsrfMiddleware` like all API controllers), body `ClientErrorReport { message: string, stack: ?string, url: string, appVersion: string }` → 204.
- Server: truncate `message` to 500 chars, `stack` to 4000, `url` to 300; strip control characters; log with `logger()->error('Client error', [...plus clientIp, user id or null, user agent truncated to 200])`. Rate limit key `client-error:<clientIp>` 20 per 10 minutes → beyond that return 204 silently (no error for the client).
- `AppErrorHandler`: for unexpected errors (not `ApiError`, not `AbortError`) send one report via the generated client (fire and forget, errors while reporting are swallowed, never recursive), at most 5 reports per page load, de-duplicated by message.
- Tests: PHP — report is logged (assert via a test logger or by reading the configured log file in a temp location), long values truncated, rate limit silent, invalid body 422; frontend — unexpected error triggers one POST with message/url/version, `ApiError` and `AbortError` don't, duplicates and the 6th report are not sent, a failing report doesn't throw.

Commit: `feat: Report unexpected frontend errors to the server log`

### Task 5: Host the Hind Guntur font with the app

**Files:** `frontend/package.json` (`@fontsource/hind-guntur`), `frontend/src/styles.css`, `frontend/src/index.html`, `docs/FRONTEND.md`.

- `npm i @fontsource/hind-guntur`; in `styles.css` import the weights actually used — check with `grep -rhoE 'font-(light|normal|medium|semibold|bold)' src/app | sort -u` and include 300 only if `font-light` is used; at least 400, 500, 600, 700 (e.g. `@import '@fontsource/hind-guntur/400.css';`).
- Remove the two `fonts.googleapis.com`/`fonts.gstatic.com` preconnects and the Google stylesheet link from `index.html`.
- Verify: `npx ng build` → the font files are in the output (hashed, inside the app folder after `build.sh`), and `grep -r "fonts.googleapis\|fonts.gstatic" frontend/src dist/2-upload-last` finds nothing. Visual check with Playwright against `ng serve`: `document.fonts.check('16px "Hind Guntur"')` is true and **no request goes to a Google domain** (listen to `page.on('request')`).
- E2E: add to `transition.spec.ts` an assertion that loading `/` makes no request to `*.googleapis.com` / `*.gstatic.com`.

Commit: `feat(frontend): Self-host the Hind Guntur font (no Google Fonts request)`

### Task 6: Clean-ups

- `events.play_together` → de "Gemischt", en "Mixed", es "Mixto" (check where it is shown; adjust specs that assert the text).
- Remove `Response::unauthorized()` and `Request::isAjax()` if they have no callers (`grep -rn "unauthorized(\|isAjax(" src tests`); remove their tests.

Commit: `chore: German label for mixed events; remove unused helpers`

### Task 7: CI on GitHub

**Files:** create `.github/workflows/ci.yml`; modify `docs/TESTING.md`, `README.md` (badge optional).

- Trigger: `push` (all branches) and `pull_request`; plus `workflow_dispatch` with input `e2e: boolean`.
- Job `php`: `ubuntu-latest`, service `mariadb:11` (`MARIADB_ROOT_PASSWORD: root`, health check), `shivammathur/setup-php@v2` with `php-version: '8.3'`, extensions `mysqli, pdo_mysql, intl, pcov`, coverage `pcov`; `composer install --no-interaction --no-progress`; run `vendor/bin/phpunit` with env `DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=root DB_PASSWORD=root DB_NAME=TP_test` (check that `phpunit.xml`'s `<env>` entries don't override existing environment variables; if they do, add `force="false"` or pass the values another way and document it); then `composer api:check` and `php bin/coverage-check.php` after a coverage run (or run `composer test:coverage-check`). The job fails on test failures but **not** on the 9 known PHPUnit deprecations (PHPUnit's exit code with only deprecations is 0? — verify locally with `vendor/bin/phpunit; echo $?` and handle accordingly).
- Job `frontend`: `actions/setup-node@v4` with `node-version: 26`, `cache: npm`, `cache-dependency-path: frontend/package-lock.json`; `npm ci`, `npm run lint`, `npm run check:i18n`, `npm test`, `npx ng build --configuration production`. `generated.ts` freshness is checked in the PHP job.
- Job `e2e` (only on `workflow_dispatch` with `e2e: true`): checkout, setup PHP 8.3 + composer, Node 26, `cd frontend && npm ci && npx playwright install --with-deps chromium && npm run e2e` (Docker is available on GitHub-hosted runners; `e2e/stack.sh` uses plain `docker`).
- Validate the YAML locally (`npx --yes @action-validator/cli .github/workflows/ci.yml` or `python3 -c 'import yaml,sys; yaml.safe_load(open(sys.argv[1]))' .github/workflows/ci.yml`).
- After pushing the branch, check the run with `gh run list --branch feature/ui-refactor --limit 3` and `gh run view <id> --log-failed`; fix until green (the workflow is the only file that may need iterations).
- `docs/TESTING.md`: CI section (what runs when, how to start E2E manually: Actions → CI → Run workflow → e2e).

Commit: `ci: GitHub Actions for PHP and frontend checks; manual E2E job`

### Task 8: Docs

- `docs/WHATS-NEW.md`: move the six technical items from "Ideas for later" into a "Done since" note (or mark them ✅) — keep the other ideas.
- `docs/API.md`: client error endpoint, 429 responses (`auth.too_many_attempts` with `params.minutes`, `errors.too_many_requests`), honeypot field.
- Test counts in `README.md`, `CLAUDE.md`, `docs/TESTING.md`.

Commit: `docs: Technical hardening`
