<?php


function getRouterBuilder(string $request, string $method): RouterBuilder
{
    if (str_starts_with($request, '/events')) {
        Log::trace('getRouterBuilder', 'use events router');
        require_once dirname(__FILE__) . '/events/routes.php';
        return getEventRoutes($method);
    }

    if (str_starts_with($request, '/users')) {
        Log::trace('getRouterBuilder', 'use users router');
        require_once dirname(__FILE__) . '/users/routes.php';
        return getUserRoutes($method);
    }

    Log::trace('getRouterBuilder', 'use home router');
    require_once dirname(__FILE__) . '/home/routes.php';
    return getHomeRoutes($method);
}
