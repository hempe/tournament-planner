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
use TP\Models\DB;
use TP\Models\User;
use Exception;

#[RoutePrefix('/social-events')]
final class SocialEventController
{
    #[Get('/new')]
    #[Middleware(AdminMiddleware::class)]
    public function create(Request $request): Response
    {
        $tournamentId = $request->getString('tournamentId') !== '' ? (int) $request->getString('tournamentId') : null;
        $tournament = $tournamentId ? DB::$events->get($tournamentId, User::id() ?? 0) : null;

        ob_start();
        require __DIR__ . '/../Layout/header.php';
        require __DIR__ . '/../Views/SocialEvents/New.php';
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
            new ValidationRule('menus', ['required', 'string']),
            new ValidationRule('tables', ['required', 'string']),
        ]);

        if (!$validation->isValid) {
            flash('error', $validation->getErrorMessages());
            $qs = $request->getString('tournamentId') !== '' ? '?tournamentId=' . $request->getString('tournamentId') : '';
            return Response::redirect('/social-events/new' . $qs);
        }

        try {
            $data = $request->getValidatedData();
            $tournamentId = $request->getString('tournamentId') !== '' ? (int) $request->getString('tournamentId') : null;
            $id = DB::$socialEvents->add(
                $data['name'],
                $data['date'],
                $tournamentId,
                $request->getString('description') ?: null,
                $request->getString('registration_close') ?: null,
                $data['menus'],
                $data['tables'],
            );
            return Response::redirect("/social-events/$id");
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect('/social-events/new');
        }
    }

    #[Get('/{id}')]
    #[Middleware(AuthMiddleware::class)]
    public function detail(Request $request, array $params): Response
    {
        $socialEventId = (int) $params['id'];
        $userId = User::id();

        if ($userId === null) {
            return Response::unauthorized();
        }

        $socialEvent = DB::$socialEvents->get($socialEventId, $userId);
        if (!$socialEvent) {
            return Response::notFound(__('social_events.not_found'));
        }

        $menus = DB::$socialEvents->menus($socialEventId);
        $tables = DB::$socialEvents->tables($socialEventId);
        $registration = DB::$socialEvents->getUserRegistration($socialEventId, $userId);
        $registrations = DB::$socialEvents->registrations($socialEventId);

        ob_start();
        require __DIR__ . '/../Layout/header.php';
        require __DIR__ . '/../Views/SocialEvents/Detail.php';
        require __DIR__ . '/../Layout/footer.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }

    #[Get('/{id}/admin')]
    #[Middleware(AdminMiddleware::class)]
    public function admin(Request $request, array $params): Response
    {
        $socialEventId = (int) $params['id'];
        $userId = User::id();

        if ($userId === null) {
            return Response::unauthorized();
        }

        $socialEvent = DB::$socialEvents->get($socialEventId, $userId);
        if (!$socialEvent) {
            return Response::notFound(__('social_events.not_found'));
        }

        $registrations = DB::$socialEvents->registrations($socialEventId);
        $tables = DB::$socialEvents->tables($socialEventId);
        $menus = DB::$socialEvents->menus($socialEventId);

        ob_start();
        require __DIR__ . '/../Layout/header.php';
        require __DIR__ . '/../Views/SocialEvents/Admin.php';
        require __DIR__ . '/../Layout/footer.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }

    #[Post('/{id}')]
    #[Middleware(AdminMiddleware::class)]
    public function update(Request $request, array $params): Response
    {
        $socialEventId = (int) $params['id'];

        $validation = $request->validate([
            new ValidationRule('name', ['required', 'string', 'max' => 255]),
            new ValidationRule('date', ['required', 'date']),
            new ValidationRule('menus', ['required', 'string']),
            new ValidationRule('tables', ['required', 'string']),
        ]);

        if (!$validation->isValid) {
            flash('error', $validation->getErrorMessages());
            return Response::redirect("/social-events/$socialEventId/admin");
        }

        try {
            $data = $request->getValidatedData();
            DB::$socialEvents->update(
                $socialEventId,
                $data['name'],
                $data['date'],
                $request->getString('description') ?: null,
                $request->getString('registration_close') ?: null,
                $data['menus'],
                $data['tables'],
            );
            return Response::redirect("/social-events/$socialEventId/admin");
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect("/social-events/$socialEventId/admin");
        }
    }

    #[Post('/{id}/delete')]
    #[Middleware(AdminMiddleware::class)]
    public function delete(Request $request, array $params): Response
    {
        $socialEventId = (int) $params['id'];
        $socialEvent = DB::$socialEvents->get($socialEventId, User::id() ?? 0);
        if (!$socialEvent) {
            return Response::notFound(__('social_events.not_found'));
        }

        DB::$socialEvents->delete($socialEventId);
        $backUrl = $socialEvent->tournamentId ? "/events/{$socialEvent->tournamentId}" : '/';
        return Response::redirect($backUrl);
    }

    #[Post('/{id}/lock')]
    #[Middleware(AdminMiddleware::class)]
    public function lock(Request $request, array $params): Response
    {
        DB::$socialEvents->lock((int) $params['id']);
        return Response::redirect("/social-events/{$params['id']}/admin");
    }

    #[Post('/{id}/unlock')]
    #[Middleware(AdminMiddleware::class)]
    public function unlock(Request $request, array $params): Response
    {
        DB::$socialEvents->unlock((int) $params['id']);
        return Response::redirect("/social-events/{$params['id']}/admin");
    }

    #[Post('/{id}/register')]
    #[Middleware(AuthMiddleware::class)]
    public function register(Request $request, array $params): Response
    {
        $socialEventId = (int) $params['id'];
        $userId = User::id();

        if ($userId === null) {
            return Response::unauthorized();
        }

        $socialEvent = DB::$socialEvents->get($socialEventId, $userId);
        if (!$socialEvent) {
            return Response::notFound(__('social_events.not_found'));
        }

        if ($socialEvent->isLocked) {
            return Response::redirect("/social-events/$socialEventId");
        }

        if ($socialEvent->userRegistered) {
            return Response::redirect("/social-events/$socialEventId");
        }

        $menuId = (int) $request->getString('menu_id');
        $tableId = $request->getString('table_id') !== '' ? (int) $request->getString('table_id') : null;

        if (!DB::$socialEvents->menuBelongsToEvent($menuId, $socialEventId)) {
            flash('error', __('social_events.invalid_menu'));
            return Response::redirect("/social-events/$socialEventId");
        }

        if ($tableId !== null && !DB::$socialEvents->tableBelongsToEvent($tableId, $socialEventId)) {
            flash('error', __('social_events.invalid_table'));
            return Response::redirect("/social-events/$socialEventId");
        }

        if (DB::$socialEvents->isFull($socialEventId)) {
            flash('error', __('social_events.event_full'));
            return Response::redirect("/social-events/$socialEventId");
        }

        if ($tableId !== null && DB::$socialEvents->isTableFull($tableId)) {
            flash('error', __('social_events.table_full'));
            return Response::redirect("/social-events/$socialEventId");
        }

        DB::$socialEvents->register($socialEventId, $userId, $menuId, $tableId);
        return Response::redirect("/social-events/$socialEventId");
    }

    #[Post('/{id}/unregister')]
    #[Middleware(AuthMiddleware::class)]
    public function unregister(Request $request, array $params): Response
    {
        $socialEventId = (int) $params['id'];
        $userId = User::id();

        if ($userId === null) {
            return Response::unauthorized();
        }

        $socialEvent = DB::$socialEvents->get($socialEventId, $userId);
        if (!$socialEvent || $socialEvent->isLocked) {
            return Response::redirect("/social-events/$socialEventId");
        }

        DB::$socialEvents->unregister($socialEventId, $userId);
        return Response::redirect("/social-events/$socialEventId");
    }

    #[Get('/{id}/guests/new')]
    public function createGuest(Request $request, array $params): Response
    {
        $socialEventId = (int) $params['id'];
        $socialEvent = DB::$socialEvents->get($socialEventId, 0);

        if (!$socialEvent) {
            return Response::notFound(__('social_events.not_found'));
        }

        $menus = DB::$socialEvents->menus($socialEventId);
        $tables = DB::$socialEvents->tables($socialEventId);

        ob_start();
        require __DIR__ . '/../Layout/header.php';
        require __DIR__ . '/../Views/SocialEvents/NewGuest.php';
        require __DIR__ . '/../Layout/footer.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }

    #[Post('/{id}/guests/new')]
    public function storeGuest(Request $request, array $params): Response
    {
        $socialEventId = (int) $params['id'];
        $socialEvent = DB::$socialEvents->get($socialEventId, 0);

        if (!$socialEvent) {
            return Response::notFound(__('social_events.not_found'));
        }

        if ($socialEvent->isLocked) {
            return Response::redirect("/social-events/$socialEventId/guests/new");
        }

        $isAdmin = User::admin();
        $emailRules = $isAdmin ? ['email', 'max' => 255] : ['required', 'email', 'max' => 255];

        $validation = $request->validate([
            new ValidationRule('first_name', ['required', 'string', 'max' => 255]),
            new ValidationRule('last_name', ['required', 'string', 'max' => 255]),
            new ValidationRule('email', $emailRules),
            new ValidationRule('menu_id', ['required', 'integer']),
        ]);

        if (!$validation->isValid) {
            flash('error', $validation->getErrorMessages());
            return Response::redirect("/social-events/$socialEventId/guests/new");
        }

        $data = $request->getValidatedData();
        $menuId = (int) $data['menu_id'];
        $tableId = $request->getString('table_id') !== '' ? (int) $request->getString('table_id') : null;

        if (!DB::$socialEvents->menuBelongsToEvent($menuId, $socialEventId)) {
            flash('error', __('social_events.invalid_menu'));
            return Response::redirect("/social-events/$socialEventId/guests/new");
        }

        if ($tableId !== null && !DB::$socialEvents->tableBelongsToEvent($tableId, $socialEventId)) {
            flash('error', __('social_events.invalid_table'));
            return Response::redirect("/social-events/$socialEventId/guests/new");
        }

        if (DB::$socialEvents->isFull($socialEventId)) {
            flash('error', __('social_events.event_full'));
            return Response::redirect("/social-events/$socialEventId/guests/new");
        }

        if ($tableId !== null && DB::$socialEvents->isTableFull($tableId)) {
            flash('error', __('social_events.table_full'));
            return Response::redirect("/social-events/$socialEventId/guests/new");
        }

        DB::$socialEvents->registerGuest(
            $socialEventId,
            $data['first_name'],
            $data['last_name'],
            $data['email'] ?? '',
            $menuId,
            $tableId,
        );

        return Response::redirect("/social-events/$socialEventId/admin");
    }

    #[Post('/{id}/registrations/{registrationId}/delete')]
    #[Middleware(AdminMiddleware::class)]
    public function deleteRegistration(Request $request, array $params): Response
    {
        $socialEventId = (int) $params['id'];
        DB::$socialEvents->deleteRegistration((int) $params['registrationId']);
        return Response::redirect("/social-events/$socialEventId/admin");
    }
}
