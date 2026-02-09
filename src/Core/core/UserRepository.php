<?php

class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly bool $isAdmin
    ) {}

    public static function loggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function admin(): bool
    {
        return (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }

    public static function setCurrent(User $user)
    {
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_name'] = $user->username;
        $_SESSION['is_admin'] = $user->isAdmin;
    }

    public static function current(): ?User
    {
        if (!isset($_SESSION['user_id']))
            return null;

        $user = new User(
            $_SESSION['user_id'],
            $_SESSION['user_name'],
            $_SESSION['is_admin']
        );
        return $user;
    }

    public static function canEdit(int $userId): bool
    {
        return User::admin() || $userId == User::id();
    }
}

class UserRepository
{
    public function __construct(private readonly mysqli $conn) {}

    /**
     * Checks if a user exists by user ID.
     *
     * @param string $userId The ID of the user to check.
     * @return bool True if the user exists, false otherwise.
     * @throws Exception If the query fails.
     */
    public function exists(string $userId): bool
    {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result->num_rows > 0;
    }

    /**
     * Checks if a username is already taken.
     *
     * @param string $username The username to check.
     * @return bool True if the username is already taken, false otherwise.
     * @throws Exception If the query fails.
     */
    public function userNameAlreadyTaken(string $username): bool
    {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result->num_rows > 0;
    }

    /**
     * Creates a new user.
     *
     * @param string $username The username of the new user.
     * @param string $password The password of the new user.
     * @return int|false The ID of the newly created user, or false on failure.
     * @throws Exception If the query fails.
     */
    public function create(string $username, string $password): int|false
    {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("INSERT INTO users (username, password, admin) VALUES (?, ?, 0)");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("ss", $username, $hashed_password);
        $success = $stmt->execute();
        $userId = $this->conn->insert_id;
        $stmt->close();
        return $success ? $userId : false;
    }

    /**
     * Sets the password for a user.
     *
     * @param int $userId The ID of the user.
     * @param string $password The new password for the user.
     * @throws Exception If the query fails.
     */
    public function setPassword(int $userId, string $password): void
    {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("si", $hashed_password, $userId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Sets the admin status for a user.
     *
     * @param int $userId The ID of the user.
     * @param bool $isAdmin The admin status to set.
     * @throws Exception If the query fails.
     */
    public function setAdmin(int $userId, bool $isAdmin): void
    {
        $stmt = $this->conn->prepare("UPDATE users SET Admin = ? WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("ii", $isAdmin, $userId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Deletes a user.
     *
     * @param int $userId The ID of the user to delete.
     * @throws Exception If the query fails.
     */
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

    /**
     * Retrieves all users.
     *
     * @return array<User> An array of User objects.
     * @throws Exception If the query fails.
     */
    public function all(): array
    {
        $stmt = $this->conn->prepare("SELECT id, username, admin FROM users");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $users = [];

        while ($row = $result->fetch_assoc()) {
            $users[] = new User($row['id'], $row['username'], $row['admin']);
        }

        $stmt->close();
        return $users;
    }

    /**
     * Retrieves a user along with their password based on the provided username.
     *
     * @param string $username The username of the user to retrieve.
     * @return array{0: User|null, 1: string|null} An array containing a User object and the hashed password, or null if the user is not found.
     * @throws Exception If the query fails.
     */
    public function getWithPassword(string $username): array
    {
        $stmt = $this->conn->prepare("SELECT id, password, admin FROM users WHERE username = ?");
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $user = new User($row['id'], $username, $row['admin']);
            return [$user, $row['password']];
        }

        $stmt->close();
        return [null, null];
    }
}
