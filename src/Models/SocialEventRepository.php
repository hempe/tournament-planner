<?php

declare(strict_types=1);

namespace TP\Models;

use DateTime;
use Exception;

final class SocialEventRepository extends BaseRepository
{
    private const QUERY_SOCIAL_EVENT = "SELECT
                se.id,
                se.tournamentId,
                se.name,
                se.date,
                se.description,
                se.registration_close,
                (se.locked = 1 OR se.date < NOW() OR (se.registration_close IS NOT NULL AND se.registration_close < NOW())) AS isLocked,
                COALESCE((SELECT SUM(st.capacity) FROM social_tables st WHERE st.socialEventId = se.id), 0) AS totalCapacity,
                (SELECT COUNT(*) FROM social_registrations sr WHERE sr.socialEventId = se.id) AS registered,
                (SELECT COUNT(*) FROM social_registrations sr WHERE sr.socialEventId = se.id AND sr.userId = ?) AS userRegistered
            FROM social_events se";

    private function mapSocialEvent(array $row): SocialEvent
    {
        return new SocialEvent(
            (int) $row['id'],
            isset($row['tournamentId']) ? (int) $row['tournamentId'] : null,
            $row['name'],
            $row['date'],
            $row['description'] ?? null,
            $row['registration_close'] ?? null,
            (bool) $row['isLocked'],
            (int) $row['totalCapacity'],
            (int) $row['registered'],
            (int) $row['userRegistered'],
        );
    }

    public function get(int $id, int $userId): ?SocialEvent
    {
        $rows = $this->fetchMappedRows(
            self::QUERY_SOCIAL_EVENT . " WHERE se.id = ?",
            'ii',
            [$userId, $id],
            fn($row) => $this->mapSocialEvent($row)
        );
        return $rows[0] ?? null;
    }

    /** @return SocialEvent[] */
    public function all(?DateTime $date = null): array
    {
        $userId = User::id() ?? 0;
        $query = self::QUERY_SOCIAL_EVENT . " WHERE 1=1";
        $types = 'i';
        $params = [$userId];

        if ($date) {
            $query .= " AND MONTH(se.date) = ? AND YEAR(se.date) = ?";
            $types .= 'ii';
            $params[] = (int) $date->format('m');
            $params[] = (int) $date->format('Y');
        }

        return $this->fetchMappedRows($query, $types, $params, fn($row) => $this->mapSocialEvent($row));
    }

    /** @return SocialEvent[] */
    public function allForGuest(?DateTime $date = null): array
    {
        $query = self::QUERY_SOCIAL_EVENT . " WHERE 1=1";
        $types = 'i';
        $params = [0];

        if ($date) {
            $query .= " AND MONTH(se.date) = ? AND YEAR(se.date) = ?";
            $types .= 'ii';
            $params[] = (int) $date->format('m');
            $params[] = (int) $date->format('Y');
        }

        return $this->fetchMappedRows($query, $types, $params, fn($row) => $this->mapSocialEvent($row));
    }

    public function getForTournament(int $tournamentId): ?SocialEvent
    {
        $userId = User::id() ?? 0;
        $rows = $this->fetchMappedRows(
            self::QUERY_SOCIAL_EVENT . " WHERE se.tournamentId = ?",
            'ii',
            [$userId, $tournamentId],
            fn($row) => $this->mapSocialEvent($row)
        );
        return $rows[0] ?? null;
    }

    public function add(
        string $name,
        string $date,
        ?int $tournamentId = null,
        ?string $description = null,
        ?string $registrationClose = null,
        string $menus = '',
        string $tables = '',
    ): int {
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $this->executeUpdateQuery(
            "INSERT INTO social_events (tournamentId, name, date, description, registration_close, locked, timestamp) VALUES (?, ?, ?, ?, ?, 0, ?)",
            'isssss',
            [$tournamentId, $name, $date, $description, $registrationClose, $now]
        );

        $id = (int) $this->fetchSingleValue("SELECT LAST_INSERT_ID()", "", []);
        if (!$id) {
            throw new Exception("Failed to create social event");
        }

        $this->setMenus($id, $menus);
        $this->setTables($id, $tables);

        return $id;
    }

    public function update(
        int $id,
        string $name,
        string $date,
        ?string $description = null,
        ?string $registrationClose = null,
        string $menus = '',
        string $tables = '',
    ): void {
        $this->executeUpdateQuery(
            "UPDATE social_events SET name = ?, date = ?, description = ?, registration_close = ? WHERE id = ?",
            'ssssi',
            [$name, $date, $description, $registrationClose, $id]
        );
        $this->setMenus($id, $menus);
        $this->setTables($id, $tables);
    }

    public function delete(int $id): void
    {
        $this->executeUpdateQuery("DELETE FROM social_events WHERE id = ?", 'i', [$id]);
    }

    public function lock(int $id): void
    {
        $this->executeUpdateQuery("UPDATE social_events SET locked = 1 WHERE id = ?", 'i', [$id]);
    }

    public function unlock(int $id): void
    {
        $this->executeUpdateQuery("UPDATE social_events SET locked = 0 WHERE id = ?", 'i', [$id]);
    }

    public function isLocked(int $id): bool
    {
        return (bool) $this->fetchSingleValue(
            "SELECT (locked = 1 OR date < NOW() OR (registration_close IS NOT NULL AND registration_close < NOW())) FROM social_events WHERE id = ?",
            'i',
            [$id]
        );
    }

    /** @return SocialMenu[] */
    public function menus(int $socialEventId): array
    {
        return $this->fetchMappedRows(
            "SELECT id, socialEventId, name FROM social_menus WHERE socialEventId = ? ORDER BY id",
            'i',
            [$socialEventId],
            fn($row) => new SocialMenu((int) $row['id'], (int) $row['socialEventId'], $row['name'])
        );
    }

    /** @return SocialTable[] */
    public function tables(int $socialEventId): array
    {
        return $this->fetchMappedRows(
            "SELECT st.id, st.socialEventId, st.number, st.capacity,
                COALESCE((SELECT COUNT(*) FROM social_registrations sr WHERE sr.tableId = st.id), 0) AS registered
             FROM social_tables st WHERE st.socialEventId = ? ORDER BY st.number",
            'i',
            [$socialEventId],
            fn($row) => new SocialTable(
                (int) $row['id'],
                (int) $row['socialEventId'],
                (int) $row['number'],
                (int) $row['capacity'],
                (int) $row['registered'],
            )
        );
    }

    /** @return SocialRegistration[] */
    public function registrations(int $socialEventId): array
    {
        return $this->fetchMappedRows(
            "SELECT sr.id, sr.socialEventId, sr.userId, sr.firstName, sr.lastName, sr.email,
                sr.tableId, st.number AS tableNumber,
                sr.menuId, sm.name AS menuName,
                sr.timestamp,
                u.first_name AS userFirstName, u.last_name AS userLastName, u.username
             FROM social_registrations sr
             LEFT JOIN social_tables st ON sr.tableId = st.id
             JOIN social_menus sm ON sr.menuId = sm.id
             LEFT JOIN users u ON sr.userId = u.id
             WHERE sr.socialEventId = ?
             ORDER BY sr.timestamp",
            'i',
            [$socialEventId],
            fn($row) => new SocialRegistration(
                (int) $row['id'],
                (int) $row['socialEventId'],
                isset($row['userId']) ? (int) $row['userId'] : null,
                $row['userId'] !== null ? ($row['userFirstName'] ?? $row['username']) : $row['firstName'],
                $row['userId'] !== null ? ($row['userLastName'] ?? null) : $row['lastName'],
                $row['userId'] !== null ? null : $row['email'],
                isset($row['tableId']) ? (int) $row['tableId'] : null,
                isset($row['tableNumber']) ? (int) $row['tableNumber'] : null,
                (int) $row['menuId'],
                $row['menuName'],
                $row['timestamp'],
            )
        );
    }

    public function getUserRegistration(int $socialEventId, int $userId): ?SocialRegistration
    {
        $rows = $this->fetchMappedRows(
            "SELECT sr.id, sr.socialEventId, sr.userId, sr.firstName, sr.lastName, sr.email,
                sr.tableId, st.number AS tableNumber,
                sr.menuId, sm.name AS menuName,
                sr.timestamp,
                u.first_name AS userFirstName, u.last_name AS userLastName, u.username
             FROM social_registrations sr
             LEFT JOIN social_tables st ON sr.tableId = st.id
             JOIN social_menus sm ON sr.menuId = sm.id
             LEFT JOIN users u ON sr.userId = u.id
             WHERE sr.socialEventId = ? AND sr.userId = ?",
            'ii',
            [$socialEventId, $userId],
            fn($row) => new SocialRegistration(
                (int) $row['id'],
                (int) $row['socialEventId'],
                (int) $row['userId'],
                $row['userFirstName'] ?? $row['username'],
                $row['userLastName'] ?? null,
                null,
                isset($row['tableId']) ? (int) $row['tableId'] : null,
                isset($row['tableNumber']) ? (int) $row['tableNumber'] : null,
                (int) $row['menuId'],
                $row['menuName'],
                $row['timestamp'],
            )
        );
        return $rows[0] ?? null;
    }

    public function register(int $socialEventId, int $userId, int $menuId, ?int $tableId): void
    {
        $this->executeUpdateQuery(
            "INSERT INTO social_registrations (socialEventId, userId, menuId, tableId) VALUES (?, ?, ?, ?)",
            'iiii',
            [$socialEventId, $userId, $menuId, $tableId]
        );
    }

    public function registerGuest(int $socialEventId, string $firstName, string $lastName, string $email, int $menuId, ?int $tableId): void
    {
        $this->executeUpdateQuery(
            "INSERT INTO social_registrations (socialEventId, firstName, lastName, email, menuId, tableId) VALUES (?, ?, ?, ?, ?, ?)",
            'isssii',
            [$socialEventId, $firstName, $lastName, $email, $menuId, $tableId]
        );
    }

    public function unregister(int $socialEventId, int $userId): void
    {
        $this->executeUpdateQuery(
            "DELETE FROM social_registrations WHERE socialEventId = ? AND userId = ?",
            'ii',
            [$socialEventId, $userId]
        );
    }

    public function deleteRegistration(int $registrationId): void
    {
        $this->executeUpdateQuery(
            "DELETE FROM social_registrations WHERE id = ?",
            'i',
            [$registrationId]
        );
    }

    public function isFull(int $socialEventId): bool
    {
        $available = (int) $this->fetchSingleValue(
            "SELECT COALESCE((SELECT SUM(capacity) FROM social_tables WHERE socialEventId = ?), 0)
                  - (SELECT COUNT(*) FROM social_registrations WHERE socialEventId = ?)",
            'ii',
            [$socialEventId, $socialEventId]
        );
        return $available <= 0;
    }

    public function isTableFull(int $tableId): bool
    {
        $available = (int) $this->fetchSingleValue(
            "SELECT capacity - (SELECT COUNT(*) FROM social_registrations WHERE tableId = ?)
             FROM social_tables WHERE id = ?",
            'ii',
            [$tableId, $tableId]
        );
        return $available <= 0;
    }

    public function menuBelongsToEvent(int $menuId, int $socialEventId): bool
    {
        return (bool) $this->fetchSingleValue(
            "SELECT COUNT(*) FROM social_menus WHERE id = ? AND socialEventId = ?",
            'ii',
            [$menuId, $socialEventId]
        );
    }

    public function tableBelongsToEvent(int $tableId, int $socialEventId): bool
    {
        return (bool) $this->fetchSingleValue(
            "SELECT COUNT(*) FROM social_tables WHERE id = ? AND socialEventId = ?",
            'ii',
            [$tableId, $socialEventId]
        );
    }

    public function getMenusAsString(int $socialEventId): string
    {
        $menus = $this->menus($socialEventId);
        return implode(', ', array_map(fn($m) => $m->name, $menus));
    }

    public function getTablesAsString(int $socialEventId): string
    {
        $tables = $this->tables($socialEventId);
        return implode(', ', array_map(fn($t) => (string) $t->capacity, $tables));
    }

    private function setMenus(int $socialEventId, string $menus): void
    {
        $this->executeUpdateQuery("DELETE FROM social_menus WHERE socialEventId = ?", 'i', [$socialEventId]);
        $names = array_filter(array_map('trim', explode(',', $menus)));
        foreach ($names as $name) {
            $this->executeUpdateQuery(
                "INSERT INTO social_menus (socialEventId, name) VALUES (?, ?)",
                'is',
                [$socialEventId, $name]
            );
        }
    }

    private function setTables(int $socialEventId, string $tables): void
    {
        $this->executeUpdateQuery("DELETE FROM social_tables WHERE socialEventId = ?", 'i', [$socialEventId]);
        $capacities = array_filter(array_map('trim', explode(',', $tables)));
        $number = 1;
        foreach ($capacities as $capacity) {
            if (is_numeric($capacity) && (int) $capacity > 0) {
                $this->executeUpdateQuery(
                    "INSERT INTO social_tables (socialEventId, number, capacity) VALUES (?, ?, ?)",
                    'iii',
                    [$socialEventId, $number, (int) $capacity]
                );
                $number++;
            }
        }
    }
}
