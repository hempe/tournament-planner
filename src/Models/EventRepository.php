<?php

declare(strict_types=1);

namespace TP\Models;

use DateTime;
use Exception;

final class EventRepository extends BaseRepository
{
    private const QUERY_EVENT = "SELECT 
                e.id, 
                e.locked,
                (e.locked = 1 OR e.date < NOW()) AS isLocked,
                e.name, 
                e.date, 
                e.capacity,
                (SELECT COUNT(*) 
                    FROM event_users 
                    WHERE state = 1 AND eventId = e.id) AS joined,
                (SELECT COUNT(*) 
                    FROM event_users 
                    WHERE state = 2 AND eventId = e.id) AS waitList,
                COALESCE(eu.state, 0) AS userState
            FROM events e
            LEFT JOIN event_users eu ON eu.eventId = e.id
            AND eu.userId = ?
            ";

    private const QUERY_REGISTERED_EVENTS = self::QUERY_EVENT;
    private const QUERY_EVENT_BY_ID = self::QUERY_EVENT . " AND e.id = ?";
    private const QUERY_ALL_EVENTS = self::QUERY_EVENT;

    private function mapEvent(array $row): Event
    {
        return new Event(
            (int) $row['id'],
            (bool) $row['locked'],
            (bool) $row['isLocked'],
            $row['date'],
            $row['name'],
            (int) $row['capacity'],
            (int) $row['joined'],
            (int) $row['waitList'],
            (int) $row['userState']
        );
    }

    /** @return Event[] */
    private function fetchEvents(
        string $query,
        string $types,
        array $params
    ): array {
        return $this->fetchMappedRows($query, $types, $params, fn($row) => $this->mapEvent($row));
    }

    /** @return User[] */
    public function availableUsers(int $eventId): array
    {
        $isAdmin = User::admin();
        $query = $isAdmin
            ? "SELECT u.id, u.username, u.admin FROM users AS u WHERE u.id NOT IN (SELECT r.userId FROM event_users AS r WHERE r.eventId = ?) ORDER BY u.username"
            : "SELECT u.id, u.username, u.admin FROM users AS u WHERE u.id = ? AND u.id NOT IN (SELECT r.userId FROM event_users AS r WHERE r.eventId = ?) ORDER BY u.username";

        $params = $isAdmin ? [$eventId] : [User::id(), $eventId];
        $types = $isAdmin ? "i" : "ii";

        return $this->fetchMappedRows($query, $types, $params, function ($row) {
            return new User((int) $row['id'], $row['username'], (bool) $row['admin']);
        });
    }

    public function delete(int $id): void
    {
        $this->executeUpdateQuery("DELETE FROM events WHERE id = ?", "i", [$id]);
    }

    public function update(int $id, string $name, int $capacity): void
    {
        $this->executeUpdateQuery(
            "UPDATE events SET name = ?, capacity = ? WHERE id = ?",
            "sii",
            [$name, $capacity, $id]
        );
        $this->fix($id);
    }

    public function add(string $name, string $date, int $capacity, bool $locked = false): int
    {
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $this->executeUpdateQuery(
            "INSERT INTO events (name, date, capacity, locked, timestamp) VALUES (?, ?, ?, ?, ?)",
            "ssiis",
            [$name, $date, $capacity, $locked ? 1 : 0, $now]
        );

        $eventId = $this->fetchSingleValue("SELECT LAST_INSERT_ID()", "", []);
        if (!$eventId) {
            throw new Exception("Failed to create event");
        }

        return (int) $eventId;
    }

    public function lock(int $id): void
    {
        $this->executeUpdateQuery(
            "UPDATE events SET locked = 1 WHERE id = ?",
            "i",
            [$id]
        );
    }

    public function unlock(int $id): void
    {
        $this->executeUpdateQuery(
            "UPDATE events SET locked = 0 WHERE id = ?",
            "i",
            [$id]
        );
    }

    public function updateRegistrationComment(
        int $eventId,
        int $userId,
        string $comment
    ): void {
        $this->executeUpdateQuery(
            "UPDATE event_users SET comment = ? WHERE eventId = ? AND userId = ?",
            "sii",
            [$comment, $eventId, $userId]
        );
    }

    /** @return EventRegistration[] */
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

        return $this->fetchMappedRows(
            $query,
            "i",
            [$eventId],
            function ($row) {
                return new EventRegistration(
                    (int) $row['user_id'],
                    $row['username'],
                    $row['comment'] ?? '',
                    $row['timestamp'],
                    (int) $row['state']
                );
            }
        );
    }

    public function get(int $id, int $userId): ?Event
    {
        $rows = $this->fetchMappedRows(
            self::QUERY_EVENT_BY_ID,
            'ii',
            [$userId, $id],
            fn($row) => $this->mapEvent($row)
        );
        return $rows[0] ?? null;
    }

    /** @return Event[] */
    public function all(?DateTime $date = null): array
    {
        $query = self::QUERY_ALL_EVENTS;

        if ($date) {
            $query .= " AND MONTH(e.date) = ? AND YEAR(e.date) = ?";
            $userId = User::id();
            if ($userId === null) {
                return [];
            }
            return $this->fetchEvents(
                $query,
                'iii',
                [$userId, (int) $date->format('m'), (int) $date->format('Y')]
            );
        }

        $userId = User::id();
        if ($userId === null) {
            return [];
        }

        return $this->fetchEvents(
            $query,
            'i',
            [$userId]
        );
    }

    /** @return Event[] */
    public function getUpcoming(): array
    {
        $userId = User::id();
        if ($userId === null) {
            return [];
        }

        $query = self::QUERY_ALL_EVENTS . " AND e.date >= CURDATE() ORDER BY e.date ASC";
        return $this->fetchEvents(
            $query,
            'i',
            [$userId]
        );
    }

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

    public function unregister(int $eventId, int $userId): void
    {
        $this->executeUpdateQuery(
            "DELETE FROM event_users WHERE eventId = ? AND userId = ?",
            "ii",
            [$eventId, $userId]
        );

        $this->fix($eventId);
    }

    public function isLocked(int $eventId): bool
    {
        return (bool) $this->fetchSingleValue(
            "SELECT (locked = 1 OR date < NOW()) as isLocked FROM events WHERE id = ?",
            "i",
            [$eventId]
        );
    }

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

    /** @return Event[] */
    public function registeredEvents(int $userId): array
    {
        return $this->fetchEvents(
            self::QUERY_REGISTERED_EVENTS,
            'i',
            [$userId]
        );
    }
}