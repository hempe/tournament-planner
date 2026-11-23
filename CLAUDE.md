# CLAUDE.md

Guidance for Claude Code (claude.ai/code) when working in this repository.

## Commands

```bash
./run.sh [elfaro|amigos]         # PHP API on :5000 + Angular dev server on :4200 (proxies /api); default elfaro
composer test                    # PHPUnit (needs MySQL/MariaDB; never run two PHPUnit processes at once)
composer test:coverage-check     # PHPUnit with PCOV, fails below 85 % line coverage
composer api:generate            # regenerate frontend/src/app/core/api/generated.ts from the PHP DTOs
composer api:check               # fail if generated.ts is stale (also a PHPUnit test and part of build.sh)
./build.sh --flavor <name>       # production build -> dist/<name>/1-upload-first + 2-upload-last (flavour required; clean tree, --dirty to override)
bin/amigos-dry-run.sh <export>   # rehearse the one-off Amigos DB migration on a phpMyAdmin export (Docker; prints counts only)
mysql < database/init.sql        # create/upgrade the schema (./migrate.sh does the same from .env)
```

Inside `frontend/`: `npm test` (Vitest), `npm run lint`, `npm run check:i18n`, `npx ng build`, `npm run e2e`
(Playwright against Apache + MariaDB in Docker, builds two El Faro releases and then one Amigos release itself;
`E2E_SKIP_SLOW=1` skips the update spec, `E2E_SKIP_AMIGOS=1` the Amigos run).

`.env` holds `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USERNAME`, `DB_PASSWORD`; production also needs `APP_ENV=production`.

## Architecture

Golf event management: tournaments (`events`) and social events (`social_events`, always linked to a tournament;
menus and tables, no waitlist).

- **Flavours** — one code base, two clubs, chosen at build time: `elfaro` (GOLF EL FARO) and `amigos` (Amigos de Tauro).
  `frontend/flavors/<name>/` holds `flavor.json` (switches, user fields, timezone), theme, `index.html`, text overrides
  and assets. PHP: `TP\Core\Flavor` (`config/flavor.json` in a dist, else `APP_FLAVOR`, default elfaro); routes of
  switched-off features use `#[Feature('…')]` → 404. Angular: `-c production,<name>` swaps `active-flavor.ts`;
  `inject(Features)`. Base texts are club-neutral; club names live only in the flavour overrides.
- **Frontend** — Angular 22 single-page app in `frontend/` (signals, signal forms, Spartan UI components in
  `src/app/ui/`, no RxJS, no `HttpClient`). Features are lazy-loaded from `app.routes.ts`. It is a PWA
  (service worker, update prompt). See [docs/FRONTEND.md](docs/FRONTEND.md).
- **Backend** — PHP 8.3 JSON API under `/api` in `src/Controllers/Api/`. `index.php` only serves `/api/*` and
  `/health`; there is no server-rendered HTML. See [docs/API.md](docs/API.md).
- **DTOs and client** — `src/Api/Dto/` define request/response shapes; routes declare them with `#[Returns]` /
  `#[Body]`. `bin/generate-api.php` turns them into `frontend/src/app/core/api/generated.ts`
  (commit it; the frontend never hand-writes API types).
- **Routing** — PHP 8 attributes (`#[Get]`, `#[Post]`, `#[RoutePrefix]`, `#[Middleware]`) discovered by
  `RouteLoader`, cached in production. See [docs/ROUTING.md](docs/ROUTING.md).
- **Middleware** — `AuthMiddleware` (401 for anonymous), `AdminMiddleware` (403), `CsrfMiddleware` (every API controller).
- **Errors** — API errors are `{ "error": "<i18n key>", "fields": {...}, "params": {...} }`; the frontend translates the keys.
- **Database** — repositories behind `src/Models/DB.php` (`DB::$events`, `DB::$users`, `DB::$guests`, `DB::$socialEvents`).
  Session-based auth (`User::loggedIn()`, `User::admin()`).
- **i18n** — `frontend/src/i18n/{de,en,es}.json`, read by the frontend and by PHP (copied to `resources/i18n/` in `dist/`).
  All user-facing text goes through a key; never hardcode German in code or templates. Keys are checked by `npm run check:i18n`.
- **Build/deploy** — `build.sh` builds into a version folder `app/<version>/` and splits the output into two manual
  uploads. See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). Server `.htaccess`: only `index.php` runs, `/api/*` goes to PHP,
  everything else is an Angular route.

### Directory structure

- `src/Controllers/Api/`, `src/Api/Dto/`, `src/Core/`, `src/Models/`, `src/Middleware/`, `src/Services/` — backend
- `frontend/src/app/` — `core/` (api, auth, i18n, layout, update), `features/`, `shared/`, `ui/`
- `frontend/e2e/`, `e2e/` — Playwright specs, Docker stack (Apache + PHP 8.3 + MariaDB), seed data
- `tests/Integration/` — PHPUnit tests (API, core, models, security)
- `bin/` — generator, route cache, dist verification, coverage check
- `database/` — `init.sql` schema and migrations

## Testing policy

Every change is tested, no exceptions:

- Flavour switches are tested in both states within one run (`Flavor::override([...])` in PHPUnit, `setTestFlavor()` in Vitest).
- Run `composer test` for PHP changes and `npm test`, `npm run lint`, `npm run check:i18n` in `frontend/` for frontend changes;
  run `composer api:check` after touching DTOs or API routes.
- Add or update tests for modified or new behavior: PHPUnit for API/backend, Vitest for frontend.
- Cross-cutting, routing, `.htaccess`, build or deploy changes also need `npm run e2e` (and an E2E spec when behavior is new).
- All tests must pass; pre-existing failures are acceptable, new ones are not.
- Never run PHPUnit in parallel (shared test DB `TP_test`) — also not alongside `npm run e2e`.
- Current counts: 456 PHPUnit tests, 304 Vitest tests, 69 Playwright tests (59 El Faro on desktop + iPhone 13, 10 Amigos on desktop + dark-mode iPhone 13). PHP coverage must stay >= 85 %.
- Test DB: `TP_test`, admin seeded as `admin` / `Admin123!`. `IntegrationTestCase::request()` resets `$_GET`/`$_POST` on every call.
  Details: [docs/TESTING.md](docs/TESTING.md).

## Documentation policy

Update docs with every change: [docs/API.md](docs/API.md) for endpoints, [docs/FRONTEND.md](docs/FRONTEND.md) for
screens and frontend rules, [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for build/deploy, `README.md` for features, and the
test counts here and in `docs/TESTING.md` after larger changes. New translation keys go into all three `i18n/*.json` files.

## PHP gotchas (8.3 in production, strict types)

- `array_map()` does not accept named args for built-in functions.
- Operator precedence: `$a ?? false || $b === 'x'` returns the string value of `$a` — use `!empty($a) || ...`.
- Null-guard `$_GET` values before `strtotime()` / `htmlspecialchars()` (deprecated with null).
- Never use `is_callable` on arbitrary strings (`is_callable('Date')` is true because `date()` exists).
- Do not use syntax newer than PHP 8.3; `build.sh` lints with `php:8.3-cli` when Docker is available.
