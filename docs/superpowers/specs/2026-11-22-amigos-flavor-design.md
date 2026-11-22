# Amigos + El Faro: one code base, two flavours — design (DRAFT, brainstorming in progress)

Status: **sections 1–2 agreed with the user; next: present section 3 (migration), then 4 and 5.**
Continue the brainstorming flow (present remaining sections → finalize this spec → user review → writing-plans).

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
  priorities, phones, handicaps; drop `players`/`event_registration` after a backup). Test it against a real export
  first — **the user must provide a phpMyAdmin SQL export of amigoDb** at implementation time.
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
- **Tests:** PHPUnit and Vitest run per flavour in CI; E2E covers the Amigos-specific parts.

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
  The migration prints a list of every split so the user can fix multi-part names ("De la Fuente Juan") in the user list.
  Display/sorting uses stored first/last names; login names stay unchanged.
- **Login by name (both flavours):** (1) exact `username` match first (unchanged); (2) otherwise match
  "first last" **or** "last first" against `first_name`/`last_name`, case-insensitive, whitespace-normalised;
  (3) more than one match → login fails (never guess); rate limit unchanged (key uses the typed name);
  (4) creating/renaming a user warns when the name combination would clash with another user's login or name.
  Today login matches only `username` (`UserRepository.php:201`, `AuthController::login`).
- **Timezone** from `flavor.json`.

## Still to present / decide

1. Section 3 — **database migration** of `amigoDb` (schema diff Amigos → unified `init.sql`: add El Faro columns such as
   `first_name`, `last_name`, `male`, `rfeg`, `member_number`, event `description`/prices/`registration_close`/`mixed`,
   social tables; add `phone`, `handicap`, `prio` to the unified schema for both; map `event_users`; backup + drop
   deprecated tables; dry run against the export; name-split report).
2. Section 4 — hosting: PHP 8.3.37 / MariaDB 10.11 **confirmed** from the export header; `.env` per host;
   cut-over steps for Amigos (old plain-PHP files replaced; old URLs like `event.php?id=` → legacy redirects?).
3. Section 5 — testing/CI per flavour, docs (`DEPLOYMENT.md` per flavour, `README.md`, `WHATS-NEW`-style note for Amigos).
