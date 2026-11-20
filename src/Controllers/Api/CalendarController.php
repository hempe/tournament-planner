<?php

declare(strict_types=1);

namespace TP\Controllers\Api;

use DateTime;
use TP\Api\Dto\CalendarDto;
use TP\Api\Dto\EventSummaryDto;
use TP\Api\Dto\SocialEventSummaryDto;
use TP\Core\Attributes\Get;
use TP\Core\Attributes\Middleware;
use TP\Core\Attributes\Returns;
use TP\Core\Attributes\RoutePrefix;
use TP\Core\HttpStatus;
use TP\Core\Request;
use TP\Core\Response;
use TP\Middleware\CsrfMiddleware;
use TP\Models\DB;
use TP\Models\User;

#[RoutePrefix('/api/calendar')]
#[Middleware(CsrfMiddleware::class)]
final class CalendarController
{
    #[Get('/{month}')]
    #[Returns(CalendarDto::class)]
    public function month(Request $request, array $params): Response
    {
        $month = (string) $params['month'];
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return Response::apiError('errors.not_found', HttpStatus::NOT_FOUND);
        }

        $date = new DateTime("{$month}-01");
        $loggedIn = User::loggedIn();
        $events = $loggedIn ? DB::$events->all($date) : DB::$events->allForGuest($date);
        $socialEvents = $loggedIn ? DB::$socialEvents->all($date) : DB::$socialEvents->allForGuest($date);

        $byDate = fn(object $a, object $b): int => [$a->date, $a->id] <=> [$b->date, $b->id];
        usort($events, $byDate);
        usort($socialEvents, $byDate);

        return Response::json(new CalendarDto(
            $month,
            array_map(EventSummaryDto::fromEvent(...), $events),
            array_map(SocialEventSummaryDto::fromSocialEvent(...), $socialEvents),
        ));
    }
}
