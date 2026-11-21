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

`APP_SECURE=true` (secure session cookies) is already the default in production; set `APP_SECURE=false` only if
the site is served without HTTPS. Without `APP_ENV=production` the app runs in development mode.

## Release procedure

1. **Build** on a clean git tree (commit first; `--dirty` is for experiments only):

   ```bash
   ./build.sh
   ```

   It checks that `generated.ts` is current, lints PHP with PHP 8.3 (Docker, if available), builds the Angular app
   into a version folder `app/<version>/` (`<version>` = `YYYY-MM-DD-<git hash>`), assembles the PHP backend with
   production-only Composer packages and a pre-built route cache, verifies the structure and prints the upload
   instructions. It also keeps a copy of step 2 in `releases/<version>/` (git-ignored) for rollback.

2. **Database migrations**: if the release changed the schema, run the new `ALTER` statements from
   `database/init.sql` on the production database first (the old release keeps working with the added columns).

3. **Upload step 1**: the contents of `dist/1-upload-first/` into the web root (merge into the existing
   files; keep `.env` and the existing `app/` folders). This is safe at any time: it adds `app/<version>/` and the
   new PHP code, users still see the old version.

4. **Upload step 2**: the contents of `dist/2-upload-last/` (`index.html`, `ngsw.json`, `version.txt`) into the
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
version. The PHP code is rolled back by checking out the old commit, running `./build.sh` and uploading both folders
again. Database changes are not rolled back (additive migrations are compatible with the old release).

## One-time cut-over checklist (old PHP UI to the Angular app)

Do this once, for the first Angular release.

1. [ ] **Optional**: to fill first/last names of older accounts from their usernames, open
   `/admin/migrate-names` on the **current** production (while the old release is still live) and run it once before
   uploading the new release (the page no longer exists afterwards). Users without a first name are shown by username
   and can be edited under Benutzer.
2. [ ] Check the PHP version in the hosting panel: 8.3 or newer.
3. [ ] Check that `.env` on the server contains `APP_ENV=production` and the database settings.
4. [ ] Run the database migrations, if any (`database/init.sql` contains them).
5. [ ] Build (`./build.sh`) and deploy as described above (step 1 folder, then step 2 folder).
6. [ ] Check `/version.txt`, `/health` (`{"status":"ok"}`), log in, open the calendar and a tournament.
7. [ ] Delete the legacy files on the server: `styles/`, `src/scripts/`, `src/Views/`, `src/Components/`,
   `src/Layout/`, `resources/lang/`. (Their routes are blocked by `.htaccess` or no longer referenced, but they should not stay.)
8. [ ] Open the app on a phone that still has the old home-screen shortcut and check that it loads the new app.
   Sessions and URLs stay valid; pages that were open during the switch redirect to the matching screen when submitted.
