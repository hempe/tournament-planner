# UI Refactor — Plan 5: Admin API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** JSON endpoints for every admin action that exists today: event list/create/edit/delete/lock/export, bulk creation, managing other users' registrations, guest edit/delete, social event create/edit/delete/lock and registration removal, and user management.

**Architecture:** Admin routes live in their own controllers (`EventAdminController`, `SocialEventAdminController`, `UserAdminController`, all `AdminMiddleware` at class level) under the same REST paths as the member API. Repository exceptions that are shown to users carry a translation key and parameters (`UserFacingException`); the API returns them as `{ error, params }`. Route matching becomes robust against declaration order: literal segments win over parameters.

**Tech Stack:** PHP 8.5, PHPUnit 10.5.

**Spec:** `docs/superpowers/specs/2026-11-20-ui-refactor-design.md` (§4.2, §4.7)

**Series (revised):** (1) ✅, (2) ✅, (3) member API ✅, (4) member UI, **(5) admin API**, (6) admin UI, (7) build/PWA/E2E/cut-over.

**Decisions:**
- Error responses may carry `params` for the translation: `{ "error": "social_events.menu_in_use", "params": { "menu": "Fisch", "count": 3 } }`. The frontend `ApiError` gains `params` (Task 1) and `Notify`/the global error handler pass them to `t()`.
- CSV export stays a file download, not JSON. A new `#[RawResponse]` attribute marks such routes; the TS generator skips them; the UI links to the URL directly.
- Bulk creation is stateless: `preview` returns the computed events, the client sends that list back to `bulk` (no `$_SESSION['bulk_events']`).
- Admins managing other users' registrations get the same lock rule as today (409 when locked).
- Event dates are not editable after creation (as today).
- Date-time inputs use `YYYY-MM-DDTHH:MM` (HTML `datetime-local`) in requests; stored as `YYYY-MM-DD HH:MM:00`.

---

## Conventions for every task

Same as plan 3 (`docs/superpowers/plans/2026-11-20-ui-refactor-03-member-api.md`, "Conventions for every task"): `$this->json()` / `apiLoginAs()` harness, `composer api:generate` + frontend build after route/DTO changes, `#[Returns]`/`#[Body]` on every route, class-level `CsrfMiddleware`, `composer test` must keep `Warnings: 3, Deprecations: 9`. Admin login in tests: `$this->apiLoginAs('admin', 'Admin123!')`. Every admin route needs an access test: anonymous → 401, member → 403.

Commit after each task with the given message; end every message with
`Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`

---

### Task 1: Foundations — route order, user-facing errors, raw responses

**Files:** modify `src/Core/Router.php`, `src/Core/Response.php` (`apiError` params), `src/Core/Api/TypeScriptGenerator.php`, `src/Models/SocialEventRepository.php` (throw sites), `frontend/src/app/core/api/api-client.ts` (+ spec), `frontend/src/app/core/feedback/notify.ts` / `core/errors/app-error-handler.ts` (+ specs); create `src/Core/UserFacingException.php`, `src/Core/Attributes/RawResponse.php`; tests in `tests/Integration/Core/RouterOrderTest.php`, `tests/Integration/Core/UserFacingExceptionTest.php`, `tests/Integration/Core/Api/TypeScriptGeneratorTest.php` (extend).

1. **Route order.** `Router::loadRoutes()` (used for attribute routes) must order routes like `addRoute()` does: stable sort by number of `{` in the pattern (fewer parameters first), keeping declaration order for ties. Test: load `GET /x/{id}/items/{itemId}` before `GET /x/{id}/items/me` and assert a request to `/x/5/items/me` hits the `me` handler; `/x/5/items/7` hits the parameter handler.
2. **`UserFacingException`** (`final class UserFacingException extends \RuntimeException`): constructor `(string $key, array $params = [])`; `getMessage()` returns `__($key, $params)` (legacy controllers keep working); public readonly `$key`, `$params`. Replace the three `throw new Exception(__('social_events.…', […]))` in `SocialEventRepository` with `throw new UserFacingException('social_events.…', […])`. Test: message equals the translated text; key/params exposed; legacy social event update test still passes.
3. **`Response::apiError(string $key, HttpStatus $status, array $fields = [], array $params = [])`**: adds `"params"` when non-empty. Test in `ResponseTest`.
4. **`#[RawResponse]`** (method attribute, no arguments). `TypeScriptGenerator::client()` skips methods carrying it. Extend the fixture controller in `TypeScriptGeneratorTest` with a `#[Get('/{id}/export')] #[RawResponse]` method and keep the expected output unchanged.
5. **Frontend:** `ApiError` gets `readonly params: Record<string, string | number> = {}` (4th constructor argument, read from `payload.params`). `AppErrorHandler` calls `notify.error(error.error, error.params)`. Specs: `api-client.spec.ts` asserts params are read; `app-error-handler.spec.ts` asserts they're passed.

- [ ] Steps: tests first → FAIL → implement → PASS → `composer test`, `composer api:check`, frontend `npm test && npm run lint && npx ng build` → commit:

```bash
git add src tests frontend/src/app/core
git commit -m "feat(api): Route order by specificity, user-facing errors with params, raw responses"
```

---

### Task 2: Event administration

**Files:** create `src/Controllers/Api/EventAdminController.php`, `src/Services/RecurringEvents.php`, DTOs below, `tests/Integration/Api/EventAdminApiTest.php`, `tests/Integration/Services/RecurringEventsTest.php`; modify `src/Controllers/EventController.php` (use `RecurringEvents` instead of its private `calculateRecurringDates`).

DTOs (`src/Api/Dto/`, property order = TS order):

| DTO | Properties |
|---|---|
| `AdminRegistrationDto` | `int $userId, string $name, string $comment, RegistrationState $state, bool $male, string $registeredAt, ?string $rfeg, ?string $memberNumber` |
| `AdminGuestDto` | `int $id, bool $male, string $firstName, string $lastName, ?string $email, ?float $handicap, ?string $comment, string $registeredAt` |
| `UserOptionDto` | `int $id, string $displayName` |
| `EventAdminDto` | `EventSummaryDto $event, bool $mixed, ?string $description, ?string $priceMembers, ?string $priceGuests, ?string $registrationClose, #[ArrayOf(AdminRegistrationDto::class)] array $registrations, #[ArrayOf(AdminGuestDto::class)] array $guests, #[ArrayOf(UserOptionDto::class)] array $availableUsers, ?SocialEventSummaryDto $socialEvent` |
| `EventRequest` | `string $name, string $date, int $capacity, bool $mixed, ?string $description, ?string $priceMembers, ?string $priceGuests, ?string $registrationClose` |
| `EventUpdateRequest` | `string $name, int $capacity, bool $mixed, ?string $description, ?string $priceMembers, ?string $priceGuests, ?string $registrationClose` |
| `BulkRequest` | `string $startDate, string $endDate, int $dayOfWeek, string $name, int $capacity, bool $mixed, ?string $description, ?string $priceMembers, ?string $priceGuests, ?int $registrationCloseDays, ?string $registrationCloseTime` |
| `BulkEventDto` | `string $name, string $date, int $capacity, bool $mixed, ?string $description, ?string $priceMembers, ?string $priceGuests, ?string $registrationClose` |
| `BulkPreviewDto` | `#[ArrayOf(BulkEventDto::class)] array $events` |
| `BulkCreateRequest` | `#[ArrayOf(BulkEventDto::class)] array $events` |
| `BulkResultDto` | `int $created` |

Contract (`#[RoutePrefix('/api/events')]`, `CsrfMiddleware` + `AdminMiddleware` at class level):

| Route | Body | Returns | Notes / errors |
|---|---|---|---|
| `GET /` | – | `EventSummaryDto[]` | all events, ordered by date (`DB::$events->all()`) |
| `POST /` | `EventRequest` | `EventAdminDto` | validation: name required max 255, date required date, capacity required integer min 1; 422 |
| `GET /{id}/admin` | – | `EventAdminDto` | 404 `events.not_found` |
| `PUT /{id}` | `EventUpdateRequest` | `EventAdminDto` | 404; 422 |
| `DELETE /{id}` | – | 204 | 404 |
| `PUT /{id}/lock` / `DELETE /{id}/lock` | – | `EventAdminDto` | lock / unlock |
| `GET /{id}/export` | – | CSV (`#[RawResponse]`) | same content and headers as `EventController::export` — move the CSV building into a private method or a small class and call it from both |
| `POST /{id}/registrations/{userId}` | `RegisterRequest` | `EventAdminDto` | 404 event; 404 `users.not_found`; 409 `events.locked_message` |
| `PATCH /{id}/registrations/{userId}` | `CommentRequest` | `EventAdminDto` | 404 `auth.not_registered` if not registered; 409 locked |
| `DELETE /{id}/registrations/{userId}` | – | `EventAdminDto` | 409 locked |
| `GET /{id}/guests/{guestId}` | – | `AdminGuestDto` | 404 `events.not_found` / `guests.not_found` (also when the guest belongs to another event) |
| `PUT /{id}/guests/{guestId}` | `GuestRegistrationRequest` | `AdminGuestDto` | admin rules (email/handicap optional); 404; 422 |
| `DELETE /{id}/guests/{guestId}` | – | 204 | 404 |
| `POST /bulk/preview` | `BulkRequest` | `BulkPreviewDto` | validation as `EventController::bulkPreview` (camelCase); `registrationCloseDays` 1–7 when set |
| `POST /bulk` | `BulkCreateRequest` | `BulkResultDto` | validate every event (name, date, capacity ≥ 1); create via `DB::$events->add(..., locked: true, ...)` exactly like `bulkStore` today (check `bulkStore` for the `locked` flag) |

`RecurringEvents::plan(...)` = today's `calculateRecurringDates` returning `BulkEventDto[]`; `EventController::bulkPreview` keeps working by converting the DTOs back to the arrays its view expects (or use the DTO properties in the view — keep the legacy view's output identical; its tests must pass).

Required tests: access matrix (anonymous 401 / member 403) for one GET and one mutating route; create → appears in list and admin detail; update changes name/capacity/prices/close; delete → 404 afterwards; lock/unlock toggles `event.isLocked`; export returns `text/csv` with a header row and one line per registration+guest (use `$response->headers['Content-Type']` and the body); admin registers another user → in `registrations`, removed from `availableUsers`; admin unregisters another user → waitlist promotion; registering for a locked event → 409; guest get/update/delete including wrong-event 404; bulk preview for 2099-06-01…2099-06-30 Mondays returns 5 events with computed `registrationClose` when days/time given; bulk create creates them; `RecurringEventsTest` covers the date maths (first occurrence on/after start, inclusive end, close time computation).

- [ ] Steps: tests first → FAIL → implement → PASS → `composer test`, `composer api:generate`, frontend build → commit:

```bash
git add src tests frontend/src/app/core/api/generated.ts
git commit -m "feat(api): Event administration endpoints incl. bulk creation and export"
```

---

### Task 3: Social event administration

**Files:** create `src/Controllers/Api/SocialEventAdminController.php`, DTOs below, `tests/Integration/Api/SocialEventAdminApiTest.php`.

| DTO | Properties |
|---|---|
| `AdminSocialRegistrationDto` | `int $id, string $name, ?string $email, bool $isGuest, ?string $menuName, ?int $tableNumber` |
| `SocialEventAdminDto` | `SocialEventSummaryDto $event, ?string $description, ?string $registrationClose, string $menus, string $tables, #[ArrayOf(MenuDto::class)] array $menuOptions, #[ArrayOf(TableDto::class)] array $tableOptions, #[ArrayOf(AdminSocialRegistrationDto::class)] array $registrations` (`menus`/`tables` are the comma-separated edit strings from `getMenusAsString()` / `getTablesAsString()`) |
| `SocialEventRequest` | `string $name, string $date, ?int $tournamentId, ?string $description, ?string $registrationClose, string $menus, string $tables` |

Contract (`#[RoutePrefix('/api/social-events')]`, `CsrfMiddleware` + `AdminMiddleware`):

| Route | Body | Returns | Notes / errors |
|---|---|---|---|
| `POST /` | `SocialEventRequest` | `SocialEventAdminDto` | name required, date required date; 422 |
| `GET /{id}/admin` | – | `SocialEventAdminDto` | 404 `social_events.not_found` |
| `PUT /{id}` | `SocialEventRequest` (`tournamentId` ignored) | `SocialEventAdminDto` | `UserFacingException` from the repository → 409 with its key and params |
| `DELETE /{id}` | – | 204 | |
| `PUT /{id}/lock` / `DELETE /{id}/lock` | – | `SocialEventAdminDto` | |
| `DELETE /{id}/registrations/{registrationId}` | – | `SocialEventAdminDto` | 404 when the registration belongs to another social event |

Required tests: access matrix; create linked to a tournament (`event.tournamentId`) and standalone; admin detail shows menus/tables strings and registrations (member + guest, `isGuest`); update menus; removing a menu that is in use → 409 `social_events.menu_in_use` with `params.menu` and `params.count`; lowering a table capacity below registrations → 409 `social_events.table_capacity_too_low`; delete; lock/unlock; remove a registration (and 404 for a foreign one).

- [ ] Steps as Task 2; commit:

```bash
git add src tests frontend/src/app/core/api/generated.ts
git commit -m "feat(api): Social event administration endpoints"
```

---

### Task 4: User administration

**Files:** create `src/Controllers/Api/UserAdminController.php`, DTOs below, `tests/Integration/Api/UserAdminApiTest.php`.

| DTO | Properties |
|---|---|
| `AdminUserDto` | `int $id, string $username, ?string $firstName, ?string $lastName, string $displayName, bool $male, bool $isAdmin, ?string $rfeg, ?string $memberNumber` |
| `UserCreateRequest` | `bool $male, string $username, string $password, ?string $firstName, ?string $lastName, ?string $rfeg, ?string $memberNumber` |
| `UserUpdateRequest` | `bool $male, string $username, ?string $password, ?string $firstName, ?string $lastName, ?string $rfeg, ?string $memberNumber` (empty/null password = unchanged) |

Contract (`#[RoutePrefix('/api/users')]`, `CsrfMiddleware` + `AdminMiddleware`):

| Route | Body | Returns | Notes / errors |
|---|---|---|---|
| `GET /` | – | `AdminUserDto[]` | `DB::$users->all()` order |
| `POST /` | `UserCreateRequest` | `AdminUserDto` | male required boolean, username required 3–255, password required; username taken → 422 `validation.failed` with `fields.username = { key: 'users.username_taken', params: { username } }` |
| `GET /{id}` | – | `AdminUserDto` | 404 `users.not_found` |
| `PUT /{id}` | `UserUpdateRequest` | `AdminUserDto` | same validation; taken by another user → 422 as above; password set only when non-empty |
| `PUT /{id}/admin` / `DELETE /{id}/admin` | – | `AdminUserDto` | grant / revoke admin |
| `DELETE /{id}` | – | 204 | an admin cannot delete themselves → 409 `errors.forbidden` (new safety rule; today it's possible — ⚠ mention in the commit body) |

Check `UserRepository::get()` returns rfeg/memberNumber/male (it selects them); `all()` too — if `all()` lacks columns, extend its SELECT (keep its tests green).

Required tests: access matrix; list; create (and taken username 422 with params); get/update incl. password change (log in with the new password via `apiLoginAs`); username taken by someone else 422, own username unchanged OK; grant/revoke admin; delete; self-delete 409.

- [ ] Steps as Task 2; commit:

```bash
git add src tests frontend/src/app/core/api/generated.ts
git commit -m "feat(api): User administration endpoints"
```

---

### Task 5: Documentation

- [ ] `docs/API.md`: section `## Admin endpoints` (contract tables from Tasks 2–4), the `params` field in errors, `#[RawResponse]`, route-order rule (literal before parameter is now automatic).
- [ ] Update test counts in `README.md`, `CLAUDE.md`, `docs/TESTING.md`.
- [ ] All checks: `composer test`, `composer api:check`, frontend `npm test && npm run lint && npx ng build`.
- [ ] Commit: `git commit -m "docs: Document admin API endpoints"`.

## Done criteria for plan 5

- Every admin action of the legacy UI has an API endpoint with access tests (anonymous 401, member 403, admin OK).
- `generated.ts` contains `eventAdminApi`, `socialEventAdminApi`, `userAdminApi`; export is excluded from it.
- Legacy UI unchanged; all checks green.
