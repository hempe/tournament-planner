<?php

declare(strict_types=1);

namespace TP\Controllers;

use TP\Core\Request;
use TP\Core\Response;
use TP\Models\User;
use TP\Models\DB;
use DateTime;

final class HomeController
{
    public function index(Request $request): Response
    {
        if (!User::loggedIn()) {
            return $this->loginForm($request);
        }

        $dateStr = $request->getString('date', date('Y-m-1'));
        $date = new DateTime($dateStr);
        $events = DB::$events->all($date);

        // Load the home view
        ob_start();
        $date = new DateTime($request->getString('date', date('Y') . '-' . date('m') . '-1'));
        require __DIR__ . '/../layout/header.php';
        require __DIR__ . '/../pages/home/views/home.php';
        require __DIR__ . '/../layout/footer.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }

    public function loginForm(Request $request): Response
    {
        if (User::loggedIn()) {
            return Response::redirect('/');
        }

        ob_start();
        require __DIR__ . '/../layout/header.php';
        require __DIR__ . '/../pages/home/views/login.php';
        require __DIR__ . '/../layout/footer.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }
}
