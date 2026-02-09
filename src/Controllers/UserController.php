<?php

declare(strict_types=1);

namespace TP\Controllers;

use TP\Core\Request;
use TP\Core\Response;
use TP\Core\ValidationRule;
use TP\Models\DB;
use Exception;

final class UserController
{
    public function index(Request $request): Response
    {
        $users = DB::$users->all();

        ob_start();
        require __DIR__ . '/../../src/pages/users/views/list.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }

    public function create(Request $request): Response
    {
        ob_start();
        require __DIR__ . '/../../src/pages/users/views/new.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }

    public function store(Request $request): Response
    {
        $validation = $request->validate([
            new ValidationRule('username', ['required', 'string', 'min' => 3, 'max' => 255]),
            new ValidationRule('password', ['required', 'string', 'min' => 6]),
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

            $userId = DB::$users->create($username, $password);
            flash('success', __('users.create_success'));
            return Response::redirect('/users');

        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect('/users/new');
        }
    }

    public function delete(Request $request, array $params): Response
    {
        $userId = (int)$params['id'];
        
        try {
            DB::$users->delete($userId);
            flash('success', __('users.delete_success'));
            return Response::redirect('/users');
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect('/users');
        }
    }

    public function toggleAdmin(Request $request, array $params): Response
    {
        $userId = (int)$params['id'];
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

    public function changePassword(Request $request, array $params): Response
    {
        $userId = (int)$params['id'];
        
        $validation = $request->validate([
            new ValidationRule('password', ['required', 'string', 'min' => 6]),
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