<?php

declare(strict_types=1);

namespace TP\Middleware;

use TP\Core\HttpStatus;
use TP\Core\MiddlewareInterface;
use TP\Core\Request;
use TP\Core\Response;
use TP\Models\User;

final class AdminMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!User::admin()) {
            if ($request->isApi()) {
                return User::loggedIn()
                    ? Response::apiError('errors.forbidden', HttpStatus::FORBIDDEN)
                    : Response::apiError('auth.login_required', HttpStatus::UNAUTHORIZED);
            }
            return Response::forbidden(__('errors.unauthorized'));
        }

        return $next($request);
    }
}