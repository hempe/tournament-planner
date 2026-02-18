<?php

declare(strict_types=1);

namespace TP\Controllers;

use TP\Core\Request;
use TP\Core\Response;
use TP\Core\ValidationRule;
use TP\Core\Attributes\RoutePrefix;
use TP\Core\Attributes\Get;
use TP\Core\Attributes\Post;
use TP\Core\Attributes\Middleware;
use TP\Middleware\AdminMiddleware;
use TP\Models\DB;
use Exception;

#[RoutePrefix('/events')]
final class GuestController
{
    private function validationRules(): array
    {
        return [
            new ValidationRule('male', ['required', 'boolean']),
            new ValidationRule('first_name', ['required', 'string', 'max' => 255]),
            new ValidationRule('last_name', ['required', 'string', 'max' => 255]),
            new ValidationRule('email', ['required', 'email', 'max' => 255]),
            new ValidationRule('handicap', ['required', 'string']),
            new ValidationRule('rfeg', ['string', 'max' => 255]),
            new ValidationRule('comment', ['string', 'max' => 2048]),
        ];
    }

    #[Get('/{id}/guests/new')]
    public function create(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];
        $event = DB::$events->get($eventId, 0);

        if (!$event) {
            return Response::notFound(__('events.not_found'));
        }

        ob_start();
        require __DIR__ . '/../Layout/header.php';
        require __DIR__ . '/../Views/Guests/New.php';
        require __DIR__ . '/../Layout/footer.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }

    #[Post('/{id}/guests/new')]
    public function store(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];

        $validation = $request->validate($this->validationRules());

        if (!$validation->isValid) {
            flash('error', $validation->getErrorMessages());
            return Response::redirect("/events/{$eventId}/guests/new");
        }

        try {
            $data = $request->getValidatedData();
            DB::$guests->add(
                $eventId,
                (bool) $data['male'],
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                (float) $data['handicap'],
                $data['rfeg'] ?? null,
                $data['comment'] ?? null,
            );

            flash('success', __('guests.register_success'));
            return Response::redirect('/');
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect("/events/{$eventId}/guests/new");
        }
    }

    #[Get('/{id}/guests/{guestId}/edit')]
    #[Middleware(AdminMiddleware::class)]
    public function edit(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];
        $guestId = (int) $params['guestId'];

        $event = DB::$events->get($eventId, 0);
        if (!$event) {
            return Response::notFound(__('events.not_found'));
        }

        $guest = DB::$guests->get($guestId);
        if (!$guest) {
            return Response::notFound(__('guests.not_found'));
        }

        ob_start();
        require __DIR__ . '/../Layout/header.php';
        require __DIR__ . '/../Views/Guests/Edit.php';
        require __DIR__ . '/../Layout/footer.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }

    #[Post('/{id}/guests/{guestId}/update')]
    #[Middleware(AdminMiddleware::class)]
    public function update(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];
        $guestId = (int) $params['guestId'];

        $validation = $request->validate($this->validationRules());

        if (!$validation->isValid) {
            flash('error', $validation->getErrorMessages());
            return Response::redirect("/events/{$eventId}/guests/{$guestId}/edit");
        }

        try {
            $data = $request->getValidatedData();
            DB::$guests->update(
                $guestId,
                (bool) $data['male'],
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                (float) $data['handicap'],
                $data['rfeg'] ?? null,
                $data['comment'] ?? null,
            );

            flash('success', __('guests.update_success'));
            return Response::redirect("/events/{$eventId}");
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect("/events/{$eventId}/guests/{$guestId}/edit");
        }
    }

    #[Post('/{id}/guests/{guestId}/delete')]
    #[Middleware(AdminMiddleware::class)]
    public function deleteGuest(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];
        $guestId = (int) $params['guestId'];

        try {
            DB::$guests->delete($guestId);
            flash('success', __('guests.delete_success'));
            return Response::redirect("/events/{$eventId}");
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect("/events/{$eventId}");
        }
    }
}
