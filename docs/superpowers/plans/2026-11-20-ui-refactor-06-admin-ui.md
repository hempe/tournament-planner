# UI Refactor — Plan 6: Admin UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** All admin screens in Angular at today's URLs, using the plan-5 API: event list, create, bulk create, event administration (edit, lock, export, manage registrations and guests), guest edit, social event create/administration, user list/create/edit.

**Architecture:** One lazy feature folder `features/admin/` with sub-folders per area; every route uses `adminGuard`. Shared form building blocks live in `src/app/shared/forms/` (also used by the member pages). Data via `resource()` + generated `eventAdminApi`, `socialEventAdminApi`, `userAdminApi`; mutations write the returned DTO back (`resource.value.set(dto)`); destructive actions confirm via `ConfirmService` with `danger: true`.

**Tech Stack:** Angular 22, signal forms, Spartan UI, Tailwind, Vitest.

**Spec:** `docs/superpowers/specs/2026-11-20-ui-refactor-design.md` (§4.1); API: `docs/API.md` "Admin endpoints".

**Series:** (1)–(5) ✅, **(6) admin UI**, (7) build/PWA/E2E/cut-over.

---

## Conventions for every task

Same as plan 4 (`docs/superpowers/plans/2026-11-20-ui-refactor-04-member-ui.md`, "Conventions for every task"): generated API only, fake `ApiClient` in tests, i18n for all text (`npm run check:i18n`), form submit via `submit` event in tests, mobile first (tables become stacked cards below `sm`), all four frontend checks green per commit. Use existing translation keys; add new keys only when no existing key fits (add to all three JSON files, run `vendor/bin/phpunit --filter TranslationValidationTest`).

`ApiError` with `fields` → show under the matching input (field names are the camelCase request properties); other errors → global toast (with `params`, plan 5).

Commit after each task; end every message with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`

## Routes (all `canActivate: [adminGuard]`, all `loadComponent`)

| URL | Component (`features/admin/...`) |
|---|---|
| `/events` | `events/event-list.page.ts` |
| `/events/new` (`?date=YYYY-MM-DD` prefill) | `events/event-create.page.ts` |
| `/events/bulk/new` | `events/bulk-create.page.ts` |
| `/events/:id/admin` | `events/event-admin.page.ts` |
| `/events/:id/guests/:guestId/edit` | `events/guest-edit.page.ts` |
| `/social-events/new` (`?tournamentId=`) | `social-events/social-event-create.page.ts` |
| `/social-events/:id/admin` | `social-events/social-event-admin.page.ts` |
| `/users` | `users/user-list.page.ts` |
| `/users/new` | `users/user-create.page.ts` |
| `/users/:id/edit` | `users/user-edit.page.ts` |

Declare `events/new` and `events/bulk/new` **before** `events/:id` in `app.routes.ts`.

---

### Task 1: Shared form pieces, event form, event list and create

- **First, consolidate what plan 4 left in feature folders:** move `SELECT_CLASSES` (exported from `features/social-events/social-event-detail.page.ts`) and `translateFieldErrors` (`features/guests/field-errors.ts`) into `src/app/shared/forms/` (`select.ts`, `field-errors.ts`), update the imports in the member pages, and build `app-field-error` on top of `translateFieldErrors`. All existing tests must stay green.
- `src/app/shared/forms/field-error.ts`: `<app-field-error [error]="apiError()" field="name" />` shows the translated `fields[field]` (with `params`, plus `{ field: <label> }` for `validation.*` keys) or a client-side required message.
- `events/event-form.ts`: presentational form used by create and admin pages. Inputs: initial value, `mode: 'create' | 'edit'` (date only in create), API error. Fields: `events.name`*, `events.date`* (date input, create only), `events.max_participants`* (number ≥ 1), `events.play_together` (checkbox → `mixed`), `events.description` (textarea), `events.price_members`, `events.price_guests` (text), `events.registration_close` (`datetime-local`). Output: `save` with the request body (`EventRequest` / `EventUpdateRequest`; empty strings → null).
- `event-list.page.ts`: table (`events.date`, `events.name`, `events.max_participants`, `events.registered` (= joined), `events.waitlist`); row click → `/events/:id/admin`; buttons `events.add` → `/events/new`, `events.bulk_create` → `/events/bulk/new`; delete per row (confirm `events.delete_confirm` with `{name}`, danger) → reload.
- `event-create.page.ts`: `events.new` heading; `?date` prefills the date; save → `eventAdminApi.create` → navigate `/events/:id/admin`, toast `events.update_success` (or a fitting existing key).
- Calendar: for admins, each day cell gets a small "+" link (`aria-label` = `events.new_for_date`) to `/events/new?date=YYYY-MM-DD`.

Tests: form validation (required fields, capacity ≥ 1) without API call; create posts the exact body and navigates; list renders rows and delete confirms then calls the API; `?date` prefill; member (not admin) is redirected by `adminGuard` (route-level — covered by guard tests; add a route-table assertion instead).

Commit: `feat(frontend): Admin event list and create`

---

### Task 2: Event administration page and guest edit

`event-admin.page.ts` (`/events/:id/admin`), loads `eventAdminApi.admin(id)` (check the generated name):
- Header: back to `/events/:id`, date, name; actions: save (event form in edit mode), lock/unlock (`events.lock` / `events.unlock`, confirm `events.lock_confirm` / `events.unlock_confirm`), delete (confirm `events.delete_confirm_short`, danger → navigate `/`), export (`events.export`, a plain `<a href="/api/events/{id}/export" download>` — not via ApiClient).
- Registrations: `mixed` → two groups `events.participants` (registered) and `events.waitlist`; not mixed → four groups `events.male` / `events.female` × registered / waitlist (`events.male` + ' – ' + `events.waitlist`). Each row: name, registered-at (formatted date-time, small), comment input + save (no confirm; toast `events.comment_update_success`), unregister (confirm `events.unregister_user_confirm` with `{name}`, danger).
- Add users (`auth.not_registered` heading): `availableUsers` with comment input + register button (confirm `auth.register_user` / `auth.register_user_waitlist` with `{username}` depending on free spots).
- Guests (`guests.title`, split male/female when not mixed): first/last name, email, handicap, comment; edit link `/events/:id/guests/:guestId/edit`; delete (confirm `guests.delete_confirm`, danger); "add guest" link `guests.add` → `/events/:id/guests/new`.
- Social event: linked → card linking `/social-events/:sid/admin` (`social_events.edit`); none → link `social_events.new` → `/social-events/new?tournamentId=:id`.

`guest-edit.page.ts`: same fields as the guest form (admin rules: email/handicap optional), prefilled from `GET /{id}/guests/{guestId}`; save → toast `guests.update_success` → `/events/:id/admin`; delete → toast `guests.delete_success` → back.

Tests: grouping for mixed vs. separate; register another user posts to the userId route with the comment; unregister confirms with the user's name; lock toggles and re-renders from the returned DTO; export link href; guest edit save/delete.

Commit: `feat(frontend): Event administration and guest edit`

---

### Task 3: Bulk create

`bulk-create.page.ts`, two steps on one page:
1. Form (`BulkRequest`): `events.bulk_start_date`*, `events.bulk_end_date`*, `events.bulk_day_of_week`* (select Monday–Sunday via `Intl` weekday names, values 1…6, 0), `events.name`*, `events.max_participants`*, `events.play_together`, `events.description`, `events.price_members`, `events.price_guests`, `events.bulk_registration_close_days` (1–7), `events.bulk_registration_close_time` (time). Button `events.bulk_preview` → `bulkPreview`.
2. Preview (`events.bulk_preview_title`): table of the returned events (date formatted, name, capacity, registration close); empty → `events.bulk_no_events_message`. Buttons: back (keeps the form values) and `events.bulk_create_all` → `bulkCreate({ events })` → toast `events.bulk_create_success` (check its placeholder, e.g. `{count}`) → `/events`.

Tests: preview request body; preview rendering and empty state; create sends exactly the previewed events; back keeps values.

Commit: `feat(frontend): Bulk event creation with preview`

---

### Task 4: Social event create and administration

- `social-event-form.ts`: `social_events.name`*, `social_events.date`*, `social_events.description`, `social_events.registration_close` (`datetime-local`), `social_events.menus` (+ hint `social_events.menus_hint`), `social_events.tables` (+ hint `social_events.tables_hint`).
- `social-event-create.page.ts`: `?tournamentId` prefills date (and name `social_events.title`-based default is not needed — leave empty) by loading the tournament's admin DTO; heading `social_events.new`, shows `social_events.linked_tournament` with the tournament name when linked; save → `/social-events/:id/admin`.
- `social-event-admin.page.ts`: header (back to the tournament admin page when linked, else `/`); form in edit mode; save errors with `params` (menu in use, table capacity too low) are shown as toast by the global handler; lock/unlock; delete (confirm `social_events.delete_confirm`, danger → back); participants table (`social_events.participants`: name (+ "Gast" marker when `isGuest` — use `guests.title` or add a key), table, menu, remove with confirm); `social_events.add_guest` link → `/social-events/:id/guests/new`.

Tests: create with tournament prefill; save body; 409 with params reaches the toast (`Notify.error` called with key and params); remove participant confirms and re-renders.

Commit: `feat(frontend): Social event create and administration`

---

### Task 5: User administration

- `user-list.page.ts` (`users.title`): table username, display name, admin badge; actions: edit (`users.edit` → `/users/:id/edit`), toggle admin (`users.give_admin_rights` / `users.remove_admin_rights`, confirm `users.give_admin_rights_confirm` / `users.remove_admin_rights_confirm` with `{username}`), delete (confirm `users.delete_confirm` with `{username}`, danger); button `users.add_user` → `/users/new`.
- `user-form.ts`: `users.salutation`* (`users.mr` / `users.mrs`), `users.username`* (min 3), `users.password`* (create) / `users.new_password` (edit, optional), `users.first_name`, `users.last_name`, `users.member_number`, `users.rfeg`.
- `user-create.page.ts` → toast `users.create_success` → `/users`; `user-edit.page.ts` → toast `users.update_success` → `/users`. Username taken → field error `users.username_taken` with `{username}` under the username input.
- Self-delete 409 surfaces as a toast (global handler).

Tests: list actions call the right endpoints after confirmation; create/edit bodies (edit without password sends `password: null`); username-taken field error.

Commit: `feat(frontend): User administration`

---

### Task 6: Routes, docs, smoke test

- Add all routes from the table above; extend `app.routes.spec.ts` (paths, `adminGuard`, order of `events/new`/`events/bulk/new` before `events/:id`).
- `docs/FRONTEND.md`: add the admin screens to the "Screens" table.
- Smoke test as in plan 4 Task 6 (curl the admin URLs through :4200 for the app shell, then stop the servers; say explicitly that no browser click-through was done).
- All checks (frontend four + `composer test` + `composer api:check`).

Commit: `docs: Document admin screens; admin route tests`

## Done criteria for plan 6

- Every legacy admin screen has an Angular counterpart at the same URL with tests; all checks green; the legacy PHP UI is unchanged (it is removed in plan 7).
