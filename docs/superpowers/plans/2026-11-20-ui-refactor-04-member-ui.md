# UI Refactor — Plan 4: Member UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every screen a member or guest uses, in Angular, at the same URLs as today: month calendar (`/`), tournament detail (`/events/:id`), social event detail (`/social-events/:id`), and the public guest forms (`/events/:id/guests/new`, `/social-events/:id/guests/new`) — including the dinner prompt after registering and the "tournament only / tournament & dinner" choice when unregistering.

**Architecture:** Feature folders under `frontend/src/app/features/`, each lazy-loaded. Data comes exclusively from the generated clients (`calendarApi`, `eventsApi`, `socialEventsApi`) via `resource()`; mutations return the fresh detail DTO, which is written back with `resource.value.set(dto)`. Registration logic with dialogs lives in one service (`RegistrationFlow`) used by both the calendar and the detail page. The confirm service is generalised to dialogs with arbitrary choices.

**Tech Stack:** Angular 22 (signals, `resource()`, signal forms), Spartan UI (`@ui/*`), Tailwind 4, Vitest + jsdom.

**Spec:** `docs/superpowers/specs/2026-11-20-ui-refactor-design.md` (§4.1, §4.5, §4.6, §5)

**Series:** (1) API foundation ✅, (2) frontend foundation ✅, (3) member API ✅, **(4) member UI**, (5) admin API + UI, (6) build/PWA/E2E/cut-over.

**Decisions (⚠ = differs from today):**
- Calendar URL: `/?month=YYYY-MM`. The legacy `/?date=YYYY-MM-D` is still accepted (old bookmarks/shortcuts) and mapped to its month.
- Calendar on small screens is a day-grouped list; from `md` up a month grid. Same data, two layouts (CSS only).
- Anonymous visitors see tournaments only (as today); a tournament is clickable for them when it is not locked and has free spots, leading to the guest form.
- Members can register straight from the calendar (as today, without a comment) or on the detail page (with a comment).
- ⚠ The header shows "Anlässe" and "Benutzer" only to admins (both lead to admin screens, plan 5). Members navigate via the calendar.
- New translation key `social_events.guest_register_success` (toast after a social-event guest registration).

---

## Conventions for every task

- Inside `frontend/`: `npm test`, `npx ng test --watch=false --include '<glob>'`, `npm run lint`, `npm run check:i18n`, `npx ng build`. All must pass at the end of every task.
- PHP is only touched in Task 1 (translation key); then `composer test` must stay green (`Warnings: 3, Deprecations: 9` expected).
- Use only the generated API functions (`src/app/core/api/generated.ts`) — never hand-written URLs or types. Read `generated.ts` first; function and DTO names below refer to it.
- Component tests: provide a fake `ApiClient` (`{ get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() }` as `useValue`), install translations with `TestBed.inject(I18n).use('de', {...})` containing only the keys the test reads, and fake `SessionStore` with signals when needed (pattern: `core/layout/header.spec.ts`). Submit forms by dispatching `submit` (jsdom quirk). Assert on rendered text and on the fake API calls.
- Every visible string goes through i18n (`| t`, `i18n.t()`, `notify.*('key')`). `npm run check:i18n` verifies keys.
- Mobile first: every screen must work at 375 px width (single column, buttons full width on small screens).
- Commit after each task with the given message; end every message with
  `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`

## File map

| File | Responsibility |
|---|---|
| `src/app/core/feedback/confirm.service.ts` (modify) | `ask()` + new `choose()` with arbitrary actions |
| `src/app/core/feedback/confirm-dialog.ts` (modify) | renders title, message and N actions |
| `src/app/core/format/dates.ts` | parse API dates, format with `Intl` in the active locale |
| `src/app/core/layout/header.ts` (modify) | admin-only links |
| `src/app/features/calendar/month.ts` | pure month helpers (grid, shift, parse legacy `date`) |
| `src/app/features/calendar/calendar.page.ts` | `/` — month navigation, grid + list |
| `src/app/features/calendar/event-card.ts` | one tournament in the calendar |
| `src/app/features/calendar/social-event-card.ts` | one social event in the calendar |
| `src/app/features/events/registration-flow.ts` | register / unregister incl. dialogs, toasts, dinner prompt |
| `src/app/features/events/event-detail.page.ts` | `/events/:id` |
| `src/app/features/social-events/social-event-detail.page.ts` | `/social-events/:id` |
| `src/app/features/guests/guest-registration.page.ts` | `/events/:id/guests/new` |
| `src/app/features/guests/social-guest-registration.page.ts` | `/social-events/:id/guests/new` |
| `src/app/app.routes.ts` (modify) | new routes; `features/home` is deleted |

---

### Task 1: Dialogs with choices, date formatting, header, translation key

**Files:** modify `core/feedback/confirm.service.ts`, `confirm-dialog.ts`, their specs, `core/layout/header.ts` + spec; create `core/format/dates.ts` + spec; add `social_events.guest_register_success` to `src/i18n/{de,en,es}.json`.

- [ ] **Step 1: Translation key** (repo root):

```bash
php -r '
$values = ["de" => "Gast erfolgreich angemeldet.", "en" => "Guest registered successfully.", "es" => "Invitado inscrito correctamente."];
foreach ($values as $l => $text) {
    $f = "frontend/src/i18n/$l.json";
    $d = json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR);
    $d["social_events"]["guest_register_success"] = $text;
    file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n");
}'
vendor/bin/phpunit --filter TranslationValidationTest
```

- [ ] **Step 2: Generalise the confirm service — tests first.** Add to `confirm.service.spec.ts` (keep the existing tests; they must still pass unchanged):

```ts
  it('choose() resolves with the chosen action value or null on cancel', async () => {
    const confirm = TestBed.inject(ConfirmService);

    const choice = confirm.choose({
      title: 'Abmeldung',
      message: 'Von welchem Anlass?',
      actions: [
        { value: 'tournament', label: 'Nur Turnier', danger: true },
        { value: 'both', label: 'Turnier & Abendessen', danger: true },
      ],
    });
    expect(confirm.pending()?.actions.map((a) => a.value)).toEqual(['tournament', 'both']);
    confirm.answer('both');
    await expect(choice).resolves.toBe('both');

    const cancelled = confirm.choose({ message: 'x', actions: [{ value: 'a', label: 'A' }] });
    confirm.answer(null);
    await expect(cancelled).resolves.toBeNull();
  });
```

and update the existing expectation `expect(confirm.pending()).toEqual({ message: 'Turnier abmelden?', danger: true })` to
`expect(confirm.pending()?.message).toBe('Turnier abmelden?')`.

Add to `confirm-dialog.spec.ts`:

```ts
  it('renders custom title and all actions of choose()', async () => {
    const fixture = TestBed.createComponent(ConfirmDialog);
    const confirm = TestBed.inject(ConfirmService);
    await fixture.whenStable();

    const choice = confirm.choose({
      title: 'Abmeldung',
      message: 'Von welchem Anlass möchtest du dich abmelden?',
      actions: [
        { value: 'tournament', label: 'Nur Turnier' },
        { value: 'both', label: 'Turnier & Abendessen' },
      ],
    });
    await fixture.whenStable();

    expect(document.body.textContent).toContain('Abmeldung');
    button('Turnier & Abendessen').click();
    await expect(choice).resolves.toBe('both');
  });
```

Run them → FAIL. Then implement — `confirm.service.ts`:

```ts
import { Injectable, signal } from '@angular/core';

export interface ConfirmAction {
  value: string;
  label: string;
  danger?: boolean;
}

export interface ConfirmRequest {
  /** Already translated. Defaults to the "Bestätigung" title in the dialog. */
  title?: string;
  message: string;
  actions: ConfirmAction[];
}

/**
 * `if (await confirm.ask(i18n.t('…'))) { … }` for yes/no,
 * `await confirm.choose({ message, actions })` for several options (null = cancelled).
 * Rendered by <app-confirm-dialog> in the app shell.
 */
@Injectable({ providedIn: 'root' })
export class ConfirmService {
  private readonly _pending = signal<ConfirmRequest | null>(null);
  readonly pending = this._pending.asReadonly();
  private resolve: ((value: string | null) => void) | null = null;

  /** Yes/no question; the confirm button uses the "actions.confirm" label. */
  async ask(message: string, options: { danger?: boolean; confirmLabel?: string } = {}): Promise<boolean> {
    const answer = await this.choose({
      message,
      actions: [{ value: 'confirm', label: options.confirmLabel ?? '', danger: options.danger }],
    });
    return answer === 'confirm';
  }

  choose(request: ConfirmRequest): Promise<string | null> {
    this.resolve?.(null);
    return new Promise<string | null>((resolve) => {
      this.resolve = resolve;
      this._pending.set(request);
    });
  }

  /** `true`/`false` keep working for ask(); `null` cancels. */
  answer(value: string | boolean | null): void {
    const resolve = this.resolve;
    this.resolve = null;
    this._pending.set(null);
    resolve?.(value === true ? 'confirm' : value === false ? null : value);
  }
}
```

`confirm-dialog.ts` template: title = `pending()?.title || ('calendar.confirmation' | t)`; one `hlmAlertDialogCancel` button (`actions.cancel`, `answer(null)`); then one `hlmAlertDialogAction` per action with `[variant]="action.danger ? 'destructive' : 'default'"`, label `action.label || ('actions.confirm' | t)`, `(click)="confirm.answer(action.value)"`. `(closed)` → `answer(null)`. Footer stacks vertically on small screens (`flex-col-reverse sm:flex-row`). Run → PASS (old and new tests).

- [ ] **Step 3: Date formatting — tests first.** `core/format/dates.spec.ts`:

```ts
import { formatDate, formatDateTime, formatMonth, parseApiDate } from './dates';

describe('dates', () => {
  it('parses API dates and date-times as local time', () => {
    const d = parseApiDate('2099-06-01');
    expect([d.getFullYear(), d.getMonth(), d.getDate(), d.getHours()]).toEqual([2099, 5, 1, 0]);
    const t = parseApiDate('2099-05-30T18:30:00');
    expect([t.getDate(), t.getHours(), t.getMinutes()]).toEqual([30, 18, 30]);
  });

  it('formats with Intl in the given locale', () => {
    expect(formatDate('2099-06-01', 'de')).toBe('Montag, 1. Juni 2099');
    expect(formatDateTime('2099-05-30T18:30:00', 'de')).toBe('Samstag, 30. Mai 2099 um 18:30');
    expect(formatMonth('2099-06', 'de')).toBe('Juni 2099');
    expect(formatMonth('2099-06', 'en')).toBe('June 2099');
  });
});
```

If the exact `Intl` output differs in the test environment (ICU versions vary — e.g. `"um"` vs `","`), assert the stable parts (`toContain('1. Juni 2099')`, `toContain('18:30')`) instead of changing the implementation.

Implement `core/format/dates.ts`:

```ts
/** API dates are 'YYYY-MM-DD', date-times 'YYYY-MM-DDTHH:MM:SS', both local (Europe/Zurich). */
export function parseApiDate(value: string): Date {
  const [datePart, timePart = '00:00:00'] = value.split('T');
  const [y, m, d] = datePart.split('-').map(Number);
  const [hh, mm, ss] = timePart.split(':').map(Number);
  return new Date(y, m - 1, d, hh, mm, ss || 0);
}

export function formatDate(value: string, locale: string): string {
  return new Intl.DateTimeFormat(locale, { dateStyle: 'full' }).format(parseApiDate(value));
}

export function formatDateTime(value: string, locale: string): string {
  return new Intl.DateTimeFormat(locale, { dateStyle: 'full', timeStyle: 'short' }).format(parseApiDate(value));
}

/** 'YYYY-MM' → "Juni 2099" */
export function formatMonth(month: string, locale: string): string {
  return new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(parseApiDate(`${month}-01`));
}
```

- [ ] **Step 4: Header** — change `header.ts` so the `/events` and `/users` links are inside `@if (session.isAdmin())`; update `header.spec.ts`: members see name + "Abmelden" but neither "Anlässe" nor "Benutzer"; admins see both.

- [ ] **Step 5: Verify and commit** — `npm test && npm run lint && npm run check:i18n && npx ng build`; `composer test`.

```bash
git add frontend/src frontend/src/i18n
git commit -m "feat(frontend): Dialogs with choices, date formatting, admin-only nav links"
```

---

### Task 2: Calendar

**Files:** create `features/calendar/month.ts` + spec, `calendar.page.ts` + spec, `event-card.ts`, `social-event-card.ts`; modify `app.routes.ts` (route `''` → `CalendarPage`), delete `features/home/`.

**Month helpers** (`month.ts`, pure — test every function):

```ts
/** 'YYYY-MM' of a date (local). */
export function monthOf(date: Date): string;
/** Shift 'YYYY-MM' by n months ('2099-12', 1 → '2100-01'). */
export function shiftMonth(month: string, delta: number): string;
/** Resolve the calendar month from ?month=YYYY-MM or the legacy ?date=YYYY-MM-D; falls back to `today`. */
export function resolveMonth(month: string | undefined, legacyDate: string | undefined, today: Date): string;
/** Weeks (Monday first) covering the month; days outside the month are null. */
export function monthGrid(month: string): (string | null)[][]; // each day as 'YYYY-MM-DD'
```

Required test cases: `shiftMonth` across year boundaries both ways; `resolveMonth('2099-06', undefined)` → `'2099-06'`; `resolveMonth(undefined, '2099-6-1')` → `'2099-06'`; invalid values fall back to `monthOf(today)`; `monthGrid('2099-06')` (1 June 2099 is a Monday) starts with `'2099-06-01'` in the first cell and has 5 weeks; a month starting on Sunday has leading nulls for Mon–Sat.

**Calendar page** (`/`, no guard):
- Inputs from the query string (component input binding): `month = input<string>()`, `date = input<string>()` (legacy). `current = computed(() => resolveMonth(month(), date(), new Date()))`.
- Data: `calendar = resource({ params: () => ({ month: this.current() }), loader: ({ params, abortSignal }) => calendarApi(api).month(params.month, { signal: abortSignal }) })`.
- Header row: previous / next month buttons (`calendar.previous_month`, `calendar.next_month`, as icon buttons with `aria-label`) navigating to `/?month=…` (`router.navigate([], { queryParams: { month } })`), and the month title (`formatMonth`).
- Loading: `hlm-spinner`; error: the `ApiError.error` key translated.
- Items per day = tournaments + (if logged in) social events of that day, sorted by type then name. Anonymous: tournaments only.
- `md` and up: a 7-column grid (weekday headers via `Intl`, Monday first), each day cell shows its cards. Below `md`: a list of days that have items (date heading + cards). Use the same card components in both.
- `EventCard` (input: `EventSummaryDto`, `loggedIn: boolean`):
  - status text: registered → `events.registered`; waitlist → `events.on_waitlist`; locked → `events.locked`; past date → `events.locked`; `available === 0` → `events.waitlist_available`; `available === 1` → `events.spot_available` with `{count}`; else `events.spots_available` with `{count}`.
  - member: the card links to `/events/{id}`; if `userState === 'none'`, not locked and not past, a small button (`events.register` or `events.waitlist` when full) calls `RegistrationFlow.register(id, '')` (Task 3) and then reloads the calendar resource.
  - anonymous: links to `/events/{id}/guests/new` only when not locked, not past and `available > 0`; otherwise not a link.
  - visual state: registered/waitlist highlighted (primary border), locked/past muted.
- `SocialEventCard` (input: `SocialEventSummaryDto`): links to `/social-events/{id}`; status `social_events.registered` / `social_events.full` / `social_events.available` with `{count}` (when `available !== null`) / nothing; uses the `--social` colour as accent.

Required page tests (fake `ApiClient.get` returning a `CalendarDto`): renders month title and event names for `?month=2099-06`; legacy `date` input `2099-6-1` requests `/calendar/2099-06`; next-month button navigates with `month=2099-07`; anonymous: social events hidden, open tournament links to the guest form, full tournament has no link; member: tournament links to `/events/{id}`, status text for registered/waitlist/spots.

- [ ] Steps: tests first (month helpers, then page) → run, see FAIL → implement → run, PASS → `npm test && npm run lint && npm run check:i18n && npx ng build` → commit:

```bash
git add frontend/src/app
git commit -m "feat(frontend): Month calendar with grid and mobile list"
```

(Task 2 needs `RegistrationFlow` from Task 3 for the quick-register button. Implement Task 3's `RegistrationFlow` first if you work strictly in order, or stub the button until Task 3 and wire it there — the commit of Task 2 must build.)

---

### Task 3: Registration flow and tournament detail

**Files:** create `features/events/registration-flow.ts` + spec, `event-detail.page.ts` + spec; route `events/:id` (`canActivate: [authGuard]`).

**`RegistrationFlow`** (root service; uses `eventsApi`, `socialEventsApi`, `ConfirmService`, `Notify`, `I18n`, `Router`). All methods return the fresh `EventDetailDto`, or `null` when the user cancelled:

```ts
/** Confirm, register, toast; afterwards offer the linked dinner if it is open. */
register(eventId: number, comment: string, event?: { available: number; isLocked: boolean }): Promise<EventDetailDto | null>;
/** Confirm (or ask tournament-only vs. both when also registered for the dinner), unregister, toast. */
unregister(eventId: number, social: SocialEventSummaryDto | null): Promise<EventDetailDto | null>;
/** Confirm, save comment, toast. */
updateComment(eventId: number, comment: string): Promise<EventDetailDto | null>;
```

Behaviour:
- `register`: confirm text `events.join_waitlist` if the event is full (`available <= 0`), else `events.register_confirm`; then `eventsApi.register(id, { comment })`; toast `events.registration_success`; then if the returned `socialEvent` exists, `!userRegistered`, `!isLocked` and (`available === null || available > 0`): `confirm.choose({ message: t('events.social_prompt_message', { name }), actions: [{ value: 'yes', label: t('events.social_prompt_yes') }] })` with cancel label `events.social_prompt_no` — on `'yes'` navigate to `/social-events/{id}`. (Extend `ConfirmRequest` with an optional `cancelLabel` for this; default `actions.cancel`.)
- `unregister`: if `social?.userRegistered`: `choose({ title: t('events.unregister_choice_title'), message: t('events.unregister_choice_message'), actions: [{ value: 'tournament', label: t('events.unregister_tournament_only'), danger: true }, { value: 'both', label: t('events.unregister_both'), danger: true }] })`; on `'both'` first `socialEventsApi.unregister(social.id)` (check the generated name), then the tournament. Otherwise `confirm.ask(t('events.unregister_confirm'), { danger: true })`. Toast `events.unregistration_success`.
- `updateComment`: confirm `events.comment_update_confirm`; toast `events.comment_update_success`.
- `ApiError`s are not caught here (the global handler shows them as toasts), except that the method must not leave the UI in a busy state — callers use `try/finally`.

Required `RegistrationFlow` tests: register asks the right confirm text for full vs. free; cancelled confirm → no API call, returns null; successful register shows the toast and offers the dinner prompt only when eligible (4 cases: no social event, already registered, locked, full); choosing yes navigates; unregister without dinner registration uses `ask`; with dinner registration → `'both'` calls social unregister then tournament unregister in that order; `'tournament'` only the tournament; cancel → no calls.

**`EventDetailPage`** (`/events/:id`, input `id`):
- `detail = resource(...)` via `eventsApi(api).show(id)`; after each flow call that returns a DTO: `this.detail.value.set(dto)`.
- Header: back button to `/?month=<month of event>` (`nav.back`), full date (`formatDate`), name, status badge (`events.registered` / `events.on_waitlist`), and for admins an edit link to `/events/{id}/admin` (`events.edit`).
- Details list (only filled values): `events.description` (keep line breaks: `whitespace-pre-line`), `events.price_members`, `events.price_guests`, `events.registration_close` (`formatDateTime`).
- Own registration card:
  - not registered, not locked: comment input (`events.comment` placeholder) + button `events.register` (or `events.waitlist` if full) → `flow.register`.
  - not registered, locked: text `events.locked_message`.
  - registered: comment input prefilled + save button (`events.save`, disabled when unchanged or locked) → `flow.updateComment`; when locked show `events.locked_message` above it and disable the input; unregister button `events.unregister` (destructive, disabled when locked) → `flow.unregister(id, detail.socialEvent)`.
- Linked social event (if any): a card linking to `/social-events/{id}` with its name (social accent colour).
- Participants card (`events.registered` heading): names; waitlisted ones suffixed with ` (` + `events.waitlist` + `)`.
- Guests card (`guests.title`) when there are guests: "First Last".
- 404 → the not-found message `events.not_found` with a link home.

Required page tests: renders name, prices and participants (waitlist suffix); not registered → comment + register button, click calls `RegistrationFlow.register` with the typed comment (fake the flow); registered → save + unregister buttons, unregister passes the social event; locked → `events.locked_message`, no register button; admin sees the edit link, member doesn't.

- [ ] Steps: tests first → FAIL → implement → PASS → all checks → commit:

```bash
git add frontend/src/app
git commit -m "feat(frontend): Tournament detail with registration flow and dinner prompt"
```

---

### Task 4: Social event detail

**Files:** create `features/social-events/social-event-detail.page.ts` + spec; route `social-events/:id` (`authGuard`).

Behaviour:
- `detail = resource(...)` via the generated social events `show(id)`.
- Header: back to `/?month=…`, date, name; admins: edit link `/social-events/{id}/admin` (`social_events.edit`).
- Details: `social_events.description` (pre-line), `social_events.registration_close`.
- Registration card:
  - registered: `social_events.registered` + menu (`social_events.menu`) and table (`social_events.table_number` with `{number}`, or `social_events.libero`); unregister button (`social_events.unregister`, confirm `social_events.unregister_confirm`, danger) unless locked.
  - not registered & locked: `social_events.locked_message`.
  - not registered & full (`available === 0`): `social_events.full`.
  - otherwise a form (signal forms): menu `<select>` (required when `menus.length > 0`, first option `social_events.select_menu`), table `<select>` (optional, first option `social_events.libero`, then `social_events.table_number` per table, tables with `available === 0` disabled), button `social_events.register` → confirm `social_events.register_confirm` → register → `detail.value.set(dto)`. Field errors from a 422 (`fields.menuId` / `fields.tableId`) appear under the matching select.
- Participants card (`social_events.participants`): table column (`social_events.table`) + name.
- Style selects with Tailwind to match `hlmInput` (border, rounded, height); no new Spartan component needed.

Required tests: registered view shows menu and table; form requires a menu when menus exist (no API call, error shown); submitting calls register with `{ menuId, tableId }` (`tableId: null` for "libero"); a 422 with `fields.menuId` shows the translated message under the menu select; full table option is disabled; locked shows the message and no form.

- [ ] Steps as before; commit:

```bash
git add frontend/src/app
git commit -m "feat(frontend): Social event detail with menu and table registration"
```

---

### Task 5: Guest registration forms

**Files:** create `features/guests/guest-registration.page.ts` + spec, `social-guest-registration.page.ts` + spec; routes `events/:id/guests/new`, `social-events/:id/guests/new` (no guard).

`GuestRegistrationPage`:
- On init, the page needs a CSRF cookie before posting: `SessionStore.refresh()` already ran at startup (it calls `/api/auth/me`, which issues the cookie) — nothing extra to do.
- Loads `guest-info`; shows date, name, description, prices, registration close (as on the detail page).
- Locked (`isLocked`) and the user is not an admin → show `events.locked_message`, no form.
- Form (signal forms): salutation (`users.salutation`; options `users.mr` → `male: true`, `users.mrs` → `false`; required), `guests.first_name`*, `guests.last_name`*, `guests.email` (required unless admin), `guests.handicap` (required unless admin), `guests.comment` (textarea). Required fields marked with `*`.
- Submit → `register(id, body)` → toast `guests.register_success` (it's a long text — fine as toast description) → navigate to `/` (admins: `/events/{id}/admin`).
- 422 → field errors under the inputs (map API field names `firstName`, `lastName`, `email`, `handicap`, `male`, `comment`); 409 → toast via the global handler.

`SocialGuestRegistrationPage`: same structure with `guests.first_name`*, `guests.last_name`*, `guests.email` (required unless admin), menu select (required when menus exist), table select (optional); success toast `social_events.guest_register_success`; navigate to `/` (admins: `/social-events/{id}/admin`).

Required tests (both pages): anonymous sees all required markers; submitting empty shows required errors without API call; valid submit posts the exact body (`{ male: true, firstName, lastName, email, handicap, comment }` / `{ firstName, lastName, email, menuId, tableId }`) and navigates to `/`; 422 field errors are shown; locked event for anonymous shows the locked message; admin on a locked event sees the form and email/handicap are optional.

- [ ] Steps as before; commit:

```bash
git add frontend/src/app
git commit -m "feat(frontend): Public guest registration forms"
```

---

### Task 6: Routes check, docs, manual smoke test

- [ ] **Step 1:** `app.routes.ts` contains, in this order: `''` (calendar), `login` (guestGuard), `events/:id/guests/new`, `events/:id` (authGuard), `social-events/:id/guests/new`, `social-events/:id` (authGuard), `**`. All `loadComponent`. Add a test `app.routes.spec.ts` asserting the paths and guards (import `routes`, check `path` and `canActivate`).
- [ ] **Step 2:** `docs/FRONTEND.md`: add a "Screens" table (URL → component → access) and a short note on `RegistrationFlow` and `ConfirmService.choose()`.
- [ ] **Step 3: Manual smoke test** with `./run.sh` (PHP :5000 + Angular :4200) and a seeded user: open `/` anonymous (tournaments only, guest link), log in, register for a tournament from the detail page with comment, dinner prompt appears when a linked dinner exists, unregister with the choice dialog, open a social event and register with menu/table, open `/?date=2099-6-1` (legacy URL). Note anything that doesn't work in the commit message body or fix it.
- [ ] **Step 4:** all checks (`npm test`, `npm run lint`, `npm run check:i18n`, `npx ng build`, `composer test`, `composer api:check`); commit:

```bash
git add frontend/src/app docs/FRONTEND.md
git commit -m "docs: Document member screens; route table test"
```

## Done criteria for plan 4

- All member/guest URLs from spec §4.1 except admin ones render in Angular with the behaviour above; legacy `/?date=` works.
- Frontend tests cover every page and `RegistrationFlow`; all checks pass; the legacy PHP UI is unchanged.
