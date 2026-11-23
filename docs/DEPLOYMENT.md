# Deployment Guide

Tournament Planner is deployed by hand: build on your machine, upload two folders to the web root
(plain file upload, no shell needed on the server). Never upload the source tree.

## Server requirements

- PHP 8.3+ with `pdo_mysql` and `mysqli` (production runs 8.3.37; check the version in the hosting panel before uploading).
- MySQL 8.0+ / MariaDB 10.6+.
- Apache with `mod_rewrite` (required) and `mod_headers` (optional, sets the cache headers; without it the app
  works but browsers may cache `index.html` heuristically, which delays updates). `AllowOverride` must permit
  the `.htaccess` that ships in the build.
- `logs/` and `storage/cache/` writable by PHP.
- HTTPS.

### What the shipped `.htaccess` does

- Only `index.php` is executable: every other `.php` file, dotfiles, `*.ini`, `*.lock`, `*.md`, `*.sh`, `*.log`
  and the folders `src`, `vendor`, `config`, `resources`, `storage`, `logs` answer 404.
- `/api/*` and `/health` go to `index.php`; real files (`app/<version>/*`, icons, manifest, service worker) are served directly.
- A non-GET request to any other URL (a form of a page that was open before the switch) gets `303` to the same
  URL; the Angular app then sends the user to the matching page.
- Everything else serves `index.html` (the Angular routes).
- With `mod_headers`: `/app/*` is `public, max-age=31536000, immutable`; `index.html`, `ngsw.json`,
  `ngsw-worker.js`, `site.webmanifest` and `version.txt` are `no-cache`.
- `/health` is public; anonymous callers only get `{"status":"ok"}` (`"error"` when the database is unreachable).
  Logged-in admins see more details.

### `.env` on the server

The `.env` file lives only on the server and is never part of the build or touched by an upload. It must contain:

```env
APP_ENV=production
DB_HOST=localhost
DB_PORT=3306
DB_NAME=...
DB_USERNAME=...
DB_PASSWORD=...
```

Optional: `APP_TIMEZONE` overrides the flavour's timezone (El Faro `Europe/Zurich`, Amigos `Atlantic/Canary`).
The club (flavour) is **not** set in `.env`: it is baked into the upload (`config/flavor.json`).

`APP_SECURE=true` (secure session cookies) is already the default in production; set `APP_SECURE=false` only if
the site is served without HTTPS. Without `APP_ENV=production` the app runs in development mode.

## Flavours (one code base, two clubs)

The same code runs two sites. Each build is for exactly one club ("flavour", folder `frontend/flavors/<name>/`):

| Flavour | Site | Notes |
|---|---|---|
| `elfaro` | GOLF EL FARO | everything on: dinners, guests, prices, deadlines, men/women lists |
| `amigos` | Amigos de Tauro | no dinners/guests/prices/deadlines; waitlist by priority; phone, handicap, priority per user |

An upload only contains its own club's logo, icons, texts and theme. **Upload each flavour only to its own host**;
the version (`YYYY-MM-DD-<hash>-<flavour>`) and `config/flavor.json` show which one is installed.

## Release procedure

1. **Build** on a clean git tree (commit first; `--dirty` is for experiments only). The flavour is required:

   ```bash
   ./build.sh --flavor elfaro     # or: --flavor amigos
   ```

   It checks that `generated.ts` is current, lints PHP with PHP 8.3 (Docker, if available), builds the Angular app
   into a version folder `app/<version>/` (`<version>` = `YYYY-MM-DD-<git hash>-<flavour>`), assembles the PHP backend with
   production-only Composer packages and a pre-built route cache, verifies the structure and prints the upload
   instructions. It also keeps a copy of step 2 in `releases/<version>/` (git-ignored) for rollback.

2. **Database migrations**: if the release changed the schema, run the new `ALTER` statements from
   `database/init.sql` on the production database first (the old release keeps working with the added columns).

3. **Upload step 1**: the contents of `dist/<flavour>/1-upload-first/` into the web root (merge into the existing
   files; keep `.env` and the existing `app/` folders). This is safe at any time: it adds `app/<version>/` and the
   new PHP code, users still see the old version.

4. **Upload step 2**: the contents of `dist/<flavour>/2-upload-last/` (`index.html`, `ngsw.json`, `version.txt`) into the
   web root. **The new version goes live now.** Always upload this folder last: `index.html` points at the new
   `app/<version>/` files, which must already exist.

5. **Check the version**: open `https://<your-domain>/version.txt` (it must show the new version; the app footer shows
   it too) and click through the calendar and a login. Open apps pick up the release on their next start or when
   the tab becomes visible again and show "Neue Version verfügbar".

6. **Clean up**: in `app/` on the server delete all version folders except the newest 3 (names sort by date). Do not
   delete the version you may want to roll back to.

### Rollback

Upload the contents of `releases/<previous version>/` (from your machine) into the web root. This works as long as
`app/<previous version>/` still exists on the server (hence keeping the newest 3). The build prints the previous
version. The PHP code is rolled back by checking out the old commit, running `./build.sh --flavor <name>` and uploading both folders
again. Database changes are not rolled back (additive migrations are compatible with the old release).

## One-time cut-over checklist (old PHP UI to the Angular app)

Do this once, for the first Angular release.

1. [ ] **Names (optional, recommended):**
   - If you want first/last names filled for older accounts and haven't done so yet: open `/admin/migrate-names`
     on the **current** production (while the old release is still live) and run it once — the page no longer
     exists in the new release.
   - Then (also if names were split earlier) run `database/fix-title-names.sql` in the database tool of the
     hosting panel (e.g. phpMyAdmin): the old migration left a stray dot after titles ("Karl-Uwe, Dr. Bäcker" →
     first name "Karl-Uwe.") and didn't recognise "Mag.". Run the `SELECT` at the top first to see the affected
     rows; the `UPDATE`s only touch rows still in that broken state and can be run more than once.
   - Users without a first name are shown by username and can be edited under Benutzer.
2. [ ] Check the PHP version in the hosting panel: 8.3 or newer.
3. [ ] Check that `.env` on the server contains `APP_ENV=production` and the database settings.
4. [ ] Run the database migrations, if any (`database/init.sql` contains them).
5. [ ] Build (`./build.sh --flavor elfaro`) and deploy as described above (step 1 folder, then step 2 folder).
6. [ ] Check `/version.txt`, `/health` (`{"status":"ok"}`), log in, open the calendar and a tournament.
7. [ ] Delete the legacy files on the server: `styles/`, `src/scripts/`, `src/Views/`, `src/Components/`,
   `src/Layout/`, `resources/lang/`. (Their routes are blocked by `.htaccess` or no longer referenced, but they should not stay.)
8. [ ] Open the app on a phone that still has the old home-screen shortcut and check that it loads the new app.
   Sessions and URLs stay valid; pages that were open during the switch redirect to the matching screen when submitted.

## Amigos de Tauro: one-time cut-over (old plain-PHP app → this app)

Do this once, on the Amigos host (PHP 8.3, MariaDB 10.11 — checked). The old app's database is migrated **in place**:
logins, passwords, events, registrations, priorities, phones and handicaps are kept.

1. [ ] **Backup:** phpMyAdmin → Export of the database (SQL), and download all old files via FTP. Keep both.
2. [ ] **Dry run on your machine** with that export (Docker needed; prints only counts and the name check list):
   `bin/amigos-dry-run.sh /path/to/export.sql`. It must end with `Dry run OK.` (counts before = after).
3. [ ] **`.env`** on the server (see above), with the database settings from the old
   `private/credential.live.php` and `APP_ENV=production`.
4. [ ] **phpMyAdmin → SQL:** run `database/init.sql` (without its first two lines `CREATE DATABASE` / `USE`, the
   database is already selected), then `database/migrations/amigos-legacy.sql`. The last result is a check list of
   one-word and multi-part names (e.g. "De la Fuente Juan"): fix those under *Benutzer* after the switch.
5. [ ] **Build and upload:** `./build.sh --flavor amigos`, then step 1 folder, then step 2 folder (see above).
6. [ ] **Delete the old files** on the server: every `*.php` except `index.php`, the folders `admin/`, `classes/`,
   `layout/`, `styles/`, `private/` (after `.env` works), and `init.sql`, `o.db.php`, `logo.jpg`, `logo_full.png`,
   `logo.png`, `favicon.svg` (not used any more). **Not** `background_low_res.png`, the icons or `site.webmanifest`:
   the upload replaced them with its own copies. Keep `php.ini` (extensions; `.htaccess` blocks it from the web).
7. [ ] **Check:** `/version.txt` ends with `-amigos`, `/health` says ok, log in with the old login **and** with
   "Vorname Nachname", open an old link like `/event.php?id=<id>` (redirects to `/events/<id>`), open the app from a
   phone shortcut, and look at a phone in dark mode (background photo).
8. [ ] **Rollback** (if something is wrong): upload the old files again and import the backup from step 1.

