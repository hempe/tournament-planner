# Endpoint Reference

Every HTTP endpoint: method, path, auth requirement, accepted POST fields,
validation rules, and expected redirect on success/failure.

Use this as the checklist when writing monkey tests — each POST entry should
have corresponding bad-input coverage in `tests/Integration/Controllers/MonkeyTest.php`.

---

## Auth

| Method | Path | Auth | Fields |
|--------|------|------|--------|
| GET | `/login` | public | — |
| POST | `/login` | public | `username` (required), `password` (required) |
| POST | `/logout` | auth | — |

---

## Events (`/events`)

| Method | Path | Auth | Fields / Validation |
|--------|------|------|---------------------|
| GET | `/events` | auth | — |
| GET | `/events/new` | admin | — |
| POST | `/events/new` | admin | `name` req/str/max:255, `date` req/date, `capacity` req/int/min:1, `mixed` bool; optional: `description`, `price_members`, `price_guests`, `registration_close` |
| GET | `/events/{id}` | auth | — |
| GET | `/events/{id}/admin` | admin | — |
| POST | `/events/{id}` | admin | same as new (update), no `date` field |
| POST | `/events/{id}/delete` | admin | — |
| POST | `/events/{id}/lock` | admin | — |
| POST | `/events/{id}/unlock` | admin | — |
| GET | `/events/{id}/export` | admin | — |
| POST | `/events/{id}/register` | auth | `comment` (optional), `userId` (optional, admin can register others) |
| POST | `/events/{id}/unregister` | auth | `userId` (optional) |
| POST | `/events/{id}/comment` | auth | `comment`, `userId` (optional) |
| GET | `/events/bulk/new` | admin | — |
| POST | `/events/bulk/preview` | admin | `start_date` req/date, `end_date` req/date, `day_of_week` req/int/min:0/max:6, `name` req/str/max:255, `capacity` req/int/min:1, `mixed` bool; optional: `price_members`, `price_guests`, `registration_close_days`, `registration_close_time` |
| POST | `/events/bulk/store` | admin | reads from `$_SESSION['bulk_events']` (set by bulk/preview) |

**On validation failure:** `flash_input()` + `flash('error')` + redirect to form  
**On success:** redirect to event detail or `/events`

---

## Social Events (`/social-events`)

| Method | Path | Auth | Fields / Validation |
|--------|------|------|---------------------|
| GET | `/social-events/new` | admin | query: `tournamentId` (optional) |
| POST | `/social-events/new` | admin | `name` req/str/max:255, `date` req/date, `menus` req/str, `tables` req/str; optional: `description`, `registration_close`, `tournamentId` |
| GET | `/social-events/{id}` | auth | — |
| GET | `/social-events/{id}/admin` | admin | — |
| POST | `/social-events/{id}` | admin | same fields as new (update) |
| POST | `/social-events/{id}/delete` | admin | — |
| POST | `/social-events/{id}/lock` | admin | — |
| POST | `/social-events/{id}/unlock` | admin | — |
| POST | `/social-events/{id}/register` | auth | `menu_id` (required, must belong to event), `table_id` (optional, must belong to event) |
| POST | `/social-events/{id}/unregister` | auth | — |
| GET | `/social-events/{id}/guests/new` | public | — |
| POST | `/social-events/{id}/guests/new` | public | `first_name` req/str/max:255, `last_name` req/str/max:255, `email` (req for non-admin)/email/max:255, `menu_id` req/int; optional: `table_id` |
| POST | `/social-events/{id}/registrations/{regId}/delete` | admin | — |

**On validation failure:** `flash_input()` + `flash('error')` + redirect to form  
**menu_id / table_id ownership** checked via `menuBelongsToEvent()` / `tableBelongsToEvent()`

---

## Users (`/users`)

All routes require **admin** (AuthMiddleware + AdminMiddleware on class).

| Method | Path | Fields / Validation |
|--------|------|---------------------|
| GET | `/users` | — |
| GET | `/users/new` | — |
| POST | `/users` | `male` req/bool, `username` req/str/min:3/max:255, `password` req/str; optional: `rfeg`, `member_number`, `first_name`, `last_name` |
| GET | `/users/{id}/edit` | — |
| POST | `/users/{id}` | `male` req/bool, `username` req/str/min:3/max:255; optional: `rfeg`, `member_number`, `first_name`, `last_name` |
| POST | `/users/{id}/delete` | — |
| POST | `/users/{id}/set-password` | `password` req/str, `password_confirm` req/str (must match) |
| POST | `/users/{id}/set-admin` | `admin` (bool) |

**On validation failure:** `flash_input()` + `flash('error')` + redirect to form  
**Username uniqueness** checked before insert; 303 + `flash_input` if taken

---

## Guests (`/events/{id}/guests`)

| Method | Path | Auth | Fields / Validation |
|--------|------|------|---------------------|
| GET | `/events/{id}/guests/new` | admin | — |
| POST | `/events/{id}/guests/new` | admin | `first_name` req/str/max:255, `last_name` req/str/max:255, `email` email/max:255, `handicap` optional, `comment` optional, `male` bool |
| GET | `/events/{id}/guests/{guestId}/edit` | admin | — |
| POST | `/events/{id}/guests/{guestId}` | admin | same as new |
| POST | `/events/{id}/guests/{guestId}/delete` | admin | — |

---

## Admin (`/admin`)

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| GET | `/admin/migrate-names` | admin | Shows pending count; safe if columns missing (catches exception) |
| POST | `/admin/migrate-names` | admin | Runs `ALTER TABLE … ADD COLUMN IF NOT EXISTS` then seeds first/last names from usernames |

---

## Security Notes

- All POST forms include a CSRF token (`_token` hidden input, validated by middleware)
- All user-controlled strings are output through `htmlspecialchars()` at callsite before passing to components
- `Icon`, `IconButton`: escape `title` attribute internally
- `InputAction`: escapes `inputValue` and `inputPlaceholder` internally
- `Input`: escapes `value` internally
- Content components (`Span`, `Div`, `Card`, `Table` cells): accept **raw HTML** — callers must `htmlspecialchars()` user strings before passing
- SQL injection: all DB queries use prepared statements with `?` placeholders
