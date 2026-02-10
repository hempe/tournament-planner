<?php

declare(strict_types=1);

namespace TP\Controllers;

use TP\Core\Request;
use TP\Core\Response;
use TP\Core\ValidationRule;
use TP\Models\User;
use TP\Models\DB;
use Exception;

final class EventController
{
    public function index(Request $request): Response
    {
        $events = DB::$events->all();

        ob_start();
        require __DIR__ . '/../Layout/header.php';
        require __DIR__ . '/../Views/Events/List.php';
        require __DIR__ . '/../Layout/footer.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }

    public function detail(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];
        $userId = User::id();

        if ($userId === null) {
            return Response::unauthorized();
        }

        $event = DB::$events->get($eventId, $userId);

        if (!$event) {
            return Response::notFound(__('events.not_found'));
        }

        require __DIR__ . '/../Layout/header.php';
        // Determine which view to show based on user permissions
        if (User::admin()) {
            ob_start();
            $id = $eventId;
            require __DIR__ . '/../Views/Events/Admin.php';
            $content = ob_get_clean();
        } else {
            ob_start();
            $id = $eventId;
            require __DIR__ . '/../Views/Events/Detail.php';
            $content = ob_get_clean();
        }

        require __DIR__ . '/../Layout/footer.php';

        return Response::ok($content);
    }

    public function create(Request $request): Response
    {
        ob_start();
        require __DIR__ . '/../Layout/header.php';
        require __DIR__ . '/../Views/Events/New.php';
        require __DIR__ . '/../Layout/footer.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }

    public function store(Request $request): Response
    {
        $validation = $request->validate([
            new ValidationRule('name', ['required', 'string', 'max' => 255]),
            new ValidationRule('date', ['required', 'date']),
            new ValidationRule('capacity', ['required', 'integer', 'min' => 1]),
        ]);

        if (!$validation->isValid) {
            flash('error', $validation->getErrorMessages());
            return Response::redirect('/events/new');
        }

        try {
            $data = $request->getValidatedData();
            $eventId = DB::$events->add($data['name'], $data['date'], (int) $data['capacity']);

            flash('success', __('events.create_success'));
            return Response::redirect("/events/{$eventId}");
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect('/events/new');
        }
    }

    public function admin(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];
        $userId = User::id();

        if ($userId === null) {
            return Response::unauthorized();
        }

        $event = DB::$events->get($eventId, $userId);

        if (!$event) {
            return Response::notFound(__('events.not_found'));
        }

        ob_start();
        $id = $eventId;
        require __DIR__ . '/../Layout/header.php';
        require __DIR__ . '/../Views/Events/Admin.php';
        require __DIR__ . '/../Layout/footer.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }

    public function update(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];

        $validation = $request->validate([
            new ValidationRule('name', ['required', 'string', 'max' => 255]),
            new ValidationRule('capacity', ['required', 'integer', 'min' => 1]),
        ]);

        if (!$validation->isValid) {
            flash('error', $validation->getErrorMessages());
            return Response::redirect("/events/{$eventId}");
        }

        try {
            $data = $request->getValidatedData();
            DB::$events->update($eventId, $data['name'], (int) $data['capacity']);

            flash('success', __('events.update_success'));
            return Response::redirect("/events/{$eventId}");
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect("/events/{$eventId}");
        }
    }

    public function delete(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];

        try {
            DB::$events->delete($eventId);
            flash('success', __('events.delete_success'));
            return Response::redirect('/events');
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect("/events/{$eventId}");
        }
    }

    public function lock(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];

        try {
            DB::$events->lock($eventId);
            flash('success', __('events.lock_success'));
            return Response::redirect("/events/{$eventId}");
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect("/events/{$eventId}");
        }
    }

    public function unlock(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];

        try {
            DB::$events->unlock($eventId);
            flash('success', __('events.unlock_success'));
            return Response::redirect("/events/{$eventId}");
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect("/events/{$eventId}");
        }
    }

    public function register(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];
        $userId = $request->getInt('userId') ?: User::id();
        $comment = $request->getString('comment', '');

        if ($userId === null) {
            return Response::unauthorized();
        }

        // Check if user can edit this registration
        if (!User::canEdit($userId)) {
            return Response::forbidden();
        }

        try {
            // Check if event is locked
            if (DB::$events->isLocked($eventId)) {
                flash('error', __('events.locked_message'));
                return Response::redirect("/events/{$eventId}");
            }

            DB::$events->register($eventId, $userId, $comment);
            flash('success', __('events.registration_success'));
            return Response::redirect("/events/{$eventId}");
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect("/events/{$eventId}");
        }
    }

    public function unregister(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];
        $userId = $request->getInt('userId') ?: User::id();

        if ($userId === null) {
            return Response::unauthorized();
        }

        // Check if user can edit this registration
        if (!User::canEdit($userId)) {
            return Response::forbidden();
        }

        try {
            // Check if event is locked
            if (DB::$events->isLocked($eventId)) {
                flash('error', __('events.locked_message'));
                return Response::redirect("/events/{$eventId}");
            }

            DB::$events->unregister($eventId, $userId);
            flash('success', __('events.unregistration_success'));
            return Response::redirect("/events/{$eventId}");
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect("/events/{$eventId}");
        }
    }

    public function updateComment(Request $request, array $params): Response
    {
        $eventId = (int) $params['id'];
        $userId = $request->getInt('userId') ?: User::id();
        $comment = $request->getString('comment', '');

        if ($userId === null) {
            return Response::unauthorized();
        }

        // Check if user can edit this registration
        if (!User::canEdit($userId)) {
            return Response::forbidden();
        }

        try {
            // Check if event is locked
            if (DB::$events->isLocked($eventId)) {
                flash('error', __('events.locked_message'));
                return Response::redirect("/events/{$eventId}");
            }

            DB::$events->updateRegistrationComment($eventId, $userId, $comment);
            flash('success', __('events.comment_update_success'));
            return Response::redirect("/events/{$eventId}");
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect("/events/{$eventId}");
        }
    }
}