<?php

declare(strict_types=1);

namespace TP\Models;

use TP\Core\Security;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly bool $isAdmin
    ) {
    }

    public static function loggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function admin(): bool
    {
        // No admin rights in iframe mode
        if (isset($_SESSION['iframe_mode']) && $_SESSION['iframe_mode'] === true) {
            return false;
        }

        return (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }

    public static function setCurrent(User $user): void
    {
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_name'] = $user->username;
        $_SESSION['is_admin'] = $user->isAdmin;
    }

    public static function current(): ?User
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return new User(
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $_SESSION['is_admin']
        );
    }

    public static function canEdit(int $userId): bool
    {
        return self::admin() || $userId === self::id();
    }

    public static function authenticate(string $username, string $password): ?User
    {
        // Check if database is initialized
        if (!isset(\TP\Models\DB::$users)) {
            return null;
        }

        $userRepo = \TP\Models\DB::$users;
        [$user, $hashedPassword] = $userRepo->getWithPassword($username);

        if ($user && $hashedPassword && \TP\Core\Security::getInstance()->verifyPassword($password, $hashedPassword)) {
            return $user;
        }

        return null;
    }

    public static function logout(): void
    {
        session_destroy();
    }
}