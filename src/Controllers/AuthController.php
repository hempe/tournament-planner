<?php

declare(strict_types=1);

namespace TP\Controllers;

use TP\Core\Request;
use TP\Core\Response;
use TP\Core\ValidationRule;
use TP\Models\User;
use Exception;

final class AuthController
{
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

    public function login(Request $request): Response
    {
        $validation = $request->validate([
            new ValidationRule('username', ['required', 'string']),
            new ValidationRule('password', ['required', 'string']),
        ]);

        if (!$validation->isValid) {
            flash('error', __('auth.required_fields'));
            return Response::redirect('/login');
        }

        $data = $request->getValidatedData();
        
        try {
            $user = User::authenticate($data['username'], $data['password']);
            
            if ($user) {
                session_regenerate_id(true);
                User::setCurrent($user);
                flash('success', __('auth.login_success'));
                return Response::redirect('/');
            } else {
                flash('error', __('auth.login_failed'));
                return Response::redirect('/login');
            }
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            return Response::redirect('/login');
        }
    }

    public function logout(Request $request): Response
    {
        User::logout();
        flash('success', __('auth.logout_success'));
        return Response::redirect('/login');
    }
}