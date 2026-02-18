<?php

declare(strict_types=1);

namespace TP\Controllers;

use TP\Core\Request;
use TP\Core\Response;
use TP\Core\Attributes\Get;
use TP\Models\User;
use TP\Models\DB;
use TP\Components\GuestCalendarEvent;
use DateTime;

final class HomeController
{
    #[Get('/')]
    public function index(Request $request): Response
    {
        $dateStr = $request->getString('date', date('Y-m-1'));
        $date = new DateTime($dateStr);

        if (User::loggedIn()) {
            $events = DB::$events->all($date);
            ob_start();
            require __DIR__ . '/../Layout/header.php';
            require __DIR__ . '/../Views/Home/Home.php';
            require __DIR__ . '/../Layout/footer.php';
            $content = ob_get_clean();
        } else {
            $events = DB::$events->allForGuest($date);
            $eventRenderer = fn($event) => new GuestCalendarEvent($event);
            ob_start();
            require __DIR__ . '/../Layout/header.php';
            require __DIR__ . '/../Views/Guests/Home.php';
            require __DIR__ . '/../Layout/footer.php';
            $content = ob_get_clean();
        }

        return Response::ok($content);
    }

    #[Get('/guest')]
    public function guest(Request $request): Response
    {
        $query = http_build_query($request->getQuery());
        return Response::redirect('/' . ($query ? "?{$query}" : ''));
    }
}
