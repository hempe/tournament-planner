# Amigos + El Faro: one code base, two flavours — design

Status: **implemented (2026-11-23)** — plan: `docs/superpowers/plans/2026-11-22-amigos-flavor.md`. Deviations: flavour folders in `frontend/flavors/<name>/`, texts merged at runtime, club-neutral base texts, `logo` in flavor.json.

## Background

- `/home/hempe/git/amigos` is the **predecessor** of this app (plain PHP, no git history of interest, Feb 2025),
  downloaded via FTP from Hostpoint. It runs for the group "Amigos de Tauro" with DB `amigoDb`.
- This repo (`feature/ui-refactor`, Angular SPA + PHP 8.3 JSON API) is the Golf El Faro app.
- Goal (user, verbatim): "unify these 2 into this one and having just 2 build configurations for them
  (theming in the amigos is a lot more)".

### What Amigos has (analysed read-only)

- Pages: `index.php` (calendar + login), `event.php` (register, waitlist, admin edit; locked text
  "Liste geschlossen, bitte bei kurzfristigen Änderungen oder Kommentaren Arno anrufen!"), `add-event.php`,
  `register.php` (public self-registration, unfinished — **dropped**), `admin/users.php` (phone/prio/handicap/admin),
  `admin/events.php`, `admin/register.php`, `classes/Calendar.php` (timezone `Atlantic/Canary`), `fixEvent.php`.
- Styling: `styles/style.css` — dark green nav `#425b3b`, joined colours `#b1d4ec` / `#1c84c9`, body `#888a7c`,
  system font; dark mode `@media (prefers-color-scheme: dark) and (max-width:1000px)` with
  `--body_background: url(../background_low_res.png)` and translucent cards. `site.webmanifest` "Amigos de Tauro" /
  "Amigos"; `logo.png`, `logo_full.png`, favicons.
- Schema (`init.sql`, DB `amigoDb`): `users(id, username, password, admin, phone, handicap DECIMAL(3,1), prio INT)`,
  `events(id, name, date, capacity, timestamp, locked)`, `event_users(userId, eventId, comment, state 1=joined/2=waitlist,
  timestamp)`, deprecated `players`, `event_registration`.
- Waitlist promotion orders by `users.prio ASC` (El Faro's `EventRepository::fix()` orders by `timestamp ASC`).
- Logins are `"Lastname Firstname"`.

### Real `amigoDb` (export checked 2026-11-22, structure and counts only)

- Export: `/home/hempe/git/amigos/filiusag_amigoDb.sql` (DB name on the host: `filiusag_amigoDb`). **Contains personal data
  (usernames, password hashes, phones): never print rows into a conversation, never copy it into this repo.** Use it only
  by loading it into a throwaway Docker MariaDB and running the migration plus aggregate checks (counts, NULLs, word counts
  of usernames) — no row dumps.
- Server: **MariaDB 10.11.20, PHP 8.3.37** (same as El Faro's production) → the new code runs there as is.
- Schema equals Amigos `init.sql`, all tables `utf8mb4_unicode_ci`, InnoDB. Keys: `users.username` UNIQUE;
  `event_users` FKs to `users`/`events` with `ON DELETE CASCADE`; deprecated `players` (has its own `handicap`, `prio`,
  UNIQUE `name`, FK to `users`) and `event_registration` (FK to `players`).
- Size: AUTO_INCREMENT users 141, events 235, event_users 2910, players 159, event_registration 2535.

## Decisions so far

- **Scope "minimal first":** for Amigos, social events/dinners, guests, prices/description/deadline and the
  women/men split are **off**, but they are generic switches (could be turned on later).
- **Keep for Amigos:** priority waitlist, phone + handicap, dark mode with photo, "Spieler" wording.
- **Data:** migrate `amigoDb` **in place** with a migration SQL (keep users, passwords, events, registrations,
  priorities, phones, handicaps; drop `players`/`event_registration` after a backup). Test it against the real export
  first (already provided, see "Real `amigoDb`"; take a fresh one right before the cut-over).
- **Approach A (chosen):** flavour at **build time**. User: "I don't want to deploy the logos of both".
  Rejected: one build with runtime flavour (B), two git branches (C).
- **Name login:** allowed for **both** flavours (user: "turn on for both sounds fine").

## Section 1 — Flavour structure (agreed)

```
flavors/
  elfaro/   flavor.json, theme.css, i18n/{de,en,es}.json (only differing keys), assets/ (logo, favicons, manifest)
  amigos/   flavor.json, theme.css (incl. dark mode + photo), i18n/de.json, assets/
```

`flavor.json` (Amigos example; El Faro = today's behaviour, so nothing changes for El Faro):

```json
{
  "name": "Amigos de Tauro", "shortName": "Amigos",
  "timezone": "Atlantic/Canary",
  "usernameOrder": "lastFirst",
  "features": {
    "socialEvents": false, "guests": false, "prices": false,
    "deadline": false, "genderSplit": false,
    "waitlistOrder": "priority",
    "userFields": ["phone", "handicap", "priority"]
  }
}
```

El Faro: all features on, `waitlistOrder: "timestamp"`, `userFields: []`, `usernameOrder: "firstLast"`.

- **Build:** `./build.sh --flavor <name>` — flag **required** (no default that could ship the wrong club). Output
  `dist/<flavor>/1-upload-first` + `2-upload-last`; version string carries the flavour (`2026-12-01-abc123-amigos`).
- **Angular:** one build configuration per flavour (`elfaro`, `amigos`); only that flavour's logo/theme/manifest is in
  the build.
- **Texts:** base `frontend/src/i18n/*.json` + flavour overrides merged at build time into one file per language; PHP and
  Angular read the same merged result. The build fails if an override key does not exist in the base texts.
- **Local dev:** `./run.sh amigos` / `./run.sh elfaro` — **positional argument** (user request), plain `./run.sh` =
  elfaro; unknown name → error listing valid flavours.
- **PHP:** upload contains `config/flavor.json`, read by a small `Flavor` class. Switched-off features are blocked with a
  route attribute like `#[Feature('guests')]` → **404**. Angular hides the matching UI from the same switches.
- **Tests:** see section 5 (each switch tested in both states within one run; builds and E2E per flavour).

## Section 2 — Amigos-specific behaviour (agreed)

- **Priority waitlist** (`waitlistOrder: "priority"`): promotion by `prio` ascending, ties by registration time. The
  waitlist on the tournament page is shown in promotion order. Priority editable in the admin user form (hidden in El Faro).
- **Phone + handicap (as in Amigos today):** phone only in the admin user list/form (Amigos `event.php` selects it but
  never shows it) — never in participant lists. Handicap shown in participant lists (Amigos `event.php` has a Handicap
  column), edited by admins in the user form. Both hidden in El Faro.
- **Locked message:** no setting — Amigos overrides the text in its `i18n/de.json` ("…Arno anrufen!").
- **"Spieler" wording:** via the text overrides.
- **Dark mode with photo:** only in `amigos/theme.css` (phones in dark mode, background photo, translucent cards).
  El Faro stays light-only.
- **Names:** Amigos only has `username`. Migration splits by `usernameOrder`: `lastFirst` → first word = last name,
  rest = first name (El Faro rule `firstLast`: last word = last name, see `UserRepository::splitUsername()`).
  The migration lists every 1-word / 3+-word username so the user can fix multi-part names ("De la Fuente Juan")
  in the user list (see section 3).
  Display/sorting uses stored first/last names; login names stay unchanged.
- **Login by name (both flavours):** (1) exact `username` match first (unchanged); (2) otherwise match
  "first last" **or** "last first" against `first_name`/`last_name`, case-insensitive, whitespace-normalised;
  (3) more than one match → login fails (never guess); rate limit unchanged (key uses the typed name);
  (4) creating/renaming a user warns when the name combination would clash with another user's login or name.
  Today login matches only `username` (`UserRepository.php:201`, `AuthController::login`).
- **Timezone** from `flavor.json`.

## Section 3 — Database migration of `amigoDb` (agreed)

Facts from the export (aggregates only): 111 users (2 admins), 64 events (2025-02-23 … 2027-04-27, 47 future),
636 registrations, **0 duplicate user/event pairs**, no over-full events, no NULL timestamps, **all registrations
state 1** (no waitlist in use today), `prio` 1000–2603 (4 users NULL), 31 users without handicap, **only 2 users with a
phone**, usernames: 102 two words, 7 one word, 2 three+ words, passwords all bcrypt `$2y$` (work with
`password_verify`), deprecated `players`/`event_registration` last written 2025-02-11, every player matches a user.

1. **`database/init.sql` stays the only schema script** (idempotent). Run on `amigoDb` it adds all El Faro
   tables/columns with today's defaults (`male=1`, `mixed=1`, no prices/deadline). Additions:
   - `users`: `phone VARCHAR(255) NULL`, `handicap DECIMAL(4,1) NULL`, `prio INT NULL` (both flavours; El Faro leaves
     them empty). `ADD COLUMN IF NOT EXISTS` plus `MODIFY` so Amigos' `DECIMAL(3,1)` handicap becomes `(4,1)`.
   - `event_users`: `ADD UNIQUE KEY IF NOT EXISTS unique_user_event (userId, eventId)` — `amigoDb` lacks it, El Faro
     code relies on it; the export has no duplicates.
2. **`database/migrations/amigos-legacy.sql`** (one-off, run in phpMyAdmin after a backup):
   - Name split in **plain SQL** (no shell on the host): first word of `username` → `last_name`, rest → `first_name`;
     only for users with empty `first_name`/`last_name` (re-runnable). A one-word username → last name only.
   - A closing `SELECT` lists users whose username has 1 or 3+ words, so the user can check them in phpMyAdmin and
     fix them in the user list. No file with names is generated.
   - `DROP TABLE IF EXISTS event_registration`, then `players`.
3. **Waitlist with NULL priority:** promoted after everyone with a priority; ties by registration time.
4. **`bin/amigos-dry-run.sh <export.sql>`:** loads the export into a throwaway MariaDB container, runs `init.sql` +
   `amigos-legacy.sql`, prints before/after counts (users/events/registrations must be equal) and the split check list
   for the user. Never prints other rows; the export never enters the repo.
5. **PHPUnit migration test:** builds the legacy Amigos schema with made-up data, applies both scripts, asserts counts,
   unique key, name split, dropped tables.
6. Phone is kept (admin-only, cheap) even though only 2 users have one.

## Section 4 — Hosting and cut-over for Amigos (agreed)

Facts: host has PHP 8.3.37 / MariaDB 10.11. Old and new app both store the login in `$_SESSION['user_id']` with the
default session cookie → logged-in users most likely stay logged in. Old manifest has no `start_url` → shortcuts open
`/`. Old `.htaccess` only has the empty Hostpoint panel marker block. Old credentials live in
`private/credential.live.php`; `php.ini` enables `intl`, `pdo_mysql`, `mysqli`.

1. **`.env`** on the Amigos host (copied by hand from `private/credential.live.php`), as for El Faro.
2. **Legacy URL redirects in the shared `.htaccess`** (harmless for El Faro), placed before the rule that 404s
   `*.php`: `event.php?id=N` → `301 /events/N`; `index.php`, `login.php`, `add-event.php`, `admin/*.php` → `301 /`.
3. **Cut-over checklist** (Amigos section in `DEPLOYMENT.md`):
   1. Backup: phpMyAdmin export + FTP download of the old files.
   2. Local dry run with that export (`bin/amigos-dry-run.sh`), counts must match.
   3. Create `.env` (`APP_ENV=production` + DB settings).
   4. phpMyAdmin: run `init.sql`, then `amigos-legacy.sql`; review the name list.
   5. `./build.sh --flavor amigos`, upload both folders.
   6. Delete the old files: `*.php` except `index.php`, `admin/`, `classes/`, `layout/`, `styles/`, `private/`,
      `init.sql`, `o.db.php`, old logo/background images the new theme doesn't use.
   7. Check `/health`, login, an old `event.php?id=` link, the phone shortcut.
   8. Rollback: upload the old files again and import the backup.
4. `php.ini` stays on the server (`*.ini` is blocked by `.htaccess`).

## Section 5 — Tests, CI, docs (agreed)

Replaces "PHPUnit and Vitest per flavour": every switch is tested **in both states within one run**.

- **PHPUnit:** default is El Faro (all on); `Flavor::override([...])` in `setUp()` switches features. Cover:
  `#[Feature]` → 404; priority vs. timestamp promotion (incl. NULL priority); `phone`/`handicap`/`prio` accepted and
  returned only when in `userFields`; login by name incl. ambiguous match; `Flavor` with unknown flavour / broken
  `flavor.json` fails loudly; the migration test (section 3). Coverage stays ≥ 85 %.
- **Vitest:** switches come from an injection token; cover hidden UI (guests, social events, prices), priority field
  in the user form, handicap column, phone only in the admin user list.
- **i18n:** `npm run check:i18n` also checks that every key in `flavors/*/i18n` exists in the base texts.
- **CI:** frontend job builds `elfaro` and `amigos`; `bin/verify-dist.php` fails if a dist contains assets of the other
  flavour (logo, manifest name, theme).
- **E2E:** existing specs stay on El Faro; new `amigos.spec.ts` against an Amigos build (logo/manifest, no guest/social
  UI, guest API 404, priority waitlist order, locked text override, dark mode with photo via `colorScheme: 'dark'` on
  the iPhone project); transition spec checks `event.php?id=N` → `/events/N`.
- **Docs:** `DEPLOYMENT.md` (`--flavor`, two hosts, Amigos cut-over), `README.md` (flavours), `FRONTEND.md` (switches,
  themes, text overrides), `API.md` (`#[Feature]` → 404, new user fields), `CLAUDE.md` + `TESTING.md` (commands
  `./run.sh amigos`, `./build.sh --flavor`, test counts), `docs/WHATS-NEW-AMIGOS.md` (short German note for Amigos
  members: new look, login with first and last name, installable app).
