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
            flash_input($request->getAllInput());
            flash('error', $validation->getErrorMessages());
            return Response::redirect('/users/new');
        }

        try {
            $data = $request->getValidatedData();
            $username = trim($data['username']);
            $password = trim($data['password']);
            $rfeg = $request->getString('rfeg') ?: null;
            $memberNumber = $request->getString('member_number') ?: null;
            $firstName = $request->getString('first_name') ?: null;
            $lastName = $request->getString('last_name') ?: null;

            if (DB::$users->userNameAlreadyTaken($username)) {
                flash_input($request->getAllInput());
                flash('error', __('users.username_taken', ['username' => $username]));
                return Response::redirect('/users/new');
            }

            DB::$users->create($username, $password, (bool) $data['male'], $rfeg, $memberNumber, $firstName, $lastName);
            flash('success', __('users.create_success'));
            return Response::redirect('/users');

        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect('/users/new');
        }
    }

    #[Get('/{id}/edit')]
    public function edit(Request $request, array $params): Response
    {
        $userId = (int) $params['id'];
        $user = DB::$users->get($userId);

        if (!$user) {
            return Response::notFound(__('users.not_found'));
        }

        ob_start();
        require __DIR__ . '/../Layout/header.php';
        require __DIR__ . '/../Views/Users/Edit.php';
        require __DIR__ . '/../Layout/footer.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }

    #[Post('/{id}/update')]
    public function update(Request $request, array $params): Response
    {
        $userId = (int) $params['id'];

        $user = DB::$users->get($userId);
        if (!$user) {
            return Response::notFound(__('users.not_found'));
        }

        $validation = $request->validate([
            new ValidationRule('male', ['required', 'boolean']),
            new ValidationRule('username', ['required', 'string', 'min' => 3, 'max' => 255]),
        ]);

        if (!$validation->isValid) {
            flash('error', $validation->getErrorMessages());
            return Response::redirect("/users/$userId/edit");
        }

        try {
            $data = $request->getValidatedData();
            $username = trim($data['username']);

            if ($username !== $user->username && DB::$users->userNameAlreadyTaken($username)) {
                flash('error', __('users.username_taken', ['username' => $username]));
                return Response::redirect("/users/$userId/edit");
            }

            DB::$users->update(
                $userId,
                (bool) $data['male'],
                $username,
                $request->getString('rfeg') ?: null,
                $request->getString('member_number') ?: null,
                $request->getString('first_name') ?: null,
                $request->getString('last_name') ?: null,
            );

            $password = $request->getString('password');
            if ($password !== '') {
                DB::$users->setPassword($userId, $password);
            }

            flash('success', __('users.update_success'));
            return Response::redirect('/users');
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect("/users/$userId/edit");
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
}
