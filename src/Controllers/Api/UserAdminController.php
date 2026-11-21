<?php

declare(strict_types=1);

namespace TP\Controllers\Api;

use TP\Api\Dto\AdminUserDto;
use TP\Api\Dto\UserCreateRequest;
use TP\Api\Dto\UserUpdateRequest;
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
use TP\Core\ValidationRule;
use TP\Middleware\AdminMiddleware;
use TP\Middleware\CsrfMiddleware;
use TP\Models\DB;
use TP\Models\User;

#[RoutePrefix('/api/users')]
#[Middleware(CsrfMiddleware::class)]
#[Middleware(AdminMiddleware::class)]
final class UserAdminController
{
    #[Get('')]
    #[Returns(AdminUserDto::class, list: true)]
    public function index(Request $request): Response
    {
        return Response::json(array_map(AdminUserDto::fromUser(...), DB::$users->all()));
    }

    #[Post('')]
    #[Body(UserCreateRequest::class)]
    #[Returns(AdminUserDto::class)]
    public function create(Request $request): Response
    {
        $errors = $this->validate($request, true);
        $username = trim($request->getString('username'));
        if (!isset($errors['username']) && DB::$users->userNameAlreadyTaken($username)) {
            $errors['username'] = ['key' => 'users.username_taken', 'params' => ['username' => $username]];
        }
        if ($errors !== []) {
            return $this->invalid($errors);
        }

        $id = DB::$users->create(
            $username,
            trim($request->getString('password')),
            $request->getBool('male'),
            $this->nullable($request, 'rfeg'),
            $this->nullable($request, 'memberNumber'),
            $this->nullable($request, 'firstName'),
            $this->nullable($request, 'lastName'),
        );

        return Response::json(AdminUserDto::fromUser(DB::$users->get($id)));
    }

    #[Get('/{id}')]
    #[Returns(AdminUserDto::class)]
    public function show(Request $request, array $params): Response
    {
        return $this->withUser($params, fn(User $user) => Response::json(AdminUserDto::fromUser($user)));
    }

    #[Put('/{id}')]
    #[Body(UserUpdateRequest::class)]
    #[Returns(AdminUserDto::class)]
    public function update(Request $request, array $params): Response
    {
        return $this->withUser($params, function (User $user) use ($request) {
            $errors = $this->validate($request, false);
            $username = trim($request->getString('username'));
            if (
                !isset($errors['username'])
                && $username !== $user->username
                && DB::$users->userNameAlreadyTaken($username)
            ) {
                $errors['username'] = ['key' => 'users.username_taken', 'params' => ['username' => $username]];
            }
            if ($errors !== []) {
                return $this->invalid($errors);
            }

            DB::$users->update(
                $user->id,
                $request->getBool('male'),
                $username,
                $this->nullable($request, 'rfeg'),
                $this->nullable($request, 'memberNumber'),
                $this->nullable($request, 'firstName'),
                $this->nullable($request, 'lastName'),
            );
            $password = $request->getString('password');
            if ($password !== '') {
                DB::$users->setPassword($user->id, $password);
            }

            return Response::json(AdminUserDto::fromUser(DB::$users->get($user->id)));
        });
    }

    #[Put('/{id}/admin')]
    #[Returns(AdminUserDto::class)]
    public function grantAdmin(Request $request, array $params): Response
    {
        return $this->withUser($params, function (User $user) {
            DB::$users->setAdmin($user->id, true);
            return Response::json(AdminUserDto::fromUser(DB::$users->get($user->id)));
        });
    }

    #[Delete('/{id}/admin')]
    #[Returns(AdminUserDto::class)]
    public function revokeAdmin(Request $request, array $params): Response
    {
        return $this->withUser($params, function (User $user) {
            DB::$users->setAdmin($user->id, false);
            return Response::json(AdminUserDto::fromUser(DB::$users->get($user->id)));
        });
    }

    #[Delete('/{id}')]
    public function delete(Request $request, array $params): Response
    {
        return $this->withUser($params, function (User $user) {
            // Safety rule: an admin cannot delete their own account
            if ($user->id === User::id()) {
                return Response::apiError('errors.forbidden', HttpStatus::CONFLICT);
            }
            DB::$users->delete($user->id);
            return Response::noContent();
        });
    }

    /** Run `$action($user)` if the user exists, else 404. */
    private function withUser(array $params, callable $action): Response
    {
        $user = DB::$users->get((int) $params['id']);
        if ($user === null) {
            return Response::apiError('users.not_found', HttpStatus::NOT_FOUND);
        }
        return $action($user);
    }

    /** @return array<string, array{key: string, params?: array<string, scalar>}> */
    private function validate(Request $request, bool $withPassword): array
    {
        $rules = [new ValidationRule('username', ['required', 'string', 'min' => 3, 'max' => 255])];
        if ($withPassword) {
            $rules[] = new ValidationRule('password', ['required', 'string']);
        }
        $errors = $request->validate($rules)->fieldErrors();

        // JSON `false` is sanitised to '', so check presence instead of using the boolean rule
        if (!$request->has('male')) {
            $errors = ['male' => ['key' => 'validation.required']] + $errors;
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
}
