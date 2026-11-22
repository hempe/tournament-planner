<?php

declare(strict_types=1);

namespace TP\Controllers\Api;

use TP\Api\Dto\LoginRequest;
use TP\Api\Dto\SessionDto;
use TP\Api\Dto\UserDto;
use TP\Core\Attributes\Body;
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

#[RoutePrefix('/api/auth')]
#[Middleware(CsrfMiddleware::class)]
final class AuthController
{
    private const LOGIN_MAX_FAILURES = 5;
    private const LOGIN_WINDOW_SECONDS = 900;

    #[Get('/me')]
    #[Returns(SessionDto::class)]
    public function me(Request $request): Response
    {
        $id = User::id();
        if ($id === null) {
            return Response::json(new SessionDto(null));
        }

        $user = DB::$users->get($id);
        if ($user === null) {
            // Account was deleted while logged in
            $this->resetSession();
            return Response::json(new SessionDto(null));
        }

        return Response::json(new SessionDto(UserDto::fromUser($user)));
    }

    #[Post('/login')]
    #[Body(LoginRequest::class)]
    #[Returns(SessionDto::class)]
    public function login(Request $request): Response
    {
        $validation = $request->validate([
            new ValidationRule('username', ['required', 'string']),
            new ValidationRule('password', ['required', 'string']),
        ]);

        if (!$validation->isValid) {
            return Response::apiError('validation.failed', HttpStatus::UNPROCESSABLE_ENTITY, $validation->fieldErrors());
        }

        $data = $request->getValidatedData();

        // Checked before the password so a blocked client learns nothing about correct guesses
        $limiter = RateLimiter::fromEnvironment();
        $limitKey = 'login:' . $request->clientIp() . ':' . mb_strtolower(trim($data['username']));
        if ($limiter->tooMany($limitKey, self::LOGIN_MAX_FAILURES, self::LOGIN_WINDOW_SECONDS)) {
            $minutes = (int) ceil($limiter->retryAfter($limitKey, self::LOGIN_WINDOW_SECONDS) / 60);
            return Response::apiError(
                'auth.too_many_attempts',
                HttpStatus::TOO_MANY_REQUESTS,
                params: ['minutes' => max(1, $minutes)],
            );
        }

        $user = User::authenticate($data['username'], $data['password']);

        if ($user === null) {
            $limiter->hit($limitKey, self::LOGIN_MAX_FAILURES, self::LOGIN_WINDOW_SECONDS);
            return Response::apiError('auth.login_failed', HttpStatus::UNPROCESSABLE_ENTITY);
        }

        $limiter->clear($limitKey);

        session_regenerate_id(true);
        User::setCurrent($user);

        // Reload so the response has the same shape as /me (names included)
        return Response::json(new SessionDto(UserDto::fromUser(DB::$users->get($user->id) ?? $user)));
    }

    #[Post('/logout')]
    #[Middleware(AuthMiddleware::class)]
    public function logout(Request $request): Response
    {
        $this->resetSession();
        return Response::noContent();
    }

    /**
     * Clear the session but keep it alive, so CsrfMiddleware can issue a fresh
     * token in the same response (session_destroy() would leave the client with
     * a stale token and make the next POST fail).
     */
    private function resetSession(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
