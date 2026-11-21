<?php

declare(strict_types=1);

namespace TP\Controllers\Api;

use DateTime;
use Exception;
use TP\Api\Dto\AdminGuestDto;
use TP\Api\Dto\AdminRegistrationDto;
use TP\Api\Dto\ApiDate;
use TP\Api\Dto\BulkCreateRequest;
use TP\Api\Dto\BulkEventDto;
use TP\Api\Dto\BulkPreviewDto;
use TP\Api\Dto\BulkRequest;
use TP\Api\Dto\BulkResultDto;
use TP\Api\Dto\CommentRequest;
use TP\Api\Dto\EventAdminDto;
use TP\Api\Dto\EventRequest;
use TP\Api\Dto\EventSummaryDto;
use TP\Api\Dto\EventUpdateRequest;
use TP\Api\Dto\GuestRegistrationRequest;
use TP\Api\Dto\RegisterRequest;
use TP\Api\Dto\SocialEventSummaryDto;
use TP\Api\Dto\UserOptionDto;
use TP\Core\Attributes\Body;
use TP\Core\Attributes\Delete;
use TP\Core\Attributes\Get;
use TP\Core\Attributes\Middleware;
use TP\Core\Attributes\Patch;
use TP\Core\Attributes\Post;
use TP\Core\Attributes\Put;
use TP\Core\Attributes\RawResponse;
use TP\Core\Attributes\Returns;
use TP\Core\Attributes\RoutePrefix;
use TP\Core\HttpStatus;
use TP\Core\Request;
use TP\Core\Response;
use TP\Core\ValidationRule;
use TP\Core\Validator;
use TP\Middleware\AdminMiddleware;
use TP\Middleware\CsrfMiddleware;
use TP\Models\DB;
use TP\Models\User;
use TP\Services\EventExport;
use TP\Services\RecurringEvents;

#[RoutePrefix('/api/events')]
#[Middleware(CsrfMiddleware::class)]
#[Middleware(AdminMiddleware::class)]
final class EventAdminController
{
    private const TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';

    #[Get('')]
    #[Returns(EventSummaryDto::class, list: true)]
    public function index(Request $request): Response
    {
        return Response::json(array_map(EventSummaryDto::fromEvent(...), DB::$events->all()));
    }

    #[Post('')]
    #[Body(EventRequest::class)]
    #[Returns(EventAdminDto::class)]
    public function create(Request $request): Response
    {
        $errors = $this->validateEvent($request, true);
        if ($errors !== []) {
            return $this->invalid($errors);
        }

        $eventId = DB::$events->add(
            $request->getString('name'),
            (new DateTime($request->getString('date')))->format('Y-m-d'),
            $request->getInt('capacity'),
            false,
            $request->getBool('mixed'),
            $this->nullable($request, 'description'),
            $this->nullable($request, 'priceMembers'),
            $this->nullable($request, 'priceGuests'),
            self::dateTime($request->getString('registrationClose')),
        );

        return Response::json($this->detail($eventId));
    }

    #[Get('/{id}/admin')]
    #[Returns(EventAdminDto::class)]
    public function show(Request $request, array $params): Response
    {
        return $this->withEvent($params, fn(int $id) => Response::json($this->detail($id)));
    }

    #[Put('/{id}')]
    #[Body(EventUpdateRequest::class)]
    #[Returns(EventAdminDto::class)]
    public function update(Request $request, array $params): Response
    {
        return $this->withEvent($params, function (int $id) use ($request) {
            $errors = $this->validateEvent($request, false);
            if ($errors !== []) {
                return $this->invalid($errors);
            }

            DB::$events->update(
                $id,
                $request->getString('name'),
                $request->getInt('capacity'),
                $request->getBool('mixed'),
                $this->nullable($request, 'description'),
                $this->nullable($request, 'priceMembers'),
                $this->nullable($request, 'priceGuests'),
                self::dateTime($request->getString('registrationClose')),
            );

            return Response::json($this->detail($id));
        });
    }

    #[Delete('/{id}')]
    public function delete(Request $request, array $params): Response
    {
        return $this->withEvent($params, function (int $id) {
            DB::$events->delete($id);
            return Response::noContent();
        });
    }

    #[Put('/{id}/lock')]
    #[Returns(EventAdminDto::class)]
    public function lock(Request $request, array $params): Response
    {
        return $this->withEvent($params, function (int $id) {
            DB::$events->lock($id);
            return Response::json($this->detail($id));
        });
    }

    #[Delete('/{id}/lock')]
    #[Returns(EventAdminDto::class)]
    public function unlock(Request $request, array $params): Response
    {
        return $this->withEvent($params, function (int $id) {
            DB::$events->unlock($id);
            return Response::json($this->detail($id));
        });
    }

    #[Get('/{id}/export')]
    #[RawResponse]
    public function export(Request $request, array $params): Response
    {
        $event = DB::$events->get((int) $params['id'], (int) User::id());

        return $event === null
            ? Response::apiError('events.not_found', HttpStatus::NOT_FOUND)
            : EventExport::csvResponse($event);
    }

    #[Post('/{id}/registrations/{userId}')]
    #[Body(RegisterRequest::class)]
    #[Returns(EventAdminDto::class)]
    public function register(Request $request, array $params): Response
    {
        return $this->withEvent($params, function (int $id) use ($request, $params) {
            $userId = (int) $params['userId'];
            if (DB::$users->get($userId) === null) {
                return Response::apiError('users.not_found', HttpStatus::NOT_FOUND);
            }
            if (DB::$events->isLocked($id)) {
                return Response::apiError('events.locked_message', HttpStatus::CONFLICT);
            }

            DB::$events->register($id, $userId, $request->getString('comment'));

            return Response::json($this->detail($id));
        });
    }

    #[Patch('/{id}/registrations/{userId}')]
    #[Body(CommentRequest::class)]
    #[Returns(EventAdminDto::class)]
    public function updateComment(Request $request, array $params): Response
    {
        return $this->withEvent($params, function (int $id) use ($request, $params) {
            $userId = (int) $params['userId'];
            if (DB::$events->isLocked($id)) {
                return Response::apiError('events.locked_message', HttpStatus::CONFLICT);
            }
            if (!$this->isRegistered($id, $userId)) {
                return Response::apiError('auth.not_registered', HttpStatus::NOT_FOUND);
            }

            DB::$events->updateRegistrationComment($id, $userId, $request->getString('comment'));

            return Response::json($this->detail($id));
        });
    }

    #[Delete('/{id}/registrations/{userId}')]
    #[Returns(EventAdminDto::class)]
    public function unregister(Request $request, array $params): Response
    {
        return $this->withEvent($params, function (int $id) use ($params) {
            if (DB::$events->isLocked($id)) {
                return Response::apiError('events.locked_message', HttpStatus::CONFLICT);
            }

            DB::$events->unregister($id, (int) $params['userId']);

            return Response::json($this->detail($id));
        });
    }

    #[Get('/{id}/guests/{guestId}')]
    #[Returns(AdminGuestDto::class)]
    public function showGuest(Request $request, array $params): Response
    {
        return $this->withGuest($params, fn(int $id, int $guestId) => Response::json(
            AdminGuestDto::fromGuest(DB::$guests->get($guestId))
        ));
    }

    #[Put('/{id}/guests/{guestId}')]
    #[Body(GuestRegistrationRequest::class)]
    #[Returns(AdminGuestDto::class)]
    public function updateGuest(Request $request, array $params): Response
    {
        return $this->withGuest($params, function (int $id, int $guestId) use ($request) {
            $errors = [];
            if (!$request->has('male')) {
                $errors['male'] = ['key' => 'validation.required'];
            }
            $validation = $request->validate([
                new ValidationRule('firstName', ['required', 'string', 'max' => 255]),
                new ValidationRule('lastName', ['required', 'string', 'max' => 255]),
                new ValidationRule(
                    'email',
                    $request->getString('email') === '' ? ['string'] : ['email', 'max' => 255]
                ),
                new ValidationRule('handicap', ['string']),
                new ValidationRule('comment', ['string', 'max' => 2048]),
            ]);
            if (!$validation->isValid || $errors !== []) {
                return $this->invalid($errors + $validation->fieldErrors());
            }

            $handicap = $request->getString('handicap');
            DB::$guests->update(
                $guestId,
                $request->getBool('male'),
                $request->getString('firstName'),
                $request->getString('lastName'),
                $request->getString('email') ?: null,
                $handicap !== '' ? (float) $handicap : null,
                $request->getString('comment') ?: null,
            );

            return Response::json(AdminGuestDto::fromGuest(DB::$guests->get($guestId)));
        });
    }

    #[Delete('/{id}/guests/{guestId}')]
    public function deleteGuest(Request $request, array $params): Response
    {
        return $this->withGuest($params, function (int $id, int $guestId) {
            DB::$guests->delete($guestId);
            return Response::noContent();
        });
    }

    #[Post('/bulk/preview')]
    #[Body(BulkRequest::class)]
    #[Returns(BulkPreviewDto::class)]
    public function bulkPreview(Request $request): Response
    {
        $rules = [
            new ValidationRule('startDate', ['required', 'date']),
            new ValidationRule('endDate', ['required', 'date']),
            new ValidationRule('dayOfWeek', ['required', 'integer', 'min' => 0, 'max' => 6]),
            new ValidationRule('name', ['required', 'string', 'max' => 255]),
            new ValidationRule('capacity', ['required', 'integer', 'min' => 1]),
        ];
        $closeDays = $request->getString('registrationCloseDays');
        if ($closeDays !== '') {
            $rules[] = new ValidationRule('registrationCloseDays', ['integer', 'min' => 1, 'max' => 7]);
        }
        $validation = $request->validate($rules);
        $errors = $validation->fieldErrors();

        $closeTime = $request->getString('registrationCloseTime');
        if ($closeTime !== '' && preg_match(self::TIME_PATTERN, $closeTime) !== 1) {
            $errors['registrationCloseTime'] = ['key' => 'validation.date'];
        }
        if ($errors !== []) {
            return $this->invalid($errors);
        }

        $events = RecurringEvents::plan(
            $request->getString('startDate'),
            $request->getString('endDate'),
            $request->getInt('dayOfWeek'),
            $request->getString('name'),
            $request->getInt('capacity'),
            $request->getBool('mixed'),
            $this->nullable($request, 'description'),
            $this->nullable($request, 'priceMembers'),
            $this->nullable($request, 'priceGuests'),
            $closeDays !== '' ? (int) $closeDays : null,
            $closeTime !== '' ? $closeTime : null,
        );

        return Response::json(new BulkPreviewDto(array_map(
            fn(BulkEventDto $e) => new BulkEventDto(
                $e->name,
                $e->date,
                $e->capacity,
                $e->mixed,
                $e->description,
                $e->priceMembers,
                $e->priceGuests,
                ApiDate::dateTime($e->registrationClose),
            ),
            $events
        )));
    }

    #[Post('/bulk')]
    #[Body(BulkCreateRequest::class)]
    #[Returns(BulkResultDto::class)]
    public function bulkCreate(Request $request): Response
    {
        $items = $request->getArray('events');
        if ($items === []) {
            return $this->invalid(['events' => ['key' => 'validation.required']]);
        }

        $validator = new Validator();
        $errors = [];
        foreach (array_values($items) as $i => $item) {
            $item = is_array($item) ? $item : [];
            $result = $validator->validate($item, [
                new ValidationRule('name', ['required', 'string', 'max' => 255]),
                new ValidationRule('date', ['required', 'date']),
                new ValidationRule('capacity', ['required', 'integer', 'min' => 1]),
            ]);
            foreach ($result->fieldErrors() as $field => $error) {
                $errors["events.{$i}.{$field}"] = $error;
            }
            if (self::dateTime((string) ($item['registrationClose'] ?? '')) === false) {
                $errors["events.{$i}.registrationClose"] = ['key' => 'validation.date'];
            }
        }
        if ($errors !== []) {
            return $this->invalid($errors);
        }

        $created = 0;
        foreach ($items as $item) {
            try {
                // Bulk-created events start locked, like the legacy bulk store
                DB::$events->add(
                    (string) $item['name'],
                    (new DateTime((string) $item['date']))->format('Y-m-d'),
                    (int) $item['capacity'],
                    true,
                    in_array(strtolower((string) ($item['mixed'] ?? '')), ['1', 'true', 'on', 'yes'], true),
                    ($item['description'] ?? '') !== '' ? (string) $item['description'] : null,
                    ($item['priceMembers'] ?? '') !== '' ? (string) $item['priceMembers'] : null,
                    ($item['priceGuests'] ?? '') !== '' ? (string) $item['priceGuests'] : null,
                    self::dateTime((string) ($item['registrationClose'] ?? '')),
                );
                $created++;
            } catch (Exception $e) {
                logger()->error('Bulk event creation failed: ' . $e->getMessage());
            }
        }

        return Response::json(new BulkResultDto($created));
    }

    /** Run `$action($eventId)` if the event exists, else 404. */
    private function withEvent(array $params, callable $action): Response
    {
        $eventId = (int) $params['id'];
        if (DB::$events->get($eventId, (int) User::id()) === null) {
            return Response::apiError('events.not_found', HttpStatus::NOT_FOUND);
        }
        return $action($eventId);
    }

    /** Run `$action($eventId, $guestId)` if the event exists and the guest belongs to it, else 404. */
    private function withGuest(array $params, callable $action): Response
    {
        return $this->withEvent($params, function (int $id) use ($params, $action) {
            $guestId = (int) $params['guestId'];
            $guest = DB::$guests->get($guestId);
            if ($guest === null || $guest->eventId !== $id) {
                return Response::apiError('guests.not_found', HttpStatus::NOT_FOUND);
            }
            return $action($id, $guestId);
        });
    }

    private function isRegistered(int $eventId, int $userId): bool
    {
        foreach (DB::$events->registrations($eventId) as $registration) {
            if ($registration->userId === $userId) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string, array{key: string, params?: array<string, scalar>}> */
    private function validateEvent(Request $request, bool $withDate): array
    {
        $rules = [
            new ValidationRule('name', ['required', 'string', 'max' => 255]),
            new ValidationRule('capacity', ['required', 'integer', 'min' => 1]),
        ];
        if ($withDate) {
            $rules[] = new ValidationRule('date', ['required', 'date']);
        }
        $errors = $request->validate($rules)->fieldErrors();

        if (self::dateTime($request->getString('registrationClose')) === false) {
            $errors['registrationClose'] = ['key' => 'validation.date'];
        }
        return $errors;
    }

    private function invalid(array $fields): Response
    {
        return Response::apiError('validation.failed', HttpStatus::UNPROCESSABLE_ENTITY, $fields);
    }

    private function nullable(Request $request, string $key): ?string
    {
        return $request->getString($key) ?: null;
    }

    /**
     * Normalise a `YYYY-MM-DDTHH:MM[:SS]` input to the stored `YYYY-MM-DD HH:MM:SS`.
     * Returns null for an empty value and false when the value is not a valid date-time.
     */
    private static function dateTime(string $value): string|false|null
    {
        if ($value === '') {
            return null;
        }
        $value = str_replace('T', ' ', $value);
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $parsed = DateTime::createFromFormat('!' . $format, $value);
            if ($parsed !== false && $parsed->format($format) === $value) {
                return $parsed->format('Y-m-d H:i:00');
            }
        }
        return false;
    }

    private function detail(int $eventId): EventAdminDto
    {
        $event = DB::$events->get($eventId, (int) User::id());
        $social = DB::$socialEvents->getForTournament($eventId);

        return new EventAdminDto(
            EventSummaryDto::fromEvent($event),
            $event->mixed,
            $event->description,
            $event->priceMembers,
            $event->priceGuests,
            ApiDate::dateTime($event->registrationClose),
            array_map(AdminRegistrationDto::fromRegistration(...), DB::$events->registrations($eventId)),
            array_map(AdminGuestDto::fromGuest(...), DB::$guests->allForEvent($eventId)),
            array_map(UserOptionDto::fromUser(...), DB::$events->availableUsers($eventId)),
            $social === null ? null : SocialEventSummaryDto::fromSocialEvent($social),
        );
    }
}
