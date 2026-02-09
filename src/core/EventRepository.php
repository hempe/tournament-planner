<?php

require_once __DIR__ . '/DateTimeHelper.php';
require_once __DIR__ . '/BaseRepository.php';

class EventRegistration
{
    public readonly string $ago;
    public function __construct(
        public readonly int $userId,
        public readonly string $name,
        public readonly string $comment,
        public readonly string $timestamp,
        public readonly int $state,
    ) {
        $this->ago = DateTimeHelper::ago($timestamp);
    }
}

class Event
{
    public readonly int $available;
    public function __construct(
        public readonly int $id,
        public readonly bool $locked,
        public readonly bool $isLocked,
        public readonly string $date,
        public readonly string $name,
        public readonly int $capacity,
        public readonly int $joined,
        public readonly int $onWaitList,
        public readonly int $userState
    ) {
        $this->available = $capacity - $joined;
    }
}

class EventRepository extends BaseRepository
{
    // Base query for events
    private const QUERY_EVENT = "SELECT 
            e.id, 
            e.locked,
            (e.locked = 1 OR e.date < NOW()) as isLocked,
            e.name, 
            e.date, 
            e.capacity,
            (SELECT count(*) FROM event_users WHERE state = 1 AND eventId = e.id) as joined,
            (SELECT count(*) FROM event_users WHERE state = 2 AND eventId = e.id) as waitList,
            IF(eu.userId IS NOT NULL, eu.state, 0) as userState
        FROM events e
        LEFT JOIN event_users eu ON eu.eventId = e.id";

    // Specific queries based on the base query
    private const QUERY_REGISTERED_EVENTS = self::QUERY_EVENT . " WHERE eu.userId = ?";
    private const QUERY_EVENT_BY_ID = self::QUERY_EVENT . " WHERE e.id = ? AND eu.userId = ?";
    private const QUERY_ALL_EVENTS = self::QUERY_EVENT . " WHERE eu.userId = ?";

    private function mapEvent(array $row): Event
    {
        return new Event(
            $row['id'],
            $row['locked'] == 1,
            (bool) $row['isLocked'],
            $row['date'],
            $row['name'],
            $row['capacity'],
            $row['joined'],
            $row['waitList'],
            $row['userState']
        );
    }

    private function fetchEvents(string $query, string $types, array $params): array
    {
        return $this->fetchMappedRows($query, $types, $params, fn($row) => $this->mapEvent($row));
    }

    /**
     * Retrieves available users for an event.
     *
     * @param int $id The ID of the event.
     * @return array<User> An array of available users.
     * @throws Exception If the query fails.
     */
    public function availableUsers(int $eventId): array
    {
        $isAdmin = User::admin();
        $query = $isAdmin
            ? "SELECT u.id, u.username, u.admin FROM users AS u WHERE u.id NOT IN (SELECT r.userId FROM event_users AS r WHERE r.eventId = ?) ORDER BY u.username"
            : "SELECT u.id, u.username, u.admin FROM users AS u WHERE u.id = ? AND u.id NOT IN (SELECT r.userId FROM event_users AS r WHERE r.eventId = ?) ORDER BY u.username";

        $params = $isAdmin ? [$eventId] : [User::id(), $eventId];
        $types = $isAdmin ? "i" : "ii";

        return $this->fetchMappedRows($query, $types, $params, fn($row) => new User($row['id'], $row['username'], $row['admin']));
    }

    /**
     * Deletes an event.
     *
     * @param int $id The ID of the event to delete.
     * @throws Exception If the query fails.
     */
    public function delete(int $id): void
    {
        $this->executeUpdateQuery(
            "DELETE FROM events WHERE id = ?",
            "i",
            [$id]
        );
    }

    /**
     * Updates an event.
     *
     * @param int $id The ID of the event to update.
     * @param string $name The new name of the event.
     * @param int $capacity The new capacity of the event.
     * @throws Exception If the query fails.
     */
    public function update(int $id, string $name, int $capacity): void
    {
        $this->executeUpdateQuery(
            "UPDATE events SET name = ?, capacity = ? WHERE id = ?",
            "sii",
            [$name, $capacity, $id]
        );
        $this->fix($id);
    }

    /**
     * Adds a new event.
     *
     * @param string $name The name of the event.
     * @param string $date The date of the event.
     * @param int $capacity The capacity of the event.
     * @return int|null The ID of the newly created event, or null if the creation failed.
     * @throws Exception If the query fails.
     */
    public function add(string $name, string $date, int $capacity): ?int
    {
        $now = (new \DateTime())->format('Y-m-d H:i:s');
        $this->executeUpdateQuery(
            "INSERT INTO events (name, date, capacity, timestamp) VALUES (?, ?, ?, ?)",
            "ssis",
            [$name, $date, $capacity, $now]
        );

        return $this->fetchSingleValue("SELECT LAST_INSERT_ID()", "", []);
    }

    /**
     * Locks an event.
     *
     * @param int $id The ID of the event to lock.
     * @throws Exception If the query fails.
     */
    public function lock(int $id): void
    {
        $this->executeUpdateQuery("UPDATE events SET locked = 1 WHERE id = ?", "i", [$id]);
    }

    /**
     * Unlocks an event.
     *
     * @param int $id The ID of the event to unlock.
     * @throws Exception If the query fails.
     */
    public function unlock(int $id): void
    {
        $this->executeUpdateQuery("UPDATE events SET locked = 0 WHERE id = ?", "i", [$id]);
    }

    /**
     * Updates the comment for an event registration.
     *
     * @param int $eventId The ID of the event.
     * @param int $userId The ID of the user.
     * @param string $comment The new comment.
     * @throws Exception If the query fails.
     */
    public function updateRegistrationComment(int $eventId, int $userId, string $comment): void
    {
        $this->executeUpdateQuery(
            "UPDATE event_users SET comment = ? WHERE eventId = ? AND userId = ?",
            "sii",
            [$comment, $eventId, $userId]
        );
    }

    /**
     * Retrieves the users associated with a given event.
     *
     * @param int $eventId The ID of the event.
     * @return array<EventRegistration> An array of users associated with the event.
     * @throws Exception If the query fails.
     */
    public function registrations(int $eventId): array
    {
        $query = "SELECT 
                r.id, 
                r.comment, 
                r.timestamp, 
                r.state,
                u.id AS user_id, 
                u.username 
            FROM event_users AS r 
            JOIN users AS u ON r.userId = u.id 
            WHERE r.eventId = ? 
            ORDER BY r.state, u.username";

        return $this->fetchMappedRows($query, "i", [$eventId], fn($row) => new EventRegistration(
            $row['user_id'],
            $row['username'],
            $row['comment'],
            $row['timestamp'],
            $row['state']
        ));
    }

    /**
     * Retrieves an event by its ID.
     *
     * @param int $id The ID of the event.
     * @param int $userId The ID of the user.
     * @return Event|null The event object, or null if not found.
     * @throws Exception If the query fails.
     */
    public function get(int $id, int $userId): ?Event
    {
        $rows = $this->fetchMappedRows(self::QUERY_EVENT_BY_ID, 'ii', [$id, $userId], fn($row) => $this->mapEvent($row));
        return $rows[0] ?? null;
    }

    /**
     * Retrieves a list of events.
     *
     * @param DateTime|null $date Optional date to filter events. If null, retrieves all events.
     * @return array<Event> An array of events.
     * @throws Exception If the query fails.
     */
    public function all(?DateTime $date = null): array
    {
        $query = self::QUERY_ALL_EVENTS;

        if ($date) {
            $query .= " AND MONTH(e.date) = ? AND YEAR(e.date) = ?";
            return $this->fetchEvents($query, 'iii', [$_SESSION['user_id'], $date->format('m'), $date->format('Y')]);
        }

        return $this->fetchEvents($query, 'i', [$_SESSION['user_id']]);
    }

    /**
     * Registers a user to an event.
     *
     * @param int $eventId The ID of the event.
     * @param int $userId The ID of the user.
     * @param string $comment The comment for the registration.
     * @throws Exception If the query fails.
     */
    public function register(int $eventId, int $userId, string $comment): void
    {
        $state = $this->fetchSingleValue(
            "SELECT IF(capacity > (SELECT count(*) FROM event_users WHERE state = 1 AND eventId = ?), 1, 2) as state FROM events WHERE id = ?",
            "ii",
            [$eventId, $eventId]
        );

        $this->executeUpdateQuery(
            "INSERT INTO event_users (userId, eventId, comment, state, timestamp) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE comment = VALUES(comment), state = VALUES(state), timestamp = NOW()",
            "iisi",
            [$userId, $eventId, $comment, $state]
        );

        $this->fix($eventId);
    }

    /**
     * Unregisters a user from an event.
     *
     * @param int $eventId The ID of the event.
     * @param int $userId The ID of the user.
     * @throws Exception If the query fails.
     */
    public function unregister(int $eventId, int $userId): void
    {
        $this->executeUpdateQuery(
            "DELETE FROM event_users WHERE eventId = ? AND userId = ?",
            "ii",
            [$eventId, $userId]
        );

        $this->fix($eventId);
    }

    /**
     * Checks if an event is locked.
     *
     * @param int $eventId The ID of the event.
     * @return bool True if the event is locked, false otherwise.
     * @throws Exception If the query fails.
     */
    public function isLocked(int $eventId): bool
    {
        return boolval($this->fetchSingleValue(
            "SELECT (locked = 1 OR date < NOW()) as isLocked FROM events WHERE id = ?",
            "i",
            [$eventId]
        ));
    }

    /**
     * Fixes the event registration state based on the available spots.
     *
     * @param int $eventId The ID of the event.
     * @throws Exception If the query fails.
     */
    private function fix(int $eventId): void
    {

        $available = $this->fetchSingleValue(
            "SELECT capacity - (SELECT count(*) FROM event_users WHERE state = 1 AND eventId = ?) FROM events WHERE id = ?",
            "ii",
            [$eventId, $eventId]
        ) ?? 0;

        if ($available > 0) {
            $this->executeUpdateQuery(
                "UPDATE event_users AS eu
                INNER JOIN (
                    SELECT eu_inner.id
                    FROM event_users AS eu_inner
                    INNER JOIN users AS u ON eu_inner.userId = u.id
                    WHERE eu_inner.state = 2 AND eu_inner.eventId = ?
                    ORDER BY eu_inner.timestamp ASC
                    LIMIT ?
                ) AS subquery ON eu.id = subquery.id
                SET eu.state = 1",
                "ii",
                [$eventId, $available]
            );
        } elseif ($available < 0) {
            $this->executeUpdateQuery(
                "UPDATE event_users AS eu
                INNER JOIN (
                    SELECT eu_inner.id
                    FROM event_users AS eu_inner
                    INNER JOIN users AS u ON eu_inner.userId = u.id
                    WHERE eu_inner.state = 1 AND eu_inner.eventId = ?
                    ORDER BY eu_inner.timestamp DESC
                    LIMIT ?
                ) AS subquery ON eu.id = subquery.id
                SET eu.state = 2",
                "ii",
                [$eventId, abs($available)]
            );
        }
    }

    /**
     * Retrieves a list of events the user is registered to.
     *
     * @param int $userId The ID of the user.
     * @return array<Event> An array of events the user is registered to.
     * @throws Exception If the query fails.
     */
    public function registeredEvents(int $userId): array
    {
        return $this->fetchEvents(self::QUERY_REGISTERED_EVENTS, 'i', [$userId]);
    }
}
