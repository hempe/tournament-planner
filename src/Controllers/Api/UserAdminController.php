<?php

declare(strict_types=1);

namespace TP\Controllers\Api;

use TP\Api\Dto\AdminUserDto;
use TP\Api\Dto\UserCreateRequest;
use TP\Api\Dto\UserUpdateRequest;
use TP\Core\Attributes\Body;
use TP\Core\Flavor;
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
        $conflicts = DB::$users->nameConflicts();
        return Response::json(array_map(
            fn(User $u) => AdminUserDto::fromUser($u, isset($conflicts[$u->id])),
            DB::$users->all(),
        ));
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
            Flavor::enabled('genderSplit') ? $request->getBool('male') : true,
            null,
            null,
            $this->nullable($request, 'firstName'),
            $this->nullable($request, 'lastName'),
        );
        DB::$users->updateExtra($id, $this->extraFields($request));

        return Response::json($this->dto(DB::$users->get($id)));
    }

    #[Get('/{id}')]
    #[Returns(AdminUserDto::class)]
    public function show(Request $request, array $params): Response
    {
        return $this->withUser($params, fn(User $user) => Response::json($this->dto($user)));
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
                Flavor::enabled('genderSplit') ? $request->getBool('male') : $user->male,
                $username,
                $this->nullable($request, 'firstName'),
                $this->nullable($request, 'lastName'),
            );
            DB::$users->updateExtra($user->id, $this->extraFields($request));
            $password = $request->getString('password');
            if ($password !== '') {
                DB::$users->setPassword($user->id, $password);
            }

            return Response::json($this->dto(DB::$users->get($user->id)));
        });
    }

    #[Put('/{id}/admin')]
    #[Returns(AdminUserDto::class)]
    public function grantAdmin(Request $request, array $params): Response
    {
        return $this->withUser($params, function (User $user) {
            DB::$users->setAdmin($user->id, true);
            return Response::json($this->dto(DB::$users->get($user->id)));
        });
    }

    #[Delete('/{id}/admin')]
    #[Returns(AdminUserDto::class)]
    public function revokeAdmin(Request $request, array $params): Response
    {
        return $this->withUser($params, function (User $user) {
            DB::$users->setAdmin($user->id, false);
            return Response::json($this->dto(DB::$users->get($user->id)));
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

    private function dto(User $user): AdminUserDto
    {
        return AdminUserDto::fromUser($user, isset(DB::$users->nameConflicts()[$user->id]));
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
        if (Flavor::enabled('genderSplit') && !$request->has('male')) {
            $errors = ['male' => ['key' => 'validation.required']] + $errors;
        }
        if (Flavor::hasUserField('phone') && mb_strlen($request->getString('phone')) > 255) {
            $errors['phone'] = ['key' => 'validation.max', 'params' => ['max' => 255]];
        }
        if (Flavor::hasUserField('handicap')) {
            $error = $this->numberError($request->get('handicap'), -10, 54, false);
            if ($error !== null) {
                $errors['handicap'] = $error;
            }
        }
        if (Flavor::hasUserField('priority')) {
            $error = $this->numberError($request->get('priority'), 0, 99999, true);
            if ($error !== null) {
                $errors['priority'] = $error;
            }
        }
        return $errors;
    }

    /** @return array{key: string, params?: array<string, scalar>}|null error for an optional number field */
    private function numberError(mixed $value, int $min, int $max, bool $integer): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($integer ? !(is_int($value) || (is_string($value) && ctype_digit($value))) : !is_numeric($value)) {
            return ['key' => $integer ? 'validation.integer' : 'validation.numeric'];
        }
        if ($value < $min) {
            return ['key' => 'validation.min_value', 'params' => ['min' => $min]];
        }
        if ($value > $max) {
            return ['key' => 'validation.max_value', 'params' => ['max' => $max]];
        }
        return null;
    }

    /**
     * The flavour's optional user fields from the request (others are left untouched in the database).
     *
     * @return array<string, string|null>
     */
    private function extraFields(Request $request): array
    {
        $extra = [];
        foreach (Flavor::USER_FIELDS as $field) {
            if (!Flavor::hasUserField($field)) {
                continue;
            }
            $value = $request->get($field);
            $extra[$field] = match (true) {
                $value === null, $value === '' => null,
                is_int($value), is_float($value) => (string) $value,
                default => trim((string) $value) ?: null,
            };
        }
        return $extra;
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
