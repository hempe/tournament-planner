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
use Exception;

#[RoutePrefix('/users')]
#[Middleware(AuthMiddleware::class)]
#[Middleware(AdminMiddleware::class)]
final class UserController
{
    #[Get('/')]
    public function index(Request $request): Response
    {
        $users = DB::$users->all();

        ob_start();
        require __DIR__ . '/../Layout/header.php';
        require __DIR__ . '/../Views/Users/List.php';
        require __DIR__ . '/../Layout/footer.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }

    #[Get('/new')]
    public function create(Request $request): Response
    {
        ob_start();
        require __DIR__ . '/../Layout/header.php';
        require __DIR__ . '/../Views/Users/New.php';
        require __DIR__ . '/../Layout/footer.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }

    #[Post('/')]
    public function store(Request $request): Response
    {
        $validation = $request->validate([
            new ValidationRule('male', ['required', 'boolean']),
            new ValidationRule('username', ['required', 'string', 'min' => 3, 'max' => 255]),
            new ValidationRule('password', ['required', 'string' /*, 'min' => 6 */]),
        ]);

        if (!$validation->isValid) {
            flash('error', $validation->getErrorMessages());
            return Response::redirect('/users/new');
        }

        try {
            $data = $request->getValidatedData();
            $username = trim($data['username']);
            $password = trim($data['password']);

            if (DB::$users->userNameAlreadyTaken($username)) {
                flash('error', __('users.username_taken', ['username' => $username]));
                return Response::redirect('/users/new');
            }

            $userId = DB::$users->create($username, $password, (bool) $data['male']);
            flash('success', __('users.create_success'));
            return Response::redirect('/users');

        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect('/users/new');
        }
    }

    #[Post('/{id}/delete')]
    public function delete(Request $request, array $params): Response
    {
        $userId = (int) $params['id'];

        try {
            DB::$users->delete($userId);
            flash('success', __('users.delete_success'));
            return Response::redirect('/users');
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect('/users');
        }
    }

    #[Post('/{id}/admin')]
    public function toggleAdmin(Request $request, array $params): Response
    {
        $userId = (int) $params['id'];
        $isAdmin = $request->getBool('admin');

        try {
            DB::$users->setAdmin($userId, $isAdmin);
            flash('success', __('users.admin_update_success'));
            return Response::redirect('/users');
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect('/users');
        }
    }

    #[Post('/{id}/password')]
    public function changePassword(Request $request, array $params): Response
    {
        $userId = (int) $params['id'];

        $validation = $request->validate([
            new ValidationRule('password', ['required', 'string' /*, 'min' => 6 */]),
        ]);

        if (!$validation->isValid) {
            flash('error', $validation->getErrorMessages());
            return Response::redirect('/users');
        }

        try {
            $data = $request->getValidatedData();
            DB::$users->setPassword($userId, $data['password']);
            flash('success', __('users.password_update_success'));
            return Response::redirect('/users');
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect('/users');
        }
    }
}