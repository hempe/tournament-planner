<?php

return array (
  'routes' => 
  array (
    0 => 
    array (
      'method' => 'GET',
      'pattern' => '/',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\HomeController',
        1 => 'index',
      ),
      'middleware' => 
      array (
      ),
      'name' => '',
    ),
    1 => 
    array (
      'method' => 'GET',
      'pattern' => '/login',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\AuthController',
        1 => 'loginForm',
      ),
      'middleware' => 
      array (
      ),
      'name' => '',
    ),
    2 => 
    array (
      'method' => 'POST',
      'pattern' => '/login',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\AuthController',
        1 => 'login',
      ),
      'middleware' => 
      array (
      ),
      'name' => '',
    ),
    3 => 
    array (
      'method' => 'POST',
      'pattern' => '/logout',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\AuthController',
        1 => 'logout',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    4 => 
    array (
      'method' => 'GET',
      'pattern' => '/events',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\EventController',
        1 => 'index',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    5 => 
    array (
      'method' => 'GET',
      'pattern' => '/events/{id}',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\EventController',
        1 => 'detail',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    6 => 
    array (
      'method' => 'GET',
      'pattern' => '/events/new',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\EventController',
        1 => 'create',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
        1 => 
        \TP\Middleware\AdminMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    7 => 
    array (
      'method' => 'POST',
      'pattern' => '/events/new',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\EventController',
        1 => 'store',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
        1 => 
        \TP\Middleware\AdminMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    8 => 
    array (
      'method' => 'GET',
      'pattern' => '/events/{id}/admin',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\EventController',
        1 => 'admin',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
        1 => 
        \TP\Middleware\AdminMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    9 => 
    array (
      'method' => 'POST',
      'pattern' => '/events/{id}/update',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\EventController',
        1 => 'update',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
        1 => 
        \TP\Middleware\AdminMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    10 => 
    array (
      'method' => 'POST',
      'pattern' => '/events/{id}/delete',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\EventController',
        1 => 'delete',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
        1 => 
        \TP\Middleware\AdminMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    11 => 
    array (
      'method' => 'POST',
      'pattern' => '/events/{id}/lock',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\EventController',
        1 => 'lock',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
        1 => 
        \TP\Middleware\AdminMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    12 => 
    array (
      'method' => 'POST',
      'pattern' => '/events/{id}/unlock',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\EventController',
        1 => 'unlock',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
        1 => 
        \TP\Middleware\AdminMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    13 => 
    array (
      'method' => 'POST',
      'pattern' => '/events/{id}/register',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\EventController',
        1 => 'register',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    14 => 
    array (
      'method' => 'POST',
      'pattern' => '/events/{id}/unregister',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\EventController',
        1 => 'unregister',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    15 => 
    array (
      'method' => 'POST',
      'pattern' => '/events/{id}/comment',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\EventController',
        1 => 'updateComment',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    16 => 
    array (
      'method' => 'GET',
      'pattern' => '/events/bulk/new',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\EventController',
        1 => 'bulkCreate',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
        1 => 
        \TP\Middleware\AdminMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    17 => 
    array (
      'method' => 'POST',
      'pattern' => '/events/bulk/preview',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\EventController',
        1 => 'bulkPreview',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
        1 => 
        \TP\Middleware\AdminMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    18 => 
    array (
      'method' => 'POST',
      'pattern' => '/events/bulk/store',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\EventController',
        1 => 'bulkStore',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
        1 => 
        \TP\Middleware\AdminMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    19 => 
    array (
      'method' => 'GET',
      'pattern' => '/users',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\UserController',
        1 => 'index',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
        1 => 
        \TP\Middleware\AdminMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    20 => 
    array (
      'method' => 'GET',
      'pattern' => '/users/new',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\UserController',
        1 => 'create',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
        1 => 
        \TP\Middleware\AdminMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    21 => 
    array (
      'method' => 'POST',
      'pattern' => '/users',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\UserController',
        1 => 'store',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
        1 => 
        \TP\Middleware\AdminMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    22 => 
    array (
      'method' => 'POST',
      'pattern' => '/users/{id}/delete',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\UserController',
        1 => 'delete',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
        1 => 
        \TP\Middleware\AdminMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    23 => 
    array (
      'method' => 'POST',
      'pattern' => '/users/{id}/admin',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\UserController',
        1 => 'toggleAdmin',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
        1 => 
        \TP\Middleware\AdminMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    24 => 
    array (
      'method' => 'POST',
      'pattern' => '/users/{id}/password',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\UserController',
        1 => 'changePassword',
      ),
      'middleware' => 
      array (
        0 => 
        \TP\Middleware\AuthMiddleware::__set_state(array(
        )),
        1 => 
        \TP\Middleware\AdminMiddleware::__set_state(array(
        )),
      ),
      'name' => '',
    ),
    25 => 
    array (
      'method' => 'POST',
      'pattern' => '/language/switch',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\LanguageController',
        1 => 'switchLanguage',
      ),
      'middleware' => 
      array (
      ),
      'name' => '',
    ),
    26 => 
    array (
      'method' => 'GET',
      'pattern' => '/language/current',
      'handler' => 
      array (
        0 => 'TP\\Controllers\\LanguageController',
        1 => 'getCurrentLanguage',
      ),
      'middleware' => 
      array (
      ),
      'name' => '',
    ),
  ),
  'timestamps' => 
  array (
    '/home/hempe/git/tournament-planner/src/Core/../Controllers/HomeController.php' => 1771016659,
    '/home/hempe/git/tournament-planner/src/Core/../Controllers/AuthController.php' => 1771016666,
    '/home/hempe/git/tournament-planner/src/Core/../Controllers/EventController.php' => 1771150037,
    '/home/hempe/git/tournament-planner/src/Core/../Controllers/UserController.php' => 1771151565,
    '/home/hempe/git/tournament-planner/src/Core/../Controllers/LanguageController.php' => 1771186031,
  ),
);
