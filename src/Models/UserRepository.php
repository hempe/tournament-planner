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

    public function create(string $username, string $password, bool $male = true, ?string $rfeg = null, ?string $memberNumber = null, ?string $firstName = null, ?string $lastName = null): int
    {
        $hashedPassword = Security::getInstance()->hashPassword($password);
        $maleInt = $male ? 1 : 0;
        $stmt = $this->conn->prepare("INSERT INTO users (username, password, admin, male, rfeg, member_number, first_name, last_name) VALUES (?, ?, 0, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("ssissss", $username, $hashedPassword, $maleInt, $rfeg, $memberNumber, $firstName, $lastName);
        $success = $stmt->execute();
        if (!$success) {
            $stmt->close();
            throw new Exception("Failed to create user: " . $this->conn->error);
        }
        $userId = $this->conn->insert_id;
        $stmt->close();
        return $userId;
    }

    public function get(int $id): ?User
    {
        $stmt = $this->conn->prepare("SELECT id, username, admin, male, rfeg, member_number, first_name, last_name FROM users WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return null;
        }
        return new User((int) $row['id'], $row['username'], (bool) $row['admin'], (bool) $row['male'], $row['rfeg'], $row['member_number'], $row['first_name'] ?? null, $row['last_name'] ?? null);
    }

    public function update(int $id, bool $male, string $username, ?string $rfeg, ?string $memberNumber, ?string $firstName, ?string $lastName): void
    {
        $maleInt = $male ? 1 : 0;
        $stmt = $this->conn->prepare("UPDATE users SET male = ?, username = ?, rfeg = ?, member_number = ?, first_name = ?, last_name = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("isssssi", $maleInt, $username, $rfeg, $memberNumber, $firstName, $lastName, $id);
        $stmt->execute();
        $stmt->close();
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

    public function countWithoutNames(): int
    {
        $result = $this->conn->query("SELECT COUNT(*) AS cnt FROM users WHERE first_name IS NULL OR first_name = ''");
        if (!$result) {
            return 0;
        }
        $row = $result->fetch_assoc();
        return (int) ($row['cnt'] ?? 0);
    }

    /** @return array<array{id:int, username:string, first:string, last:string, status:string}> */
    public function seedNamesFromUsernames(): array
    {
        $result = $this->conn->query("SELECT id, username FROM users WHERE first_name IS NULL OR first_name = ''");
        if (!$result) {
            return [];
        }

        $results = [];
        while ($row = $result->fetch_assoc()) {
            $raw = $row['username'];

            // Strip title suffixes: ", Dr.", ", Dr", ", Prof.", ", Prof"
            $cleaned = preg_replace('/,\s*(Dr\.|Dr|Prof\.|Prof)\b/i', '', $raw) ?? $raw;
            // Strip leading titles
            $cleaned = preg_replace('/^(Dr\.|Dr|Prof\.|Prof)\s+/i', '', $cleaned) ?? $cleaned;
            $cleaned = trim($cleaned);

            if ($cleaned === '') {
                $cleaned = $raw;
            }

            $parts    = preg_split('/\s+/', $cleaned, -1, PREG_SPLIT_NO_EMPTY) ?: [$raw];
            $lastName  = array_pop($parts);
            $firstName = implode(' ', $parts);

            $stmt = $this->conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE id = ?");
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('ssi', $firstName, $lastName, $row['id']);
            $stmt->execute();
            $stmt->close();

            $results[] = [
                'id'       => (int) $row['id'],
                'username' => $raw,
                'first'    => $firstName,
                'last'     => $lastName,
                'status'   => 'updated',
            ];
        }

        return $results;
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
        $stmt = $this->conn->prepare("SELECT id, username, admin, male, rfeg, member_number, first_name, last_name FROM users ORDER BY last_name, first_name, username");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];

        while ($row = $result->fetch_assoc()) {
            $users[] = new User((int) $row['id'], $row['username'], (bool) $row['admin'], (bool) $row['male'], $row['rfeg'], $row['member_number'], $row['first_name'] ?? null, $row['last_name'] ?? null);
        }

        $stmt->close();
        return $users;
    }

    /** @return array{0: User|null, 1: string|null} */
    public function getWithPassword(string $username): array
    {
        $stmt = $this->conn->prepare("SELECT id, password, admin, male, rfeg, member_number, first_name, last_name FROM users WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $user = new User((int) $row['id'], $username, (bool) $row['admin'], (bool) $row['male'], $row['rfeg'], $row['member_number'], $row['first_name'] ?? null, $row['last_name'] ?? null);
            $stmt->close();
            return [$user, $row['password']];
        }

        $stmt->close();
        return [null, null];
    }
}