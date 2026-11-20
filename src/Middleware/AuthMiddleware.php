<?php

declare(strict_types=1);

namespace TP\Middleware;

use TP\Core\HttpStatus;
use TP\Core\MiddlewareInterface;
use TP\Core\Request;
use TP\Core\Response;
use TP\Models\User;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!User::loggedIn()) {
            if ($request->isApi()) {
                return Response::apiError('auth.login_required', HttpStatus::UNAUTHORIZED);
            }
            if ($request->isAjax()) {
                return Response::unauthorized(__('auth.login_required'));
            }
            return Response::redirect('/login');
        }

        return $next($request);
    }
}