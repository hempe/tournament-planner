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
use TP\Middleware\AuthMiddleware;
use TP\Middleware\AdminMiddleware;
use TP\Models\User;
use TP\Models\DB;
use Exception;
use function PHPUnit\Framework\returnArgument;

#[RoutePrefix('/events')]
#[Middleware(AuthMiddleware::class)]
final class EventController
{
    /**
     * Build event detail URL with preserved query parameters
     */
    private function buildEventUrl(int $eventId, Request $request): string
    {
        $url = "/events/{$eventId}";
        $params = [];

        // Preserve iframe parameter
        if ($request->get('iframe') === '1') {
            $params[] = 'iframe=1';
        }

        // Preserve back date parameter
        if ($backDate = $request->getString('b')) {
            $params[] = 'b=' . urlencode($backDate);
        }

        if (!empty($params)) {
            $url .= '?' . implode('&', $params);
        }

        return $url;
    }

    #[Get('/')]
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

    #[Get('/{id}')]
    public function detail(Request $request, array $params): Response
    {
        if (User::admin()) {
            return $this->admin($request, $params);
        }

        $eventId = (int) $params['id'];
        $userId = User::id();

        if ($userId === null) {
            return Response::unauthorized();
        }

        $event = DB::$events->get($eventId, $userId);

        if (!$event) {
            logger()->error('Could not find event: ' . $eventId);
            return Response::notFound(__('events.not_found'));
        }

        require __DIR__ . '/../Layout/header.php';

        ob_start();
        $id = $eventId;
        require __DIR__ . '/../Views/Events/Detail.php';
        $content = ob_get_clean();

        require __DIR__ . '/../Layout/footer.php';

        return Response::ok($content);
    }

    #[Get('/new')]
    #[Middleware(AdminMiddleware::class)]
    public function create(Request $request): Response
    {
        ob_start();
        require __DIR__ . '/../Layout/header.php';
        require __DIR__ . '/../Views/Events/New.php';
        require __DIR__ . '/../Layout/footer.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }

    #[Post('/new')]
    #[Middleware(AdminMiddleware::class)]
    public function store(Request $request): Response
    {
        $validation = $request->validate([
            new ValidationRule('name', ['required', 'string', 'max' => 255]),
            new ValidationRule('date', ['required', 'date']),
            new ValidationRule('capacity', ['required', 'integer', 'min' => 1]),
            new ValidationRule('mixed', ['boolean']),
        ]);

        if (!$validation->isValid) {
            flash('error', $validation->getErrorMessages());
            return Response::redirect('/events/new');
        }

        try {
            $data = $request->getValidatedData();
            $mixed = ($data['mixed'] ?? '0') === '1';
            $eventId = DB::$events->add($data['name'], $data['date'], (int) $data['capacity'], false, $mixed);

            return Response::redirect("/events/{$eventId}");
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect('/events/new');
        }
    }

    #[Get('/{id}/admin')]
    #[Middleware(AdminMiddleware::class)]
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

    #[Post('/{id}')]
    #[Middleware(AdminMiddleware::class)]
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

    #[Post('/{id}/delete')]
    #[Middleware(AdminMiddleware::class)]
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

    #[Get('/{id}/export')]
    #[Middleware(AdminMiddleware::class)]
    public function export(Request $request, array $params): Response
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

        $registrations = DB::$events->registrations($eventId);
        $guests = DB::$guests->allForEvent($eventId);

        $csvField = fn(string $s): string => '"' . str_replace('"', '""', $s) . '"';
        $isoDate = fn(string $ts): string => (new \DateTime($ts))->format('Y-m-d\TH:i:s');
        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $event->date . '_' . $event->name) . '.csv';

        $rows = [];
        $rows[] = implode(',', [
            $csvField(__('events.export_col_name')),
            $csvField(__('events.export_col_gender')),
            $csvField(__('events.export_col_is_guest')),
            $csvField(__('users.member_number')),
            $csvField(__('users.rfeg')),
            $csvField(__('events.export_col_timestamp')),
        ]);

        foreach ($registrations as $reg) {
            $rows[] = implode(',', [
                $csvField($reg->name),
                $csvField($reg->male ? __('users.mr') : __('users.mrs')),
                $csvField(__('events.export_no')),
                $csvField($reg->memberNumber ?? ''),
                $csvField($reg->rfeg ?? ''),
                $csvField($isoDate($reg->timestamp)),
            ]);
        }

        foreach ($guests as $guest) {
            $rows[] = implode(',', [
                $csvField($guest->firstName . ' ' . $guest->lastName),
                $csvField($guest->male ? __('users.mr') : __('users.mrs')),
                $csvField(__('events.export_yes')),
                $csvField(''),
                $csvField($guest->rfeg ?? ''),
                $csvField($isoDate($guest->timestamp)),
            ]);
        }

        return Response::ok("\xEF\xBB\xBF" . implode("\r\n", $rows), [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Post('/{id}/lock')]
    #[Middleware(AdminMiddleware::class)]
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

    #[Post('/{id}/unlock')]
    #[Middleware(AdminMiddleware::class)]
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

    #[Post('/{id}/register')]
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
                return Response::redirect($this->buildEventUrl($eventId, $request));
            }

            DB::$events->register($eventId, $userId, $comment);
            flash('success', __('events.registration_success'));
            return Response::redirect($this->buildEventUrl($eventId, $request));
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect($this->buildEventUrl($eventId, $request));
        }
    }

    #[Post('/{id}/unregister')]
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
                return Response::redirect($this->buildEventUrl($eventId, $request));
            }

            DB::$events->unregister($eventId, $userId);
            flash('success', __('events.unregistration_success'));
            return Response::redirect($this->buildEventUrl($eventId, $request));
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect($this->buildEventUrl($eventId, $request));
        }
    }

    #[Post('/{id}/comment')]
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
                return Response::redirect($this->buildEventUrl($eventId, $request));
            }

            DB::$events->updateRegistrationComment($eventId, $userId, $comment);
            flash('success', __('events.comment_update_success'));
            return Response::redirect($this->buildEventUrl($eventId, $request));
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect($this->buildEventUrl($eventId, $request));
        }
    }

    #[Get('/bulk/new')]
    #[Middleware(AdminMiddleware::class)]
    public function bulkCreate(Request $request): Response
    {
        ob_start();
        require __DIR__ . '/../Layout/header.php';
        require __DIR__ . '/../Views/Events/BulkNew.php';
        require __DIR__ . '/../Layout/footer.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }

    #[Post('/bulk/preview')]
    #[Middleware(AdminMiddleware::class)]
    public function bulkPreview(Request $request): Response
    {
        $validation = $request->validate([
            new ValidationRule('start_date', ['required', 'date']),
            new ValidationRule('end_date', ['required', 'date']),
            new ValidationRule('day_of_week', ['required', 'integer', 'min' => 0, 'max' => 6]),
            new ValidationRule('name', ['required', 'string', 'max' => 255]),
            new ValidationRule('capacity', ['required', 'integer', 'min' => 1]),
            new ValidationRule('mixed', ['boolean']),
        ]);

        if (!$validation->isValid) {
            flash('error', $validation->getErrorMessages());
            return Response::redirect('/events/bulk/new');
        }

        try {
            $data = $request->getValidatedData();
            $mixed = ($data['mixed'] ?? '0') === '1';
            $events = $this->calculateRecurringDates(
                $data['start_date'],
                $data['end_date'],
                (int) $data['day_of_week'],
                $data['name'],
                (int) $data['capacity'],
                $mixed
            );

            $_SESSION['bulk_events'] = $events;

            ob_start();
            require __DIR__ . '/../Layout/header.php';
            require __DIR__ . '/../Views/Events/BulkPreview.php';
            require __DIR__ . '/../Layout/footer.php';
            $content = ob_get_clean();

            return Response::ok($content);
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect('/events/bulk/new');
        }
    }

    #[Post('/bulk/store')]
    #[Middleware(AdminMiddleware::class)]
    public function bulkStore(Request $request): Response
    {
        if (!isset($_SESSION['bulk_events']) || !is_array($_SESSION['bulk_events'])) {
            flash('error', __('events.bulk_session_expired'));
            return Response::redirect('/events/bulk/new');
        }

        $events = $_SESSION['bulk_events'];
        $successCount = 0;
        $failures = [];

        foreach ($events as $event) {
            try {
                DB::$events->add($event['name'], $event['date'], $event['capacity'], true, (bool) ($event['mixed'] ?? true));
                $successCount++;
            } catch (Exception $e) {
                $failures[] = "Failed to create event on {$event['date']}: " . $e->getMessage();
            }
        }

        unset($_SESSION['bulk_events']);

        if ($successCount > 0) {
            flash('success', __('events.bulk_create_success', ['count' => $successCount]));
        }

        if (!empty($failures)) {
            flash('error', $failures);
        }

        return Response::redirect('/events');
    }

    private function calculateRecurringDates(
        string $startDate,
        string $endDate,
        int $dayOfWeek,
        string $name,
        int $capacity,
        bool $mixed = true
    ): array {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $events = [];

        // Find first occurrence of target day-of-week on/after start date
        $current = clone $start;
        $currentDayOfWeek = (int) $current->format('w');

        if ($currentDayOfWeek !== $dayOfWeek) {
            $daysToAdd = ($dayOfWeek - $currentDayOfWeek + 7) % 7;
            $current->modify("+{$daysToAdd} days");
        }

        // Loop with +7 days increment until end date exceeded
        while ($current <= $end) {
            $events[] = [
                'name' => $name,
                'date' => $current->format('Y-m-d'),
                'capacity' => $capacity,
                'mixed' => $mixed,
            ];
            $current->modify('+7 days');
        }

        return $events;
    }
}