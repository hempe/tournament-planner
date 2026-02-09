<?php
function getUserRoutes(string $method): RouterBuilder
{
    $routes = new RouterBuilder('users',  dirname(__FILE__), $method);

    return $routes
        ->view(
            title: 'Benutzer',
            file: 'list.php',
            require: User::admin(),
            actions: [
                'delete' => fn() => DB::$users->delete($_POST['id']),
                'admin'  => fn() => DB::$users->setAdmin($_POST['id'], $_POST['admin']),
                'password'  => fn() => DB::$users->setPassword($_POST['id'], $_POST['password']),
            ]
        )
        ->view(
            title: 'Neuer Benutzer',
            route: '/new',
            file: 'new.php',
            require: User::admin(),
            actions: [
                '' => [
                    'redirect' => '/',
                    'callback' => function ($username, $password) {
                        $username = trim($username);
                        $password = trim($password);
                        if (DB::$users->userNameAlreadyTaken($username)) {
                            throw new Exception("Benutzer '" . $username . "' existiert bereits.");
                        } else {
                            // Simple validation
                            if (empty($username) || empty($password)) {
                                throw new Exception("Füllen sie bitte alle felder aus.");
                            } elseif (strlen($password) < 1) {
                                throw new Exception("Passwort muss mindestens 1 Zeichen lang sein.");
                            } else {
                                $userId = DB::$users->create($username, $password);

                                if ($userId) {
                                    header('Location: /users' . $userId, true, 303);
                                } else {
                                    $_SESSION['registration_error'] = "Registrierung fehlgeschalgen.";
                                    throw new Exception("Registrierung fehlgeschalgen.");
                                }
                            }
                        }
                    }
                ]
            ]
        );
}
