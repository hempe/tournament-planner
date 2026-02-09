<?php

function getEventRoutes(string $method): RouterBuilder
{
    $routes =  new RouterBuilder('events', dirname(__FILE__), $method);
    return $routes
        ->view(
            title: 'Anlässe',
            file: 'list.php',
            require: User::admin()
        )
        // Create new event
        ->view(
            title: 'Neuer Anlass',
            route: '/new',
            file: 'new.php',
            require: User::admin(),
            actions: [
                '' => [
                    'callback' => fn() => $_GET['id'] = DB::$events->add($_POST['name'], $_POST['date'], $_POST['capacity']),
                    'redirect' => '/{id}'
                ]
            ]
        )
        // Event details
        ->view(
            route: '/{id}',
            file: 'admin.php',
            title: 'Anlass',
            require: User::admin(),
            actions: [
                '' =>  fn($id, $name, $capacity) => DB::$events->update($id, $name, $capacity),
                'delete' => [
                    'callback' => fn($id) => DB::$events->delete($id),
                    'redirect' => '/'
                ],
                'lock' => fn($id) => DB::$events->lock($id),
                'unlock' => fn($id) => DB::$events->unlock($id),
                'user/join' => fn($id, $userId, $comment) => DB::$events->register($id, $userId, $comment),
                'user/comment' => fn($id, $userId, $comment) => DB::$events->updateRegistrationComment($id, $userId, $comment),
                'user/remove' => fn($id, $userId) => DB::$events->unregister($id,  $userId),
            ]
        )
        ->view(
            route: '/{id}',
            file: 'detail.php',
            title: 'Anlass',
            require: User::loggedIn(),
            actions: [
                'user/join' => [
                    'require' => fn($userId) => User::canEdit($userId),
                    'callback' => function ($id, $userId, $comment) {
                        if (DB::$events->isLocked($id)) {
                            $_SESSION['popup_error'] = "Anmeldung geschlossen, bitte bei kurzfristigen Änderungen oder Kommentaren anrufen!";
                        } else {
                            DB::$events->register($id, $userId, $comment);
                        }
                    }
                ],
                'user/comment' => [
                    'require' => fn($userId) => User::canEdit($userId),
                    'callback' => function ($id, $userId, $comment) {
                        if (DB::$events->isLocked($id)) {
                            $_SESSION['popup_error'] = "Anmeldung geschlossen, bitte bei kurzfristigen Änderungen oder Kommentaren anrufen!";
                        } else {
                            DB::$events->updateRegistrationComment($id, $userId, $comment);
                        }
                    }
                ],
                'user/remove' => [
                    'require' => fn($userId) => User::canEdit($userId),
                    'callback' => function ($id, $userId) {
                        if (DB::$events->isLocked($id)) {
                            $_SESSION['popup_error'] = "Anmeldung geschlossen, bitte bei kurzfristigen Änderungen oder Kommentaren anrufen!";
                        } else {
                            DB::$events->unregister($id,  $userId);
                        }
                    }
                ]
            ]
        );
}
