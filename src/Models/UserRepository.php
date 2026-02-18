<?php

declare(strict_types=1);

namespace TP\Models;

use TP\Core\Security;
use Exception;
use mysqli;

final class UserRepository
{
    public function __construct(private readonly mysqli $conn)
    {
    }

    public function exists(string $userId): bool
    {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function userNameAlreadyTaken(string $username): bool
    {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $taken = $result->num_rows > 0;
        $stmt->close();
        return $taken;
    }

    public function create(string $username, string $password, bool $male = true): int
    {
        $hashedPassword = Security::getInstance()->hashPassword($password);
        $maleInt = $male ? 1 : 0;
        $stmt = $this->conn->prepare("INSERT INTO users (username, password, admin, male) VALUES (?, ?, 0, ?)");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("ssi", $username, $hashedPassword, $maleInt);
        $success = $stmt->execute();
        if (!$success) {
            $stmt->close();
            throw new Exception("Failed to create user: " . $this->conn->error);
        }
        $userId = $this->conn->insert_id;
        $stmt->close();
        return $userId;
    }

    public function setPassword(int $userId, string $password): void
    {
        $hashedPassword = Security::getInstance()->hashPassword($password);
        $stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("si", $hashedPassword, $userId);
        $stmt->execute();
        $stmt->close();
    }

    public function setAdmin(int $userId, bool $isAdmin): void
    {
        $adminValue = $isAdmin ? 1 : 0;
        $stmt = $this->conn->prepare("UPDATE users SET admin = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("ii", $adminValue, $userId);
        $stmt->execute();
        $stmt->close();
    }

    public function delete(int $userId): void
    {
        $stmt = $this->conn->prepare("DELETE FROM users WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();
    }

    /** @return User[] */
    public function all(): array
    {
        $stmt = $this->conn->prepare("SELECT id, username, admin, male FROM users");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];

        while ($row = $result->fetch_assoc()) {
            $users[] = new User((int) $row['id'], $row['username'], (bool) $row['admin'], (bool) $row['male']);
        }

        $stmt->close();
        return $users;
    }

    /** @return array{0: User|null, 1: string|null} */
    public function getWithPassword(string $username): array
    {
        $stmt = $this->conn->prepare("SELECT id, password, admin, male FROM users WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $user = new User((int) $row['id'], $username, (bool) $row['admin'], (bool) $row['male']);
            $stmt->close();
            return [$user, $row['password']];
        }

        $stmt->close();
        return [null, null];
    }
}