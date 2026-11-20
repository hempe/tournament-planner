# UI Refactor — Plan 3: Member API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** JSON endpoints for everything a member or guest does: month calendar, tournament detail with register / change comment / unregister, social event detail with menu+table registration, and the two public guest-registration forms — all returning DTOs so the TypeScript client is generated.

**Architecture:** Three new API controllers in `src/Controllers/Api/` (`CalendarController`, `EventsController`, `SocialEventsController`) on top of the existing repositories (business rules unchanged). Responses are DTOs in `src/Api/Dto/` with static `from…()` factories. After each controller, `composer api:generate` refreshes `frontend/src/app/core/api/generated.ts`.

**Tech Stack:** PHP 8.5, PHPUnit 10.5, existing `EventRepository` / `SocialEventRepository` / `GuestRepository`.

**Spec:** `docs/superpowers/specs/2026-11-20-ui-refactor-design.md` (§4.1, §4.2, §4.7)

**Series (revised):** (1) API foundation ✅, (2) frontend foundation ✅, **(3) member API**, (4) member UI, (5) admin API + UI, (6) build/PWA/E2E/cut-over. Plan 3 was split because API and screens are each a full plan.

**Decisions made in this plan (deviations from today's behaviour are marked ⚠):**
- Calendar month is a path parameter: `GET /api/calendar/{month}` with `YYYY-MM` (the generator does not support query parameters, and none are needed).
- Date-times in DTOs are local time (Europe/Zurich) as `YYYY-MM-DDTHH:MM:SS` without offset; dates are `YYYY-MM-DD`.
- Registration state is an enum `RegistrationState` (`'none' | 'registered' | 'waitlist'`) instead of `0/1/2`.
- Mutating member endpoints return the fresh detail DTO, so the UI re-renders from one response.
- Members act only on their own registration (`/registrations/me`). Admin actions on other users come in plan 5.
- ⚠ Guest registration (`POST /api/events/{id}/guests`, `POST /api/social-events/{id}/guests`) is rejected with `409 events.locked_message` / `409 social_events.locked_message` when the event is locked or past its registration deadline — unless the caller is an admin. Today only the calendar hides the button; the server accepts it.
- Social event registration when already registered returns the current detail (idempotent) instead of a silent redirect.

---

## Conventions for every task

- `composer test` baseline at the start of this plan: run `composer test 2>&1 | grep '^Tests:'` once and note the number; the summary must keep `Warnings: 3, Deprecations: 9`.
- API tests use the plan-1 harness: `$this->json($method, $uri, $data)` (sends the CSRF token), `$this->apiLoginAs($user, $pass)`, `$response->json()`. Call `$this->json('GET', '/api/auth/me')` once before the first non-GET request in a test if not logged in through `apiLoginAs` (it issues the CSRF token).
- After a task that adds or changes DTOs/routes: `composer api:generate`, and in `frontend/`: `npx ng build` must still succeed; commit the regenerated `generated.ts` with the task.
- Every API controller: `#[Middleware(CsrfMiddleware::class)]` at class level; every route: `#[Returns(...)]` (omit only for 204) and `#[Body(...)]` when it reads a body.
- **Route order matters:** routes are matched in declaration order. Declare literal segments (`/me`) before parameter segments (`/{userId}`).
- Commit after each task with the given message; end every message with
  `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`

## File map

| File | Responsibility |
|---|---|
| `src/Api/Dto/RegistrationState.php` | enum + mapping from DB ints |
| `src/Api/Dto/ApiDate.php` | DB date-time → API string |
| `src/Api/Dto/EventSummaryDto.php`, `EventDetailDto.php`, `MyRegistrationDto.php`, `ParticipantDto.php`, `GuestNameDto.php`, `PublicEventDto.php` | tournament DTOs |
| `src/Api/Dto/SocialEventSummaryDto.php`, `SocialEventDetailDto.php`, `MenuDto.php`, `TableDto.php`, `SocialRegistrationDto.php`, `SocialParticipantDto.php`, `SocialGuestInfoDto.php` | social event DTOs |
| `src/Api/Dto/CalendarDto.php` | month view |
| `src/Api/Dto/RegisterRequest.php`, `CommentRequest.php`, `SocialRegisterRequest.php`, `GuestRegistrationRequest.php`, `SocialGuestRequest.php` | request bodies |
| `src/Controllers/Api/CalendarController.php` | `GET /api/calendar/{month}` |
| `src/Controllers/Api/EventsController.php` | tournament detail, registration, comment, guests |
| `src/Controllers/Api/SocialEventsController.php` | social event detail, registration, guests |
| `tests/Integration/Api/*Test.php` | endpoint tests |
| `docs/API.md` | endpoint reference |

## Test fixtures used throughout

Create users with `DB::$users->create('jane', 'Pass123!', firstName: 'Jane', lastName: 'Doe')` (returns the id). Create events with `DB::$events->add('Cup', '2099-06-01', 2)` (name, date, capacity; returns id). Locked event: `DB::$events->lock($id)`. Past registration deadline: `DB::$events->add('Closed', '2099-06-01', 10, false, true, null, null, null, '2000-01-01 00:00:00')`. Social event: `DB::$socialEvents->add(...)` — check its signature in `src/Models/SocialEventRepository.php` (`add(`, around line 99) before writing the social tests; menus and tables are passed as comma-separated strings (e.g. `'Fisch,Fleisch'`, `'4,6'`). Every test class calls `$this->cleanDatabase()` in `setUp()`.

---

### Task 1: DTOs

**Files:** Create every DTO listed in the file map under `src/Api/Dto/`. Test: `tests/Integration/Api/MemberDtoTest.php`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use PHPUnit\Framework\TestCase;
use TP\Api\Dto\ApiDate;
use TP\Api\Dto\EventSummaryDto;
use TP\Api\Dto\RegistrationState;
use TP\Api\Dto\SocialEventSummaryDto;
use TP\Api\Dto\TableDto;
use TP\Models\Event;
use TP\Models\SocialEvent;
use TP\Models\SocialTable;

class MemberDtoTest extends TestCase
{
    public function testRegistrationStateFromDatabaseInt(): void
    {
        $this->assertSame(RegistrationState::None, RegistrationState::fromDb(0));
        $this->assertSame(RegistrationState::Registered, RegistrationState::fromDb(1));
        $this->assertSame(RegistrationState::Waitlist, RegistrationState::fromDb(2));
        $this->assertSame('waitlist', RegistrationState::Waitlist->value);
    }

    public function testApiDate(): void
    {
        $this->assertSame('2099-05-01T18:00:00', ApiDate::dateTime('2099-05-01 18:00:00'));
        $this->assertNull(ApiDate::dateTime(null));
        $this->assertSame('2099-05-01', ApiDate::date('2099-05-01 00:00:00'));
        $this->assertSame('2099-05-01', ApiDate::date('2099-05-01'));
    }

    public function testEventSummaryFromEvent(): void
    {
        $event = new Event(5, false, '2099-06-01', 'Cup', 20, 18, 3, 2, true, 'desc', 'CHF 50', null, '2099-05-30 12:00:00');

        $dto = EventSummaryDto::fromEvent($event);

        $this->assertSame(
            [
                'id' => 5, 'name' => 'Cup', 'date' => '2099-06-01', 'capacity' => 20, 'joined' => 18,
                'waitlist' => 3, 'available' => 2, 'isLocked' => false, 'userState' => RegistrationState::Waitlist,
            ],
            get_object_vars($dto)
        );
    }

    public function testSocialEventSummaryFromSocialEvent(): void
    {
        $social = new SocialEvent(3, 5, 'Dinner', '2099-06-01', null, null, false, 10, 4, 1);

        $dto = SocialEventSummaryDto::fromSocialEvent($social);

        $this->assertSame(3, $dto->id);
        $this->assertSame(5, $dto->tournamentId);
        $this->assertSame(6, $dto->available);
        $this->assertTrue($dto->userRegistered);
    }

    public function testTableDtoFromSocialTable(): void
    {
        $dto = TableDto::fromTable(new SocialTable(9, 3, 2, 6, 4));

        $this->assertSame(['id' => 9, 'number' => 2, 'capacity' => 6, 'available' => 2], get_object_vars($dto));
    }
}
```

Before running: open `src/Models/SocialTable.php` and confirm the constructor order is `(id, socialEventId, number, capacity, registered)` with a computed `available`; adjust the test's constructor call if it differs.

- [ ] **Step 2: Run to verify it fails** — `vendor/bin/phpunit --filter MemberDtoTest` → FAIL (classes missing).

- [ ] **Step 3: Implement the DTOs** — all are `final readonly class` with constructor-promoted public properties in exactly this order (the order defines the generated TS interface):

`RegistrationState.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Api\Dto;

enum RegistrationState: string
{
    case None = 'none';
    case Registered = 'registered';
    case Waitlist = 'waitlist';

    /** event_users.state: 0 = not registered, 1 = registered, 2 = waitlist */
    public static function fromDb(int $state): self
    {
        return match ($state) {
            1 => self::Registered,
            2 => self::Waitlist,
            default => self::None,
        };
    }
}
```

`ApiDate.php` (a helper, not a DTO — no public properties):

```php
<?php

declare(strict_types=1);

namespace TP\Api\Dto;

/** Dates go out as YYYY-MM-DD, date-times as local YYYY-MM-DDTHH:MM:SS (Europe/Zurich, no offset). */
final class ApiDate
{
    public static function date(string $value): string
    {
        return substr($value, 0, 10);
    }

    public static function dateTime(?string $value): ?string
    {
        return $value === null ? null : str_replace(' ', 'T', substr($value, 0, 19));
    }
}
```

Remaining DTOs (properties in this order; factories as shown):

| Class | Properties | Factory |
|---|---|---|
| `EventSummaryDto` | `int $id, string $name, string $date, int $capacity, int $joined, int $waitlist, int $available, bool $isLocked, RegistrationState $userState` | `fromEvent(Event $e)`: `date` via `ApiDate::date`, `waitlist` = `$e->onWaitList`, `userState` = `RegistrationState::fromDb($e->userState)` |
| `MyRegistrationDto` | `RegistrationState $state, string $comment` | `fromRegistration(EventRegistration $r)` |
| `ParticipantDto` | `string $name, RegistrationState $state` | `fromRegistration(EventRegistration $r)` |
| `GuestNameDto` | `string $firstName, string $lastName` | `fromGuest(EventGuest $g)` |
| `SocialEventSummaryDto` | `int $id, ?int $tournamentId, string $name, string $date, bool $isLocked, ?int $totalCapacity, int $registered, ?int $available, bool $userRegistered` | `fromSocialEvent(SocialEvent $s)` (`userRegistered` = `$s->userRegistered === 1`, `date` via `ApiDate::date`) |
| `EventDetailDto` | `EventSummaryDto $event, ?string $description, ?string $priceMembers, ?string $priceGuests, ?string $registrationClose, ?MyRegistrationDto $myRegistration, #[ArrayOf(ParticipantDto::class)] array $participants, #[ArrayOf(GuestNameDto::class)] array $guests, ?SocialEventSummaryDto $socialEvent` | none (built by the controller) |
| `PublicEventDto` | `int $id, string $name, string $date, ?string $description, ?string $priceMembers, ?string $priceGuests, ?string $registrationClose, bool $isLocked` | `fromEvent(Event $e)` |
| `MenuDto` | `int $id, string $name` | `fromMenu(SocialMenu $m)` |
| `TableDto` | `int $id, int $number, int $capacity, int $available` | `fromTable(SocialTable $t)` |
| `SocialRegistrationDto` | `?string $menuName, ?int $tableNumber` | `fromRegistration(SocialRegistration $r)` |
| `SocialParticipantDto` | `string $name, ?int $tableNumber` | `fromRegistration(SocialRegistration $r)` (`name` = `$r->displayName`) |
| `SocialEventDetailDto` | `SocialEventSummaryDto $event, ?string $description, ?string $registrationClose, #[ArrayOf(MenuDto::class)] array $menus, #[ArrayOf(TableDto::class)] array $tables, ?SocialRegistrationDto $myRegistration, #[ArrayOf(SocialParticipantDto::class)] array $participants` | none |
| `SocialGuestInfoDto` | `SocialEventSummaryDto $event, ?string $description, #[ArrayOf(MenuDto::class)] array $menus, #[ArrayOf(TableDto::class)] array $tables` | none |
| `CalendarDto` | `string $month, #[ArrayOf(EventSummaryDto::class)] array $events, #[ArrayOf(SocialEventSummaryDto::class)] array $socialEvents` | none |
| `RegisterRequest` | `string $comment` | – |
| `CommentRequest` | `string $comment` | – |
| `SocialRegisterRequest` | `?int $menuId, ?int $tableId` | – |
| `GuestRegistrationRequest` | `bool $male, string $firstName, string $lastName, ?string $email, ?string $handicap, ?string $comment` | – |
| `SocialGuestRequest` | `string $firstName, string $lastName, ?string $email, ?int $menuId, ?int $tableId` | – |

Example of the pattern (`EventSummaryDto.php`):

```php
<?php

declare(strict_types=1);

namespace TP\Api\Dto;

use TP\Models\Event;

final readonly class EventSummaryDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $date,
        public int $capacity,
        public int $joined,
        public int $waitlist,
        public int $available,
        public bool $isLocked,
        public RegistrationState $userState,
    ) {
    }

    public static function fromEvent(Event $event): self
    {
        return new self(
            $event->id,
            $event->name,
            ApiDate::date($event->date),
            $event->capacity,
            $event->joined,
            $event->onWaitList,
            $event->available,
            $event->isLocked,
            RegistrationState::fromDb($event->userState),
        );
    }
}
```

Array properties need the import `use TP\Core\Attributes\ArrayOf;` and the attribute directly before the promoted parameter, e.g. `#[ArrayOf(ParticipantDto::class)] public array $participants,`.

- [ ] **Step 4: Run to verify it passes** — `vendor/bin/phpunit --filter MemberDtoTest` → 5 tests OK. `composer test` → baseline + 5.

- [ ] **Step 5: Commit**

```bash
git add src/Api/Dto tests/Integration/Api/MemberDtoTest.php
git commit -m "feat(api): Add member-facing DTOs for events, social events and calendar"
```

(No regeneration yet — DTOs only appear in `generated.ts` once a route references them.)

---

### Task 2: Calendar endpoint

**Files:** Create `src/Controllers/Api/CalendarController.php`, `tests/Integration/Api/CalendarApiTest.php`; regenerate `generated.ts`.

Contract:

| Route | Access | Returns | Errors |
|---|---|---|---|
| `GET /api/calendar/{month}` (`YYYY-MM`) | public | `CalendarDto` | `404 errors.not_found` for a malformed month |

Logged in: `DB::$events->all($date)` and `DB::$socialEvents->all($date)` (include the user's state). Anonymous: `allForGuest($date)` for both. `$date` = first day of the month (`new DateTime("{$month}-01")`). Both lists sorted by date ascending (then by id, to be deterministic). `month` in the response echoes the request.

- [ ] **Step 1: Write the failing tests** — `CalendarApiTest`:

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Api;

use TP\Models\DB;
use TP\Tests\Integration\IntegrationTestCase;

class CalendarApiTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    public function testAnonymousSeesEventsOfTheMonthWithoutPersonalState(): void
    {
        DB::$events->add('June Cup', '2099-06-10', 20);
        DB::$events->add('Early June', '2099-06-02', 20);
        DB::$events->add('July Cup', '2099-07-01', 20);

        $response = $this->json('GET', '/api/calendar/2099-06');

        $this->assertSame(200, $response->statusCode);
        $data = $response->json();
        $this->assertSame('2099-06', $data['month']);
        $this->assertSame(['Early June', 'June Cup'], array_column($data['events'], 'name'));
        $this->assertSame('none', $data['events'][0]['userState']);
        $this->assertSame([], $data['socialEvents']);
    }

    public function testMemberSeesOwnRegistrationState(): void
    {
        $userId = DB::$users->create('jane', 'Pass123!');
        $eventId = DB::$events->add('Cup', '2099-06-10', 20);
        DB::$events->register($eventId, $userId, '');
        $this->apiLoginAs('jane', 'Pass123!');

        $event = $this->json('GET', '/api/calendar/2099-06')->json()['events'][0];

        $this->assertSame('registered', $event['userState']);
        $this->assertSame(1, $event['joined']);
        $this->assertSame(19, $event['available']);
    }

    public function testMalformedMonthIs404(): void
    {
        foreach (['2099-6', 'june', '2099-13', '2099-06-01'] as $month) {
            $this->assertSame(404, $this->json('GET', "/api/calendar/{$month}")->statusCode, $month);
        }
    }
}
```

Add one more test once you have checked `SocialEventRepository::add()`'s signature: a social event in June appears in `socialEvents` with `userRegistered` `false` for anonymous.

- [ ] **Step 2: Run to verify it fails** — `vendor/bin/phpunit --filter CalendarApiTest` → FAIL (404 `errors.not_found` for every request).

- [ ] **Step 3: Implement** — `src/Controllers/Api/CalendarController.php`:

```php
<?php

declare(strict_types=1);

namespace TP\Controllers\Api;

use DateTime;
use TP\Api\Dto\CalendarDto;
use TP\Api\Dto\EventSummaryDto;
use TP\Api\Dto\SocialEventSummaryDto;
use TP\Core\Attributes\Get;
use TP\Core\Attributes\Middleware;
use TP\Core\Attributes\Returns;
use TP\Core\Attributes\RoutePrefix;
use TP\Core\HttpStatus;
use TP\Core\Request;
use TP\Core\Response;
use TP\Middleware\CsrfMiddleware;
use TP\Models\DB;
use TP\Models\User;

#[RoutePrefix('/api/calendar')]
#[Middleware(CsrfMiddleware::class)]
final class CalendarController
{
    #[Get('/{month}')]
    #[Returns(CalendarDto::class)]
    public function month(Request $request, array $params): Response
    {
        $month = (string) $params['month'];
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return Response::apiError('errors.not_found', HttpStatus::NOT_FOUND);
        }

        $date = new DateTime("{$month}-01");
        $loggedIn = User::loggedIn();
        $events = $loggedIn ? DB::$events->all($date) : DB::$events->allForGuest($date);
        $socialEvents = $loggedIn ? DB::$socialEvents->all($date) : DB::$socialEvents->allForGuest($date);

        $byDate = fn(object $a, object $b): int => [$a->date, $a->id] <=> [$b->date, $b->id];
        usort($events, $byDate);
        usort($socialEvents, $byDate);

        return Response::json(new CalendarDto(
            $month,
            array_map(EventSummaryDto::fromEvent(...), $events),
            array_map(SocialEventSummaryDto::fromSocialEvent(...), $socialEvents),
        ));
    }
}
```

- [ ] **Step 4: Run to verify it passes** — `vendor/bin/phpunit --filter CalendarApiTest` → OK. `composer test` → baseline + new tests.

- [ ] **Step 5: Regenerate and commit**

```bash
composer api:generate
(cd frontend && npx ng build >/dev/null && echo build-ok)
git add src/Controllers/Api/CalendarController.php tests/Integration/Api/CalendarApiTest.php frontend/src/app/core/api/generated.ts
git commit -m "feat(api): Add month calendar endpoint"
```
Expected: `generated.ts` now has `calendarApi` with `month(month, options)` and the `CalendarDto`, `EventSummaryDto`, `SocialEventSummaryDto`, `RegistrationState` types.

---

### Task 3: Tournament detail and own registration

**Files:** Create `src/Controllers/Api/EventsController.php`, `tests/Integration/Api/EventsApiTest.php`; regenerate.

Contract (`#[RoutePrefix('/api/events')]`, class-level `CsrfMiddleware`; `AuthMiddleware` per route as listed):

| Route | Access | Body | Returns | Errors |
|---|---|---|---|---|
| `GET /{id}` | user | – | `EventDetailDto` | 404 `events.not_found` |
| `POST /{id}/registrations` | user | `RegisterRequest` | `EventDetailDto` | 404, 409 `events.locked_message` |
| `PATCH /{id}/registrations/me` | user | `CommentRequest` | `EventDetailDto` | 404, 404 `auth.not_registered`, 409 `events.locked_message` |
| `DELETE /{id}/registrations/me` | user | – | `EventDetailDto` | 404, 409 `events.locked_message` |

Rules:
- `EventDetailDto` is built by a private `detail(int $eventId, int $userId): ?EventDetailDto`: event via `DB::$events->get($eventId, $userId)`; `myRegistration` = the entry of `DB::$events->registrations($eventId)` whose `userId` equals `$userId` (or null); `participants` = all registrations in repository order; `guests` = `DB::$guests->allForEvent($eventId)`; `socialEvent` = `DB::$socialEvents->getForTournament($eventId)` (or null); `registrationClose` via `ApiDate::dateTime`.
- Locked = `DB::$events->isLocked($eventId)` (manual lock, date passed, or deadline passed) → 409 for all three mutations.
- Registering when the event is full puts the user on the waitlist (repository behaviour) — the response shows `myRegistration.state = 'waitlist'`.
- Registering again when already registered updates the comment (repository's `ON DUPLICATE KEY`) — keep.
- `{id}` is cast with `(int)`; a non-existent event → 404 before any other check.

- [ ] **Step 1: Write the failing tests** — `EventsApiTest`, at least these (write them out in full):

1. `testAnonymousGets401` — `GET /api/events/{id}` → 401 `auth.login_required`; `POST …/registrations` (after `GET /api/auth/me`) → 401.
2. `testDetailShowsEventParticipantsGuestsAndOwnRegistration` — event with description and prices; another user registered with comment; one guest via `DB::$guests->add($eventId, true, 'Gus', 'Guest', null, null, null)`; logged-in member not registered → `myRegistration` null, `participants[0].name` is the other user's display name, `guests[0].firstName` 'Gus', `event.userState` 'none', `priceMembers` matches.
3. `testRegisterReturnsDetailWithRegistration` — POST `{comment: 'Mit Trolley'}` → 200, `myRegistration` = `{state: 'registered', comment: 'Mit Trolley'}`, `event.joined` 1.
4. `testRegisterWhenFullGoesToWaitlist` — capacity 1, other user registered first → `myRegistration.state` 'waitlist', `event.waitlist` 1.
5. `testRegisterOnLockedEventIs409` — `DB::$events->lock($id)` → 409 `events.locked_message`, nothing stored (`DB::$events->registrations($id)` empty).
6. `testRegisterAfterDeadlineIs409` — deadline in the past → 409.
7. `testUpdateCommentChangesOwnComment` — PATCH `{comment: 'Neu'}` → `myRegistration.comment` 'Neu'.
8. `testUpdateCommentWhenNotRegisteredIs404` → 404 `auth.not_registered`.
9. `testUnregisterRemovesOwnRegistrationAndPromotesWaitlist` — capacity 1, member registered, other on waitlist; DELETE → 200, `myRegistration` null, the other user is now `registered` in `participants`.
10. `testUnregisterOnLockedEventIs409`.
11. `testUnknownEventIs404` — GET/POST/PATCH/DELETE on id 999999 → 404 `events.not_found`.
12. `testDetailIncludesLinkedSocialEvent` — social event linked to the tournament → `socialEvent.id` matches, `userRegistered` false.

- [ ] **Step 2: Run to verify they fail** — `vendor/bin/phpunit --filter EventsApiTest` → FAIL.

- [ ] **Step 3: Implement `EventsController`** following the contract and rules above. Shape of one action, to follow for the others:

```php
    #[Post('/{id}/registrations')]
    #[Middleware(AuthMiddleware::class)]
    #[Body(RegisterRequest::class)]
    #[Returns(EventDetailDto::class)]
    public function register(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];
        $userId = (int) User::id();

        if (DB::$events->get($eventId, $userId) === null) {
            return Response::apiError('events.not_found', HttpStatus::NOT_FOUND);
        }
        if (DB::$events->isLocked($eventId)) {
            return Response::apiError('events.locked_message', HttpStatus::CONFLICT);
        }

        DB::$events->register($eventId, $userId, $request->getString('comment'));

        return Response::json($this->detail($eventId, $userId));
    }
```

Declare the routes in this order: `GET /{id}`, `POST /{id}/registrations`, `PATCH /{id}/registrations/me`, `DELETE /{id}/registrations/me` (Task 5 adds the guest routes after them). Use `#[Patch]` (added in plan 1) and `#[Delete]`.

- [ ] **Step 4: Run to verify they pass** — `vendor/bin/phpunit --filter EventsApiTest` → OK. `composer test` → baseline + new tests.

- [ ] **Step 5: Regenerate and commit**

```bash
composer api:generate
(cd frontend && npx ng build >/dev/null && echo build-ok)
git add src/Controllers/Api/EventsController.php tests/Integration/Api/EventsApiTest.php frontend/src/app/core/api/generated.ts
git commit -m "feat(api): Add tournament detail and own registration endpoints"
```

---

### Task 4: Social event detail and own registration

**Files:** Create `src/Controllers/Api/SocialEventsController.php`, `tests/Integration/Api/SocialEventsApiTest.php`; regenerate.

Contract (`#[RoutePrefix('/api/social-events')]`, class-level `CsrfMiddleware`):

| Route | Access | Body | Returns | Errors |
|---|---|---|---|---|
| `GET /{id}` | user | – | `SocialEventDetailDto` | 404 `social_events.not_found` |
| `POST /{id}/registrations` | user | `SocialRegisterRequest` | `SocialEventDetailDto` | 404; 409 `social_events.locked_message`; 422 `validation.failed` with `fields.menuId` = `{key: 'social_events.invalid_menu'}` or `fields.tableId` = `{key: 'social_events.invalid_table'}`; 409 `social_events.event_full`; 409 `social_events.table_full` |
| `DELETE /{id}/registrations/me` | user | – | `SocialEventDetailDto` | 404; 409 `social_events.locked_message` |

Rules (same checks, same order as `SocialEventController::register()` today):
1. not found → 404; 2. locked (`$socialEvent->isLocked`) → 409; 3. already registered → return current detail (200); 4. event has menus and `menuId` is null → 422 `invalid_menu`; 5. `menuId` not belonging to the event → 422 `invalid_menu`; 6. `tableId` not belonging → 422 `invalid_table`; 7. event full (`isFull`) → 409 `event_full`; 8. table full (`isTableFull`) → 409 `table_full`; 9. `register(...)`.
- Event without menus: `menuId` is ignored (stored as null).
- `menuId`/`tableId` arrive as JSON numbers or null; read them with `$request->has('menuId') && $request->getString('menuId') !== '' ? $request->getInt('menuId') : null` (JSON null becomes `''` through the request sanitiser).
- Detail: `menus` = `DB::$socialEvents->menus($id)`, `tables` = `tables($id)`, `myRegistration` = `getUserRegistration($id, $userId)`, `participants` = `registrations($id)` (members see names and table numbers, as on today's page).

- [ ] **Step 1: Write the failing tests** — `SocialEventsApiTest`, at least: anonymous 401 for GET and POST; detail lists menus/tables/participants with `myRegistration` null; register with menu and table → `myRegistration` = `{menuName, tableNumber}` and the table's `available` decreased; register without menu when menus exist → 422 with `fields.menuId.key` `social_events.invalid_menu`; foreign menu id → 422; foreign table id → 422 `fields.tableId`; locked → 409; full event → 409 `social_events.event_full`; full table → 409 `social_events.table_full`; registering twice → 200 and still one registration; unregister → `myRegistration` null; unregister when locked → 409; unknown id → 404 `social_events.not_found`.

- [ ] **Step 2: Run to verify they fail.**

- [ ] **Step 3: Implement** following contract and rules. Field errors are built by hand:

```php
return Response::apiError('validation.failed', HttpStatus::UNPROCESSABLE_ENTITY, [
    'menuId' => ['key' => 'social_events.invalid_menu'],
]);
```

- [ ] **Step 4: Run to verify they pass**, then `composer test`.

- [ ] **Step 5: Regenerate and commit**

```bash
composer api:generate
(cd frontend && npx ng build >/dev/null && echo build-ok)
git add src/Controllers/Api/SocialEventsController.php tests/Integration/Api/SocialEventsApiTest.php frontend/src/app/core/api/generated.ts
git commit -m "feat(api): Add social event detail and own registration endpoints"
```

---

### Task 5: Public guest registration

**Files:** Modify `src/Controllers/Api/EventsController.php`, `src/Controllers/Api/SocialEventsController.php`; create `tests/Integration/Api/GuestApiTest.php`; regenerate.

Contract (both routes **public** — no `AuthMiddleware`; CSRF still applies, the guest page fetches `/api/auth/me` first):

| Route | Body | Returns | Errors |
|---|---|---|---|
| `GET /api/events/{id}/guest-info` | – | `PublicEventDto` | 404 `events.not_found` |
| `POST /api/events/{id}/guests` | `GuestRegistrationRequest` | 204 | 404; 409 `events.locked_message` (non-admins); 422 `validation.failed` + fields |
| `GET /api/social-events/{id}/guest-info` | – | `SocialGuestInfoDto` | 404 `social_events.not_found` |
| `POST /api/social-events/{id}/guests` | `SocialGuestRequest` | 204 | 404; 409 `social_events.locked_message` (non-admins); 422 validation / `invalid_menu` / `invalid_table`; 409 `event_full` / `table_full` |

Validation (`GuestRegistrationRequest`, mirrors `GuestController::validationRules()` with camelCase names):
- `male`: required, boolean; `firstName`, `lastName`: required, string, max 255; `email`: required (non-admin), email, max 255; `handicap`: required (non-admin), string; `comment`: string, max 2048.
- Store with `DB::$guests->add($eventId, $request->getBool('male'), firstName, lastName, email ?: null, handicap !== '' ? (float) handicap : null, comment ?: null)`.

Validation (`SocialGuestRequest`): `firstName`, `lastName` required/string/max 255; `email` required for non-admins, email, max 255; `menuId` required integer when the event has menus; then the same menu/table/full checks as Task 4; store with `DB::$socialEvents->registerGuest(...)`.

Lock rule ⚠: `DB::$events->isLocked($eventId)` / `$socialEvent->isLocked` → 409 unless `User::admin()`.

Use `DB::$events->get($eventId, 0)` / `DB::$socialEvents->get($id, 0)` for the public lookups (no personal state).

Declare the guest routes **after** the Task-3/4 routes in each controller.

- [ ] **Step 1: Write the failing tests** — `GuestApiTest`, at least: guest-info for an existing event as anonymous (no participants in the payload — assert the keys are exactly those of `PublicEventDto`); unknown id 404; anonymous registration with all fields → 204 and `DB::$guests->allForEvent()` has the guest with handicap `12.5`; missing email/handicap as anonymous → 422 with `fields.email.key` `validation.required` and `fields.handicap.key` `validation.required`; admin may omit email and handicap → 204; invalid email → 422 `validation.email`; ⚠ locked event as anonymous → 409 `events.locked_message` and nothing stored; locked event as admin → 204; social guest-info returns menus/tables; social guest with menu → 204 and the registration exists; social guest without menu when menus exist → 422; social guest on locked event → 409 (anonymous) / 204 (admin); request without CSRF token → 403.

- [ ] **Step 2: Run to verify they fail.**

- [ ] **Step 3: Implement.**

- [ ] **Step 4: Run to verify they pass**, then `composer test`. The old HTML guest routes (`/events/{id}/guests/new`) keep their behaviour — their tests must still pass.

- [ ] **Step 5: Regenerate and commit**

```bash
composer api:generate
(cd frontend && npx ng build >/dev/null && echo build-ok)
git add src/Controllers/Api tests/Integration/Api/GuestApiTest.php frontend/src/app/core/api/generated.ts
git commit -m "feat(api): Add public guest registration endpoints (locked events rejected for non-admins)"
```

---

### Task 6: Documentation

**Files:** Modify `docs/API.md`, `README.md`, `CLAUDE.md`, `docs/TESTING.md`.

- [ ] **Step 1:** In `docs/API.md`, add a section `## Member endpoints` with one table per controller (copy the contract tables from Tasks 2–5), the date/date-time format rule, the `RegistrationState` values, and the note that mutating member endpoints return the fresh detail DTO.
- [ ] **Step 2:** Update the test counts (`composer test 2>&1 | grep '^Tests:'`) in `README.md`, `CLAUDE.md`, `docs/TESTING.md`.
- [ ] **Step 3:** Run `composer test`, `composer api:check`, and in `frontend/`: `npm test && npm run lint && npx ng build`.
- [ ] **Step 4: Commit**

```bash
git add docs/API.md README.md CLAUDE.md docs/TESTING.md
git commit -m "docs: Document member API endpoints"
```

## Done criteria for plan 3

- All endpoints in the contract tables exist, are tested (including anonymous/member/admin access), and appear in `generated.ts` (`calendarApi`, `eventsApi`, `socialEventsApi`).
- `composer test` and `composer api:check` pass; the frontend still builds, lints and tests green.
- The legacy HTML UI is unchanged.
