<?php

declare(strict_types=1);

namespace TP\Controllers\Api;

use TP\Api\Dto\ApiDate;
use TP\Api\Dto\CommentRequest;
use TP\Api\Dto\EventDetailDto;
use TP\Api\Dto\EventSummaryDto;
use TP\Api\Dto\GuestNameDto;
use TP\Api\Dto\GuestRegistrationRequest;
use TP\Api\Dto\MyRegistrationDto;
use TP\Api\Dto\ParticipantDto;
use TP\Api\Dto\PublicEventDto;
use TP\Api\Dto\RegisterRequest;
use TP\Api\Dto\SocialEventSummaryDto;
use TP\Core\Attributes\Body;
use TP\Core\Attributes\Delete;
use TP\Core\Attributes\Get;
use TP\Core\Attributes\Middleware;
use TP\Core\Attributes\Patch;
use TP\Core\Attributes\Post;
use TP\Core\Attributes\Returns;
use TP\Core\Attributes\RoutePrefix;
use TP\Core\HttpStatus;
use TP\Core\RateLimiter;
use TP\Core\Request;
use TP\Core\Response;
use TP\Core\ValidationRule;
use TP\Middleware\AuthMiddleware;
use TP\Middleware\CsrfMiddleware;
use TP\Models\DB;
use TP\Models\EventRegistration;
use TP\Models\User;

#[RoutePrefix('/api/events')]
#[Middleware(CsrfMiddleware::class)]
final class EventsController
{
    #[Get('/{id}')]
    #[Middleware(AuthMiddleware::class)]
    #[Returns(EventDetailDto::class)]
    public function show(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];
        $detail = $this->detail($eventId, (int) User::id());

        return $detail === null
            ? Response::apiError('events.not_found', HttpStatus::NOT_FOUND)
            : Response::json($detail);
    }

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

    #[Patch('/{id}/registrations/me')]
    #[Middleware(AuthMiddleware::class)]
    #[Body(CommentRequest::class)]
    #[Returns(EventDetailDto::class)]
    public function updateComment(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];
        $userId = (int) User::id();

        if (DB::$events->get($eventId, $userId) === null) {
            return Response::apiError('events.not_found', HttpStatus::NOT_FOUND);
        }
        if (DB::$events->isLocked($eventId)) {
            return Response::apiError('events.locked_message', HttpStatus::CONFLICT);
        }
        if ($this->myRegistration($eventId, $userId) === null) {
            return Response::apiError('auth.not_registered', HttpStatus::NOT_FOUND);
        }

        DB::$events->updateRegistrationComment($eventId, $userId, $request->getString('comment'));

        return Response::json($this->detail($eventId, $userId));
    }

    #[Delete('/{id}/registrations/me')]
    #[Middleware(AuthMiddleware::class)]
    #[Returns(EventDetailDto::class)]
    public function unregister(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];
        $userId = (int) User::id();

        if (DB::$events->get($eventId, $userId) === null) {
            return Response::apiError('events.not_found', HttpStatus::NOT_FOUND);
        }
        if (DB::$events->isLocked($eventId)) {
            return Response::apiError('events.locked_message', HttpStatus::CONFLICT);
        }

        DB::$events->unregister($eventId, $userId);

        return Response::json($this->detail($eventId, $userId));
    }

    #[Get('/{id}/guest-info')]
    #[Returns(PublicEventDto::class)]
    public function guestInfo(Request $request, array $params): Response
    {
        $event = DB::$events->get((int) $params['id'], 0);

        return $event === null
            ? Response::apiError('events.not_found', HttpStatus::NOT_FOUND)
            : Response::json(PublicEventDto::fromEvent($event));
    }

    #[Post('/{id}/guests')]
    #[Body(GuestRegistrationRequest::class)]
    public function addGuest(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];

        if (DB::$events->get($eventId, 0) === null) {
            return Response::apiError('events.not_found', HttpStatus::NOT_FOUND);
        }

        // Honeypot: bots fill the hidden field. Pretend it worked, store nothing.
        if ($request->getString('website') !== '') {
            return Response::noContent();
        }

        $isAdmin = User::admin();
        // Locked events (manual lock, date or deadline passed) only accept guests from admins
        if (!$isAdmin && DB::$events->isLocked($eventId)) {
            return Response::apiError('events.locked_message', HttpStatus::CONFLICT);
        }

        $validation = $request->validate([
            new ValidationRule('male', ['required', 'boolean']),
            new ValidationRule('firstName', ['required', 'string', 'max' => 255]),
            new ValidationRule('lastName', ['required', 'string', 'max' => 255]),
            new ValidationRule('email', $this->emailRules($isAdmin, $request)),
            new ValidationRule('handicap', $isAdmin ? ['string'] : ['required', 'string']),
            new ValidationRule('comment', ['string', 'max' => 2048]),
        ]);
        if (!$validation->isValid) {
            return Response::apiError('validation.failed', HttpStatus::UNPROCESSABLE_ENTITY, $validation->fieldErrors());
        }

        if (!$isAdmin && !RateLimiter::fromEnvironment()->hit('guest:' . $request->clientIp(), 10, 3600)) {
            return Response::apiError('errors.too_many_requests', HttpStatus::TOO_MANY_REQUESTS);
        }

        $handicap = $request->getString('handicap');
        DB::$guests->add(
            $eventId,
            $request->getBool('male'),
            $request->getString('firstName'),
            $request->getString('lastName'),
            $request->getString('email') ?: null,
            $handicap !== '' ? (float) $handicap : null,
            $request->getString('comment') ?: null,
        );

        return Response::noContent();
    }

    /** Admins may leave the email blank; everyone else must give a valid one. */
    private function emailRules(bool $isAdmin, Request $request): array
    {
        if ($isAdmin) {
            return $request->getString('email') === '' ? ['string'] : ['email', 'max' => 255];
        }
        return ['required', 'email', 'max' => 255];
    }

    private function myRegistration(int $eventId, int $userId): ?EventRegistration
    {
        foreach (DB::$events->registrations($eventId) as $registration) {
            if ($registration->userId === $userId) {
                return $registration;
            }
        }
        return null;
    }

    private function detail(int $eventId, int $userId): ?EventDetailDto
    {
        $event = DB::$events->get($eventId, $userId);
        if ($event === null) {
            return null;
        }

        $registrations = DB::$events->registrations($eventId);
        $mine = null;
        foreach ($registrations as $registration) {
            if ($registration->userId === $userId) {
                $mine = MyRegistrationDto::fromRegistration($registration);
                break;
            }
        }
        $social = DB::$socialEvents->getForTournament($eventId);

        return new EventDetailDto(
            EventSummaryDto::fromEvent($event),
            $event->description,
            $event->priceMembers,
            $event->priceGuests,
            ApiDate::dateTime($event->registrationClose),
            $mine,
            array_map(ParticipantDto::fromRegistration(...), $registrations),
            array_map(GuestNameDto::fromGuest(...), DB::$guests->allForEvent($eventId)),
            $social === null ? null : SocialEventSummaryDto::fromSocialEvent($social),
        );
    }
}
