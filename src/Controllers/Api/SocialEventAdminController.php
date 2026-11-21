<?php

declare(strict_types=1);

namespace TP\Controllers\Api;

use TP\Api\Dto\AdminSocialRegistrationDto;
use TP\Api\Dto\ApiDate;
use TP\Api\Dto\MenuDto;
use TP\Api\Dto\SocialEventAdminDto;
use TP\Api\Dto\SocialEventRequest;
use TP\Api\Dto\SocialEventSummaryDto;
use TP\Api\Dto\TableDto;
use TP\Core\Attributes\Body;
use TP\Core\Attributes\Delete;
use TP\Core\Attributes\Get;
use TP\Core\Attributes\Middleware;
use TP\Core\Attributes\Post;
use TP\Core\Attributes\Put;
use TP\Core\Attributes\Returns;
use TP\Core\Attributes\RoutePrefix;
use TP\Core\HttpStatus;
use TP\Core\Request;
use TP\Core\Response;
use TP\Core\UserFacingException;
use TP\Core\ValidationRule;
use TP\Middleware\AdminMiddleware;
use TP\Middleware\CsrfMiddleware;
use TP\Models\DB;
use TP\Models\User;

#[RoutePrefix('/api/social-events')]
#[Middleware(CsrfMiddleware::class)]
#[Middleware(AdminMiddleware::class)]
final class SocialEventAdminController
{
    #[Post('')]
    #[Body(SocialEventRequest::class)]
    #[Returns(SocialEventAdminDto::class)]
    public function create(Request $request): Response
    {
        $errors = $this->validate($request);
        if ($errors !== []) {
            return $this->invalid($errors);
        }

        $tournamentId = $request->getString('tournamentId');
        try {
            $id = DB::$socialEvents->add(
                $request->getString('name'),
                $request->getString('date'),
                $tournamentId !== '' ? (int) $tournamentId : null,
                $request->getString('description') ?: null,
                EventAdminController::dateTime($request->getString('registrationClose')) ?: null,
                $request->getString('menus'),
                $request->getString('tables'),
            );
        } catch (UserFacingException $e) {
            return Response::apiError($e->key, HttpStatus::CONFLICT, [], $e->params);
        }

        return Response::json($this->detail($id));
    }

    #[Get('/{id}/admin')]
    #[Returns(SocialEventAdminDto::class)]
    public function show(Request $request, array $params): Response
    {
        return $this->withSocialEvent($params, fn(int $id) => Response::json($this->detail($id)));
    }

    #[Put('/{id}')]
    #[Body(SocialEventRequest::class)]
    #[Returns(SocialEventAdminDto::class)]
    public function update(Request $request, array $params): Response
    {
        return $this->withSocialEvent($params, function (int $id) use ($request) {
            $errors = $this->validate($request);
            if ($errors !== []) {
                return $this->invalid($errors);
            }

            try {
                DB::$socialEvents->update(
                    $id,
                    $request->getString('name'),
                    $request->getString('date'),
                    $request->getString('description') ?: null,
                    EventAdminController::dateTime($request->getString('registrationClose')) ?: null,
                    $request->getString('menus'),
                    $request->getString('tables'),
                );
            } catch (UserFacingException $e) {
                return Response::apiError($e->key, HttpStatus::CONFLICT, [], $e->params);
            }

            return Response::json($this->detail($id));
        });
    }

    #[Delete('/{id}')]
    public function delete(Request $request, array $params): Response
    {
        return $this->withSocialEvent($params, function (int $id) {
            DB::$socialEvents->delete($id);
            return Response::noContent();
        });
    }

    #[Put('/{id}/lock')]
    #[Returns(SocialEventAdminDto::class)]
    public function lock(Request $request, array $params): Response
    {
        return $this->withSocialEvent($params, function (int $id) {
            DB::$socialEvents->lock($id);
            return Response::json($this->detail($id));
        });
    }

    #[Delete('/{id}/lock')]
    #[Returns(SocialEventAdminDto::class)]
    public function unlock(Request $request, array $params): Response
    {
        return $this->withSocialEvent($params, function (int $id) {
            DB::$socialEvents->unlock($id);
            return Response::json($this->detail($id));
        });
    }

    #[Delete('/{id}/registrations/{registrationId}')]
    #[Returns(SocialEventAdminDto::class)]
    public function deleteRegistration(Request $request, array $params): Response
    {
        return $this->withSocialEvent($params, function (int $id) use ($params) {
            $registrationId = (int) $params['registrationId'];
            $belongs = array_filter(
                DB::$socialEvents->registrations($id),
                fn($r) => $r->id === $registrationId
            ) !== [];
            if (!$belongs) {
                return Response::apiError('social_events.not_found', HttpStatus::NOT_FOUND);
            }

            DB::$socialEvents->deleteRegistration($registrationId);

            return Response::json($this->detail($id));
        });
    }

    /** Run `$action($id)` if the social event exists, else 404. */
    private function withSocialEvent(array $params, callable $action): Response
    {
        $id = (int) $params['id'];
        if (DB::$socialEvents->get($id, (int) User::id()) === null) {
            return Response::apiError('social_events.not_found', HttpStatus::NOT_FOUND);
        }
        return $action($id);
    }

    /** @return array<string, array{key: string, params?: array<string, scalar>}> */
    private function validate(Request $request): array
    {
        $errors = $request->validate([
            new ValidationRule('name', ['required', 'string', 'max' => 255]),
            new ValidationRule('date', ['required', 'date']),
        ])->fieldErrors();

        if (EventAdminController::dateTime($request->getString('registrationClose')) === false) {
            $errors['registrationClose'] = ['key' => 'validation.date'];
        }
        return $errors;
    }

    private function invalid(array $fields): Response
    {
        return Response::apiError('validation.failed', HttpStatus::UNPROCESSABLE_ENTITY, $fields);
    }

    private function detail(int $id): SocialEventAdminDto
    {
        $socialEvent = DB::$socialEvents->get($id, (int) User::id());

        return new SocialEventAdminDto(
            SocialEventSummaryDto::fromSocialEvent($socialEvent),
            $socialEvent->description,
            ApiDate::dateTime($socialEvent->registrationClose),
            DB::$socialEvents->getMenusAsString($id),
            DB::$socialEvents->getTablesAsString($id),
            array_map(MenuDto::fromMenu(...), DB::$socialEvents->menus($id)),
            array_map(TableDto::fromTable(...), DB::$socialEvents->tables($id)),
            array_map(AdminSocialRegistrationDto::fromRegistration(...), DB::$socialEvents->registrations($id)),
        );
    }
}
