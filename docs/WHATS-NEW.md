# What's new — the Angular release (2026)

The tournament planner was rebuilt: the pages that PHP used to render on the server are now an
Angular app (an installable web app, "PWA"), and PHP answers it as a JSON API. Addresses,
logins and data stay the same. This document lists what changes for **members**, **guests** and
**admins**, what changed **behind the scenes**, and **ideas for later**.

For the switch itself see [DEPLOYMENT.md](DEPLOYMENT.md) (checklist "One-time cut-over").

---

## For members

### What stays the same
- Same address, same login, same bookmarks and home-screen shortcuts — old links like
  `/events/12` or `/?date=2026-6-1` still open the right page.
- Club branding: logo, club name, colours and the Hind Guntur font.
- The language follows the browser (German, English, Spanish).
- Registering, changing the comment, unregistering, the waitlist, and the question
  "Möchtest du auch am Abendessen teilnehmen?" after registering.

### What's new or better
- **Faster:** registering, unregistering or saving a comment no longer reloads the page.
- **Calendar colours as before, with icons:**
  - light green ✓ = registered, light green ⏳ = waitlist
  - dark green ＋ = registration open (registering works straight from the calendar)
  - light blue ✓ / dark blue ＋ = dinner registered / open
  - grey 🔒 = closed (locked, deadline or date passed)
- **Desktop: "Liste" or "Monat".**
  - **List view:** one block per date, with a capacity bar ("32 von 60"), a small month
    calendar (click a day to jump to it) and **"Meine nächsten Anmeldungen"**.
  - **Month view:** the familiar grid; today is circled.
  - The choice is remembered in the browser and in the link (`?view=list`).
- **Tournament page:**
  - "Deine Anmeldung" with the comment and the buttons in one place.
  - The linked dinner as a compact row with its status.
  - Participants grouped as "Angemeldet (16) / Warteliste (5) / Gäste (1)"; your own entry is
    highlighted.
- **Clearer wording:**
  - "Turnier abmelden" / "Anlass abmelden" instead of "Abmelden" (which looked like the logout).
  - Precise confirmation questions ("Möchtest du dich wirklich von diesem Turnier abmelden?").
- **Dinner page:**
  - Menu and table as clear fields; full tables can't be picked.
  - Participants show their table, or "Kein Wunsch".
- **Phones:**
  - Every screen is built for phones first.
  - The account bar sits at the top in club green, like www.golfelfaro.es.
  - Dropdowns open the phone's own picker.
  - Extra space at the bottom, so the browser bar never covers the last entries.
- **Installable app:**
  - The page can be added to the home screen and starts like an app.
  - After an update, the message "Neue Version verfügbar – Neu laden" appears.
- **No connection?** A message with **"Nochmal versuchen"** appears instead of an empty page.

## For guests (without login)

- The calendar shows the tournaments; open ones (dark green) lead straight to the guest form.
- The guest form shows date, description, prices and the registration deadline.
- **New:** once an event is closed (locked or deadline passed), the guest form shows that it is
  closed. Before, the server still accepted guest registrations for closed events.

## For admins

- **Tabs** "Startseite | Anlässe | Benutzer" in the header.
- **Anlässe:**
  - **Order:** upcoming events sorted by date; past events collapsed below.
  - **Lock:** padlock button per row, one click to lock or unlock. A small padlock marks closed
    events and tells a manual lock apart from a passed deadline.
  - **Delete:** a quiet bin icon, still with a confirmation.
- **Event administration:**
  - **Header:** lock, export (CSV) and delete in the page header.
  - **"Mitglied hinzufügen":** a search field instead of a list of all 300+ members.
  - **Registrations:** compact, grouped (and split by women/men when the event isn't mixed);
    edit a comment inline with ✎; unregister with an icon.
  - **Guests:** compact rows with edit and delete.
  - **Prices:** free text ("35.00 + Greenfee", "gratis").
- **Mehrfache Termine (bulk):** form → **preview** of all dates → create. The description can be
  set for the whole series.
- **Calendar:**
  - "＋" per day in the month view.
  - In the list view: click a day in the small month → "Anlass am … hinzufügen".
- **Gesellschaftliche Anlässe:**
  - Create one from a tournament.
  - Edit menus and tables as before; clear messages when a menu or table can't be removed
    ("…ist noch in Verwendung").
  - Remove participants; add guests.
- **Benutzer:**
  - A search field.
  - Compact rows with edit, admin rights and delete.
  - **An admin can no longer delete their own account** (to avoid locking everyone out).
- **Admins may still register guests for closed events** (members and guests can't).

## Behind the scenes (maintainer)

### Security
- **CSRF:** protection against forged requests was never checked before; it is now enforced
  and tested.
- **Cookies:** session and token cookies get the `Secure` flag in production (`APP_SECURE`).
- **Blocked paths:** `php.ini`, `.env`, `logs/`, `src/`, `vendor/` and every PHP file except
  `index.php` are no longer reachable from the web.
- **`/health`:** shows only `{"status":"ok"}` publicly; PHP version and details only for admins.

### Bugs fixed on the way
- **Dinners:** deleting a dinner failed as soon as someone had chosen a menu.
- **Names:** the old name migration left "Karl-Uwe**.**" after ", Dr." and didn't know "Mag."
  — rule fixed. Repair script for production: `database/fix-title-names.sql`.
- **Dead code:** two duplicate PHP type declarations that, depending on load order, could break
  every redirect.
- **Validation:** error texts for numbers said "…Zeichen" (characters).

### Deployment
- **Build:** `./build.sh` refuses uncommitted changes and writes the version
  (`YYYY-MM-DD-<commit>`); the version is shown in the footer and at `/version.txt`.
- **Upload:** by hand in two steps (`1-upload-first`, then `2-upload-last`), so users never see
  a half-uploaded version.
- **Rollback:** re-upload `releases/<version>/` (three small files).
- **PHP:** 8.3 required (production runs 8.3.37); the build checks the syntax with PHP 8.3.

### Quality
- **PHP:** 425 API/model tests, line coverage 91 %.
- **Frontend:** 292 tests.
- **End to end:** 58 tests in a real browser against Apache + PHP 8.3 + MariaDB (desktop and
  iPhone).
- **API types:** the TypeScript client is **generated from the PHP code**; a test fails when
  they drift apart.

---

## Ideas for later

Roughly by value for the club; none of this is started.

### Members
1. **Email notifications**
   - Confirmation after registering.
   - Above all: **"Du bist von der Warteliste nachgerückt"**. Today nobody notices when a place
     becomes free.
2. **"Mein Konto"**
   - Change your own password and name.
   - "Forgot password" by email, instead of asking an admin.
3. **Calendar subscription (iCal)**
   - Your own registrations appear automatically in Google, Apple or Outlook calendars.
4. **Start times**
   - For Saturdays with fixed start times (9:16, 9:24, …), pick or assign the time in the app
     instead of writing it in the comment.
   - Afterwards, a start list for the club house.
5. **Push notifications** (the app is installable already)
   - e.g. "Anmeldeschluss morgen 12:30" or "Platz frei geworden".
6. **Waitlist position** ("Du bist Nr. 3 auf der Warteliste").
7. **Language switch** in the app, in addition to the browser language.

### Admins
1. **Dinner export:** CSV/print list of dinner registrations (menu and table plan for the
   restaurant).
2. **Edit series:** e.g. change the price of all future Thursdays at once; **duplicate** an
   event.
3. **Activity log:** who registered or unregistered whom, and when (helps with "I was
   registered!").
4. **Overview page:** registrations of the coming weeks, full events, open waitlists.

### Technical (all done)
1. ✅ **Spam protection for guest registration:** hidden honeypot field plus a limit of 10
   registrations per hour and IP.
2. ✅ **Login rate limit:** 5 failed attempts per IP and user name block further attempts for
   15 minutes.
3. ✅ **Hind Guntur font hosted with the app**; no request goes to Google any more.
4. ✅ **CI on GitHub:** PHP tests and frontend checks on every push; the end-to-end suite can be
   started by hand. Deploying stays manual.
5. ✅ **Error reporting:** unexpected frontend errors are written to the server log
   (`POST /api/client-errors`).
6. ✅ **Small clean-ups:** "Gemischt" label, unused PHP helpers removed.
