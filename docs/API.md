# JSON API

The Angular frontend talks to PHP through a JSON API under `/api/*`. API controllers live in
`src/Controllers/Api/`. PHP serves nothing but this API and `/health`; the UI is the Angular app in `frontend/`.

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

Errors that are not tied to a field may carry `params` for the translation, e.g.
`{ "error": "social_events.menu_in_use", "params": { "menu": "Fisch", "count": 3 } }`. Repository
code throws `UserFacingException($key, $params)` for such cases; the controller returns it with
`Response::apiError($e->key, HttpStatus::CONFLICT, [], $e->params)`.

| Status | Meaning | Typical key |
|---|---|---|
| 400 | Body is not valid JSON | `errors.invalid_json` |
| 401 | Not logged in | `auth.login_required` |
| 403 | Logged in but not allowed, or bad CSRF token | `errors.forbidden`, `errors.csrf` |
| 404 | Unknown route or resource | `errors.not_found` |
| 409 | Conflict (event full, locked, registration closed) | feature-specific |
| 422 | Validation failed / wrong credentials | `validation.failed`, `auth.login_failed` |
| 429 | Rate limit hit | `auth.too_many_attempts` (with `params.minutes`), `errors.too_many_requests` |
| 500 | Unexpected error | `errors.server_error` |

## Rate limits

File-based (`src/Core/RateLimiter.php`, counters in `storage/cache/rate-limits/`), keyed by the client IP
(`Request::clientIp()`, which ignores spoofable forwarding headers).

| Where | Limit | When exceeded |
|---|---|---|
| `POST /api/auth/login` | 5 failed logins per IP and user name in 15 minutes; a successful login resets the counter | `429 auth.too_many_attempts` with `params.minutes` (wait time); checked before the password |
| `POST .../{id}/guests` (tournament and social event, non-admins) | 10 registrations per IP and hour | `429 errors.too_many_requests` |
| `POST /api/client-errors` | 20 reports per IP in 10 minutes | report silently dropped, still `204` |

## Honeypot (guest registration)

`GuestRegistrationRequest` and `SocialGuestRequest` have an optional `website` field. The forms render it
hidden; humans leave it empty. If it is not empty the API answers `204` as if it worked and stores nothing.

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

`username` may also be the person's name as "first last" or "last first" (case and extra spaces don't matter).
The exact login name is tried first; a name that matches more than one user never logs in.

`User` = `UserDto` = `{ id, username, firstName, lastName, displayName, isAdmin }`. Responses are DTOs from `src/Api/Dto/`, never hand-built arrays (plan 2 generates TypeScript from them).

## Client error reports

| Method | Path | Body | Response |
|---|---|---|---|
| POST | `/api/client-errors` | `ClientErrorReport` `{ message, stack?, url, appVersion }` | `204`; public; `422 validation.failed`. Sanitised and truncated, then written to the app log as `Client error` (with IP, user id, user agent) |

## Member endpoints

Formats: dates are `YYYY-MM-DD`; date-times are local time (Europe/Zurich) as
`YYYY-MM-DDTHH:MM:SS` without offset. `RegistrationState` is `'none' | 'registered' | 'waitlist'`.
Mutating member endpoints return the fresh detail DTO, so the UI re-renders from one response.
Members act only on their own registration (`/registrations/me`).

### Calendar

| Method | Path | Access | Returns | Errors |
|---|---|---|---|---|
| GET | `/api/calendar/{month}` (`YYYY-MM`) | public (guests see fewer events) | `CalendarDto` | 404 for an invalid month |

### Tournaments (`/api/events`)

| Method | Path | Access | Body | Returns | Errors |
|---|---|---|---|---|---|
| GET | `/{id}` | user | – | `EventDetailDto` | 404 `events.not_found` |
| POST | `/{id}/registrations` | user | `RegisterRequest` | `EventDetailDto` | 404, 409 `events.locked_message` |
| PATCH | `/{id}/registrations/me` | user | `CommentRequest` | `EventDetailDto` | 404, 404 `auth.not_registered`, 409 `events.locked_message` |
| DELETE | `/{id}/registrations/me` | user | – | `EventDetailDto` | 404, 409 `events.locked_message` |
| GET | `/{id}/guest-info` | public | – | `PublicEventDto` | 404 `events.not_found` |
| POST | `/{id}/guests` | public | `GuestRegistrationRequest` | `204` | 404; 409 `events.locked_message` (non-admins); 422 `validation.failed` + `fields` |

`ParticipantDto.handicap` is filled only in flavours with the `handicap` user field (Amigos), otherwise `null`.

Registering on a full event puts the user on the waitlist. Waitlist promotion is first come, first served; in the
Amigos flavour (`waitlistOrder: "priority"`) by `priority` ascending (users without one last), then registration time. Registering again updates the comment.
Locked means manually locked, date passed or registration deadline passed. Guest registration is
rejected on locked events unless the caller is an admin; email and handicap are optional for admins.

### Social events (`/api/social-events`)

| Method | Path | Access | Body | Returns | Errors |
|---|---|---|---|---|---|
| GET | `/{id}` | user | – | `SocialEventDetailDto` | 404 `social_events.not_found` |
| POST | `/{id}/registrations` | user | `SocialRegisterRequest` | `SocialEventDetailDto` | 404; 409 `social_events.locked_message`, `social_events.event_full`, `social_events.table_full`; 422 `validation.failed` with `fields.menuId` / `fields.tableId` = `social_events.invalid_menu` / `invalid_table` |
| DELETE | `/{id}/registrations/me` | user | – | `SocialEventDetailDto` | 404; 409 `social_events.locked_message` |
| GET | `/{id}/guest-info` | public | – | `SocialGuestInfoDto` | 404 `social_events.not_found` |
| POST | `/{id}/guests` | public | `SocialGuestRequest` | `204` | 404; 409 locked (non-admins), full event, full table; 422 validation / invalid menu / invalid table |

Registering when already registered returns the current detail (idempotent). Events without menus
ignore `menuId`.

## Admin endpoints

Admin routes live in separate controllers (`EventAdminController`, `SocialEventAdminController`,
`UserAdminController`) with `CsrfMiddleware` and `AdminMiddleware` at class level, under the same
REST paths as the member API: anonymous gets 401, a member 403. Date-time inputs use
`YYYY-MM-DDTHH:MM` (HTML `datetime-local`); they are stored as `YYYY-MM-DD HH:MM:00`. Booleans
(`male`, `mixed`) must be present in the body (missing: 422 `validation.required`).

**Route order:** routes are sorted by specificity when loaded (literal segments before `{param}`
segments), so declaration order no longer matters.

**`#[RawResponse]`:** marks a route that returns a non-JSON body (CSV export). The TypeScript
generator skips it; the UI links to the URL directly.

### Tournaments (`/api/events`)

| Route | Body | Returns | Notes / errors |
|---|---|---|---|
| `GET /` | – | `EventSummaryDto[]` | all events by date |
| `POST /` | `EventRequest` | `EventAdminDto` | 422 (name, date, capacity >= 1) |
| `GET /{id}/admin` | – | `EventAdminDto` | 404 `events.not_found` |
| `PUT /{id}` | `EventUpdateRequest` | `EventAdminDto` | 404; 422; the date is not editable |
| `DELETE /{id}` | – | 204 | 404 |
| `PUT /{id}/lock`, `DELETE /{id}/lock` | – | `EventAdminDto` | lock / unlock |
| `GET /{id}/export` | – | CSV (`#[RawResponse]`) | CSV file download |
| `POST /{id}/registrations/{userId}` | `RegisterRequest` | `EventAdminDto` | 404 event / `users.not_found`; 409 `events.locked_message` |
| `PATCH /{id}/registrations/{userId}` | `CommentRequest` | `EventAdminDto` | 404 `auth.not_registered`; 409 locked |
| `DELETE /{id}/registrations/{userId}` | – | `EventAdminDto` | 409 locked; promotes from the waitlist |
| `GET /{id}/guests/{guestId}` | – | `AdminGuestDto` | 404 `events.not_found` / `guests.not_found` (also for another event's guest) |
| `PUT /{id}/guests/{guestId}` | `GuestRegistrationRequest` | `AdminGuestDto` | email/handicap optional; 404; 422 |
| `DELETE /{id}/guests/{guestId}` | – | 204 | 404 |
| `POST /bulk/preview` | `BulkRequest` | `BulkPreviewDto` | computes the events, stores nothing; 422 |
| `POST /bulk` | `BulkCreateRequest` | `BulkResultDto` | the client sends the (edited) preview list back; events start locked; 422 per `events.N.field` |

### Social events (`/api/social-events`)

| Route | Body | Returns | Notes / errors |
|---|---|---|---|
| `POST /` | `SocialEventRequest` | `SocialEventAdminDto` | name and date required; 422; `tournamentId` optional |
| `GET /{id}/admin` | – | `SocialEventAdminDto` | 404 `social_events.not_found`; `menus` / `tables` are the comma-separated edit strings |
| `PUT /{id}` | `SocialEventRequest` | `SocialEventAdminDto` | `tournamentId` ignored; 409 with `params` for `social_events.menu_in_use`, `table_in_use`, `table_capacity_too_low` |
| `DELETE /{id}` | – | 204 | 404 |
| `PUT /{id}/lock`, `DELETE /{id}/lock` | – | `SocialEventAdminDto` | lock / unlock |
| `DELETE /{id}/registrations/{registrationId}` | – | `SocialEventAdminDto` | 404 when the registration belongs to another social event |

### Users (`/api/users`)

| Route | Body | Returns | Notes / errors |
|---|---|---|---|
| `GET /` | – | `AdminUserDto[]` | ordered by last name, first name, username |
| `POST /` | `UserCreateRequest` | `AdminUserDto` | 422; taken username: `fields.username = { key: "users.username_taken", params: { username } }` |
| `GET /{id}` | – | `AdminUserDto` | 404 `users.not_found` |
| `PUT /{id}` | `UserUpdateRequest` | `AdminUserDto` | empty/missing `password` keeps the old one; 422 as above (own username is fine) |
| `PUT /{id}/admin`, `DELETE /{id}/admin` | – | `AdminUserDto` | grant / revoke admin |
| `DELETE /{id}` | – | 204 | 409 `errors.forbidden` when an admin deletes themselves |

Optional fields per flavour (`flavor.json` → `features.userFields`): `rfeg`, `memberNumber` (El Faro), `phone`,
`handicap` (−10…54), `priority` (0…99999, lower = first off the waitlist) (Amigos). Fields the flavour doesn't use are
returned as `null` and ignored on save (the stored value is kept). Without `genderSplit`, `male` is optional.
`AdminUserDto.nameConflict` is `true` when another user has the same name or uses it as login name: name login is
then ambiguous for this user (warning only, saving is never blocked).

## Flavour switches (`#[Feature]`)

Routes of features a flavour switches off answer **404 `errors.not_found`** (checked before authentication), as if
they didn't exist. Annotate a controller class or method with `#[Feature('<switch>')]`:

| Switch | Routes |
|---|---|
| `socialEvents` | all of `/api/social-events/*` |
| `guests` | `GET /api/events/{id}/guest-info`, `POST /api/events/{id}/guests`, `GET|PUT|DELETE /api/events/{id}/guests/{guestId}`, the social-event guest routes |

Prices, description, deadline and the men/women split are only hidden in the UI (the API accepts them).

## Testing

Use `$this->json($method, $uri, $data)` in integration tests; it sends the session's CSRF token
automatically (`withCsrf: false` to test rejection). `$this->apiLoginAs($user, $pass)` logs in
through the API. Responses expose `->json()`, `->headers` and `->cookies`.
