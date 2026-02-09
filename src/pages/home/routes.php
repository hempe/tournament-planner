<?php

function getHomeRoutes(string $method): RouterBuilder
{

    $routes = new RouterBuilder('',  dirname(__FILE__), $method);
    return $routes
        ->view(
            title: 'Golf el Fargo',
            file: 'home.php',
            require: User::loggedIn(),
            actions: [
                'logout' => fn() => session_destroy()
            ]
        )
        ->view(
            title: 'Golf el Fargo',
            file: 'login.php',
            require: !User::loggedIn(),
            actions: [
                '' => function ($username, $password) {
                    if (empty($username) || empty($password))
                        throw new Exception("Username and password are required");

                    list($user, $hashed_password) = DB::$users->getWithPassword($username);
                    if ($user &&  $hashed_password && password_verify($password, $hashed_password)) {
                        session_regenerate_id(true);
                        User::setCurrent($user);
                    } else {
                        throw new Exception("Login failed");
                    }
                }
            ]
        );
}
