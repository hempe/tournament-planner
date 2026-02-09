<?php

declare(strict_types=1);

namespace GolfElFaro\Middleware;

use GolfElFaro\Core\MiddlewareInterface;
use GolfElFaro\Core\Request;
use GolfElFaro\Core\Response;
use GolfElFaro\Models\User;

final class AdminMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!User::admin()) {
            if ($request->isAjax()) {
                return Response::forbidden(__('errors.unauthorized'));
            }
            return Response::forbidden(__('errors.unauthorized'));
        }

        return $next($request);
    }
}