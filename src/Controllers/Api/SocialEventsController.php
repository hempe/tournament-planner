<?php

declare(strict_types=1);

namespace TP\Controllers\Api;

use TP\Api\Dto\MenuDto;
use TP\Api\Dto\SocialEventDetailDto;
use TP\Api\Dto\SocialEventSummaryDto;
use TP\Api\Dto\SocialGuestInfoDto;
use TP\Api\Dto\SocialGuestRequest;
use TP\Api\Dto\SocialParticipantDto;
use TP\Api\Dto\SocialRegisterRequest;
use TP\Api\Dto\SocialRegistrationDto;
use TP\Api\Dto\TableDto;
use TP\Api\Dto\ApiDate;
use TP\Core\Attributes\Body;
use TP\Core\Attributes\Delete;
use TP\Core\Attributes\Feature;
use TP\Core\Attributes\Get;
use TP\Core\Attributes\Middleware;
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
use TP\Models\User;

#[Feature('socialEvents')]
#[RoutePrefix('/api/social-events')]
#[Middleware(CsrfMiddleware::class)]
final class SocialEventsController
{
    #[Get('/{id}')]
    #[Middleware(AuthMiddleware::class)]
    #[Returns(SocialEventDetailDto::class)]
    public function show(Request $request, array $params): Response
    {
        $detail = $this->detail((int) $params['id'], (int) User::id());

        return $detail === null
            ? Response::apiError('social_events.not_found', HttpStatus::NOT_FOUND)
            : Response::json($detail);
    }

    #[Post('/{id}/registrations')]
    #[Middleware(AuthMiddleware::class)]
    #[Body(SocialRegisterRequest::class)]
    #[Returns(SocialEventDetailDto::class)]
    public function register(Request $request, array $params): Response
    {
        $id = (int) $params['id'];
        $userId = (int) User::id();

        $socialEvent = DB::$socialEvents->get($id, $userId);
        if ($socialEvent === null) {
            return Response::apiError('social_events.not_found', HttpStatus::NOT_FOUND);
        }
        if ($socialEvent->isLocked) {
            return Response::apiError('social_events.locked_message', HttpStatus::CONFLICT);
        }
        if ($socialEvent->userRegistered === 1) {
            return Response::json($this->detail($id, $userId));
        }

        $hasMenus = DB::$socialEvents->menus($id) !== [];
        $menuId = $hasMenus ? $this->optionalInt($request, 'menuId') : null;
        if ($hasMenus && $menuId === null) {
            return $this->invalidField('menuId', 'social_events.invalid_menu');
        }
        $tableId = $this->optionalInt($request, 'tableId');

        $error = $this->checkChoice($id, $menuId, $tableId);
        if ($error !== null) {
            return $error;
        }

        DB::$socialEvents->register($id, $userId, $menuId, $tableId);

        return Response::json($this->detail($id, $userId));
    }

    #[Delete('/{id}/registrations/me')]
    #[Middleware(AuthMiddleware::class)]
    #[Returns(SocialEventDetailDto::class)]
    public function unregister(Request $request, array $params): Response
    {
        $id = (int) $params['id'];
        $userId = (int) User::id();

        $socialEvent = DB::$socialEvents->get($id, $userId);
        if ($socialEvent === null) {
            return Response::apiError('social_events.not_found', HttpStatus::NOT_FOUND);
        }
        if ($socialEvent->isLocked) {
            return Response::apiError('social_events.locked_message', HttpStatus::CONFLICT);
        }

        DB::$socialEvents->unregister($id, $userId);

        return Response::json($this->detail($id, $userId));
    }

    #[Feature('guests')]
    #[Get('/{id}/guest-info')]
    #[Returns(SocialGuestInfoDto::class)]
    public function guestInfo(Request $request, array $params): Response
    {
        $id = (int) $params['id'];
        $socialEvent = DB::$socialEvents->get($id, 0);
        if ($socialEvent === null) {
            return Response::apiError('social_events.not_found', HttpStatus::NOT_FOUND);
        }

        return Response::json(new SocialGuestInfoDto(
            SocialEventSummaryDto::fromSocialEvent($socialEvent),
            $socialEvent->description,
            array_map(MenuDto::fromMenu(...), DB::$socialEvents->menus($id)),
            array_map(TableDto::fromTable(...), DB::$socialEvents->tables($id)),
        ));
    }

    #[Feature('guests')]
    #[Post('/{id}/guests')]
    #[Body(SocialGuestRequest::class)]
    public function addGuest(Request $request, array $params): Response
    {
        $id = (int) $params['id'];
        $socialEvent = DB::$socialEvents->get($id, 0);
        if ($socialEvent === null) {
            return Response::apiError('social_events.not_found', HttpStatus::NOT_FOUND);
        }

        // Honeypot: bots fill the hidden field. Pretend it worked, store nothing.
        if ($request->getString('website') !== '') {
            return Response::noContent();
        }

        $isAdmin = User::admin();
        // Locked events only accept guests from admins
        if (!$isAdmin && $socialEvent->isLocked) {
            return Response::apiError('social_events.locked_message', HttpStatus::CONFLICT);
        }

        $hasMenus = DB::$socialEvents->menus($id) !== [];
        $rules = [
            new ValidationRule('firstName', ['required', 'string', 'max' => 255]),
            new ValidationRule('lastName', ['required', 'string', 'max' => 255]),
            new ValidationRule('email', $this->emailRules($isAdmin, $request)),
        ];
        if ($hasMenus) {
            $rules[] = new ValidationRule('menuId', ['required', 'integer']);
        }
        $validation = $request->validate($rules);
        if (!$validation->isValid) {
            return Response::apiError('validation.failed', HttpStatus::UNPROCESSABLE_ENTITY, $validation->fieldErrors());
        }

        if (!$isAdmin && !RateLimiter::fromEnvironment()->hit('guest:' . $request->clientIp(), 10, 3600)) {
            return Response::apiError('errors.too_many_requests', HttpStatus::TOO_MANY_REQUESTS);
        }

        $menuId = $hasMenus ? $this->optionalInt($request, 'menuId') : null;
        $tableId = $this->optionalInt($request, 'tableId');

        $error = $this->checkChoice($id, $menuId, $tableId);
        if ($error !== null) {
            return $error;
        }

        DB::$socialEvents->registerGuest(
            $id,
            $request->getString('firstName'),
            $request->getString('lastName'),
            $request->getString('email'),
            $menuId,
            $tableId,
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

    private function optionalInt(Request $request, string $key): ?int
    {
        return $request->has($key) && $request->getString($key) !== '' ? $request->getInt($key) : null;
    }

    private function invalidField(string $field, string $key): Response
    {
        return Response::apiError('validation.failed', HttpStatus::UNPROCESSABLE_ENTITY, [
            $field => ['key' => $key],
        ]);
    }

    /**
     * Menu/table ownership and capacity checks shared by member and guest registration.
     */
    private function checkChoice(int $socialEventId, ?int $menuId, ?int $tableId): ?Response
    {
        if ($menuId !== null && !DB::$socialEvents->menuBelongsToEvent($menuId, $socialEventId)) {
            return $this->invalidField('menuId', 'social_events.invalid_menu');
        }
        if ($tableId !== null && !DB::$socialEvents->tableBelongsToEvent($tableId, $socialEventId)) {
            return $this->invalidField('tableId', 'social_events.invalid_table');
        }
        if (DB::$socialEvents->isFull($socialEventId)) {
            return Response::apiError('social_events.event_full', HttpStatus::CONFLICT);
        }
        if ($tableId !== null && DB::$socialEvents->isTableFull($tableId)) {
            return Response::apiError('social_events.table_full', HttpStatus::CONFLICT);
        }
        return null;
    }

    private function detail(int $id, int $userId): ?SocialEventDetailDto
    {
        $socialEvent = DB::$socialEvents->get($id, $userId);
        if ($socialEvent === null) {
            return null;
        }

        $mine = DB::$socialEvents->getUserRegistration($id, $userId);

        return new SocialEventDetailDto(
            SocialEventSummaryDto::fromSocialEvent($socialEvent),
            $socialEvent->description,
            ApiDate::dateTime($socialEvent->registrationClose),
            array_map(MenuDto::fromMenu(...), DB::$socialEvents->menus($id)),
            array_map(TableDto::fromTable(...), DB::$socialEvents->tables($id)),
            $mine === null ? null : SocialRegistrationDto::fromRegistration($mine),
            array_map(SocialParticipantDto::fromRegistration(...), DB::$socialEvents->registrations($id)),
        );
    }
}
