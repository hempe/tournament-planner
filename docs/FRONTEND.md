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
| `npm run e2e` | Playwright E2E against Apache + MariaDB in Docker (needs Docker; see `docs/TESTING.md`) |

From the repository root, `./run.sh` starts PHP and the Angular dev server together.

## Structure

```
src/app/
├── core/       api/ (ApiClient + generated.ts), auth/, i18n/, feedback/, errors/, layout/, init.ts, version.ts
├── features/   one folder per area, each lazy-loaded from app.routes.ts
└── ui/         Spartan components — copied into the repo, ours to change
src/i18n/       de.json, en.json, es.json (also read by PHP)
```

## Screens

| URL | Component | Access |
|---|---|---|
| `/` (`?month=YYYY-MM`, legacy `?date=`) | `features/calendar/calendar.page` | everyone (anonymous: tournaments only) |
| `/login` | `features/login/login.page` | anonymous only (`guestGuard`) |
| `/events/:id` | `features/events/event-detail.page` | members (`authGuard`) |
| `/events/:id/guests/new` | `features/guests/guest-registration.page` | everyone |
| `/social-events/:id` | `features/social-events/social-event-detail.page` | members (`authGuard`) |
| `/social-events/:id/guests/new` | `features/guests/social-guest-registration.page` | everyone |
| `/events` | `features/admin/events/event-list.page` | admins (`adminGuard`) |
| `/events/new` (`?date=YYYY-MM-DD`) | `features/admin/events/event-create.page` | admins |
| `/events/bulk/new` | `features/admin/events/bulk-create.page` (form, then preview) | admins |
| `/events/:id/admin` | `features/admin/events/event-admin.page` | admins |
| `/events/:id/guests/:guestId/edit` | `features/admin/events/guest-edit.page` | admins |
| `/social-events/new` (`?tournamentId=`) | `features/admin/social-events/social-event-create.page` | admins |
| `/social-events/:id/admin` | `features/admin/social-events/social-event-admin.page` | admins |
| `/users` | `features/admin/users/user-list.page` | admins |
| `/users/new` | `features/admin/users/user-create.page` | admins |
| `/users/:id/edit` | `features/admin/users/user-edit.page` | admins |
| anything else | `features/not-found/not-found.page` | everyone |

`RegistrationFlow` (`features/events/registration-flow.ts`) holds register / unregister / comment update
including confirmations, toasts and the dinner prompt after registering; the calendar and the tournament
detail page both use it. Each method resolves with the fresh detail DTO, or `null` when the user cancelled.

`ConfirmService.choose({ message, actions })` is the general dialog: it resolves with the `value` of the
chosen action, or `null` when cancelled (`ask()` is the yes/no shortcut). The unregister dialog uses it for
"tournament only" / "tournament & dinner".

Admin pages share form components (`event-form`, `social-event-form`, `user-form`) that emit the request
body; the pages own the API call. A 422 is shown under the matching input (`app-field-error` in
`shared/forms/`), every other error (e.g. 409 with params) reaches the global error handler as a toast. The
administration pages write the DTO returned by each mutation back into their `resource`, but re-seed the edit
form only on load and after its own save, so unsaved edits survive other actions (lock, registrations, …).

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

## PWA and update prompt

The production build registers the Angular service worker (`ngsw-worker.js`, `registerWhenStable:30000`;
disabled in dev mode). `ngsw-config.json`: the `core` group (index, manifest, main/polyfills/styles bundles) is
prefetched; everything else under `/app/**` and the icons are cached lazily. `/api/**` is never cached, and
navigation requests fall back to `index.html`.

`build.sh` puts all hashed assets in `app/<version>/` (the build sets `--deploy-url=/app/<version>/`) and keeps
`index.html`, `ngsw.json` and `version.txt` at the root; they are uploaded last (see `docs/DEPLOYMENT.md`) and
served with `Cache-Control: no-cache`.

`UpdateService` (`core/update/`) calls `SwUpdate.checkForUpdate()` on start and whenever the tab becomes visible
again. When a new release is ready it shows a persistent toast ("Neue Version verfügbar" / "Neu laden"); the action
calls `activateUpdate()` and reloads. Without a service worker, the toast is offered when the `X-App-Version` header of
an API response differs from the version compiled into the app (`APP_BUILD_VERSION`, `core/version.ts`). The version is
also shown in the footer.
