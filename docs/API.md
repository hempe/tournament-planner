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

`User` = `UserDto` = `{ id, username, firstName, lastName, displayName, isAdmin }`. Responses are DTOs from `src/Api/Dto/`, never hand-built arrays (plan 2 generates TypeScript from them).

## Testing

Use `$this->json($method, $uri, $data)` in integration tests; it sends the session's CSRF token
automatically (`withCsrf: false` to test rejection). `$this->apiLoginAs($user, $pass)` logs in
through the API. Responses expose `->json()`, `->headers` and `->cookies`.
