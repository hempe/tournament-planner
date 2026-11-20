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
