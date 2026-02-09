<?php

declare(strict_types=1);

namespace GolfElFaro\Middleware;

use GolfElFaro\Core\MiddlewareInterface;
use GolfElFaro\Core\Request;
use GolfElFaro\Core\Response;
use GolfElFaro\Models\User;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!User::loggedIn()) {
            if ($request->isAjax()) {
                return Response::unauthorized(__('auth.login_required'));
            }
            return Response::redirect('/login');
        }

        return $next($request);
    }
}