<?php

declare(strict_types=1);

namespace TP\Models;

use TP\Core\Security;
use Exception;
use mysqli;

final class UserRepository
{
    private const COLUMNS = 'id, username, admin, male, rfeg, member_number, first_name, last_name, phone, handicap, prio';
    /** Flavour-specific fields (API name => column) written by updateExtra(). */
    private const EXTRA_COLUMNS = ['rfeg' => 'rfeg', 'memberNumber' => 'member_number', 'phone' => 'phone', 'handicap' => 'handicap', 'priority' => 'prio'];

    private static function map(array $row): User
    {
        return new User(
            (int) $row['id'],
            $row['username'],
            (bool) $row['admin'],
            (bool) $row['male'],
            $row['rfeg'] ?? null,
            $row['member_number'] ?? null,
            $row['first_name'] ?? null,
            $row['last_name'] ?? null,
            $row['phone'] ?? null,
            isset($row['handicap']) ? (float) $row['handicap'] : null,
            isset($row['prio']) ? (int) $row['prio'] : null,
        );
    }
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
        $stmt = $this->conn->prepare("SELECT " . self::COLUMNS . " FROM users WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ? self::map($row) : null;
    }

    public function update(int $id, bool $male, string $username, ?string $firstName, ?string $lastName): void
    {
        $maleInt = $male ? 1 : 0;
        $stmt = $this->conn->prepare("UPDATE users SET male = ?, username = ?, first_name = ?, last_name = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("isssi", $maleInt, $username, $firstName, $lastName, $id);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Flavour-specific fields; only the keys present in $fields are written (null clears).
     *
     * @param array<string, string|null> $fields keys: rfeg, memberNumber, phone, handicap, priority
     */
    public function updateExtra(int $id, array $fields): void
    {
        foreach (array_intersect_key($fields, self::EXTRA_COLUMNS) as $key => $value) {
            $column = self::EXTRA_COLUMNS[$key];
            $stmt = $this->conn->prepare("UPDATE users SET {$column} = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare statement failed: " . $this->conn->error);
            }
            $stmt->bind_param('si', $value, $id);
            $stmt->execute();
            $stmt->close();
        }
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

    /**
     * Split a legacy username into [first name, last name]: titles (Dr, Prof, Mag, with or
     * without dot, as ", Dr." suffix or leading "Dr.") are dropped, the last word is the
     * last name, everything before it the first name.
     *
     * @return array{0: string, 1: string}
     */
    public static function splitUsername(string $raw): array
    {
        $titles = '(?:Dr|Prof|Mag)\.?';
        $cleaned = preg_replace("/,\s*{$titles}(?=\s|,|$)/iu", '', $raw) ?? $raw;
        $cleaned = preg_replace("/^{$titles}\s+/iu", '', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);
        if ($cleaned === '') {
            $cleaned = $raw;
        }

        $parts = preg_split('/\s+/u', $cleaned, -1, PREG_SPLIT_NO_EMPTY) ?: [$raw];
        $lastName = (string) array_pop($parts);
        return [implode(' ', $parts), $lastName];
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
            [$firstName, $lastName] = self::splitUsername($raw);

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
        $stmt = $this->conn->prepare("SELECT " . self::COLUMNS . " FROM users ORDER BY last_name, first_name, username");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];

        while ($row = $result->fetch_assoc()) {
            $users[] = self::map($row);
        }

        $stmt->close();
        return $users;
    }

    /**
     * Users whose "first last" or "last first" equals $name (case-insensitive, whitespace-normalised).
     *
     * @return list<array{0: User, 1: string}> [user, password hash]
     */
    public function findByName(string $name): array
    {
        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
        if ($normalized === '' || !str_contains($normalized, ' ')) {
            return [];
        }
        $stmt = $this->conn->prepare(
            "SELECT " . self::COLUMNS . ", password FROM users
             WHERE first_name <> '' AND last_name <> ''
               AND (LOWER(CONCAT(TRIM(first_name), ' ', TRIM(last_name))) = ?
                 OR LOWER(CONCAT(TRIM(last_name), ' ', TRIM(first_name))) = ?)"
        );
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param('ss', $normalized, $normalized);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(fn(array $row) => [self::map($row), $row['password']], $rows);
    }

    /**
     * Ids of users whose name ("first last" / "last first") would make a name login ambiguous:
     * another user has the same name pair, or another user's username equals it.
     *
     * @return array<int, true>
     */
    public function nameConflicts(): array
    {
        $result = $this->conn->query(
            "SELECT DISTINCT a.id FROM users a JOIN users b ON a.id <> b.id
             WHERE a.first_name <> '' AND a.last_name <> '' AND (
                (LOWER(TRIM(a.first_name)) = LOWER(TRIM(b.first_name)) AND LOWER(TRIM(a.last_name)) = LOWER(TRIM(b.last_name)))
                OR LOWER(TRIM(b.username)) IN (
                    LOWER(CONCAT(TRIM(a.first_name), ' ', TRIM(a.last_name))),
                    LOWER(CONCAT(TRIM(a.last_name), ' ', TRIM(a.first_name)))))"
        );
        $ids = [];
        foreach ($result ? $result->fetch_all() : [] as [$id]) {
            $ids[(int) $id] = true;
        }
        return $ids;
    }

    /** @return array{0: User|null, 1: string|null} */
    public function getWithPassword(string $username): array
    {
        $stmt = $this->conn->prepare("SELECT " . self::COLUMNS . ", password FROM users WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $user = self::map($row);
            $stmt->close();
            return [$user, $row['password']];
        }

        $stmt->close();
        return [null, null];
    }
}