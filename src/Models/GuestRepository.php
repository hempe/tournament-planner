<?php

declare(strict_types=1);

namespace TP\Models;

final class GuestRepository extends BaseRepository
{
    private function mapGuest(array $row): EventGuest
    {
        return new EventGuest(
            (int) $row['id'],
            (int) $row['event_id'],
            (bool) $row['male'],
            $row['first_name'],
            $row['last_name'],
            $row['email'] ?? null,
            isset($row['handicap']) ? (float) $row['handicap'] : null,
            $row['rfeg'] ?? null,
            $row['comment'] ?? null,
            $row['timestamp'],
        );
    }

    /** @return EventGuest[] */
    public function allForEvent(int $eventId): array
    {
        return $this->fetchMappedRows(
            'SELECT * FROM event_guests WHERE event_id = ? ORDER BY timestamp ASC',
            'i',
            [$eventId],
            fn($row) => $this->mapGuest($row)
        );
    }

    public function get(int $id): ?EventGuest
    {
        $stmt = $this->prepareAndExecute(
            'SELECT * FROM event_guests WHERE id = ?',
            'i',
            [$id]
        );
        $row = $this->fetchSingle($stmt);
        return $row ? $this->mapGuest($row) : null;
    }

    public function add(
        int $eventId,
        bool $male,
        string $firstName,
        string $lastName,
        ?string $email,
        ?float $handicap,
        ?string $rfeg,
        ?string $comment
    ): int {
        $maleInt = $male ? 1 : 0;
        $stmt = $this->prepareAndExecute(
            'INSERT INTO event_guests (event_id, male, first_name, last_name, email, handicap, rfeg, comment) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            'iisssdss',
            [$eventId, $maleInt, $firstName, $lastName, $email, $handicap, $rfeg, $comment]
        );
        $insertId = $this->conn->insert_id;
        $stmt->close();
        return $insertId;
    }

    public function update(
        int $id,
        bool $male,
        string $firstName,
        string $lastName,
        ?string $email,
        ?float $handicap,
        ?string $rfeg,
        ?string $comment
    ): void {
        $maleInt = $male ? 1 : 0;
        $this->executeUpdateQuery(
            'UPDATE event_guests SET male = ?, first_name = ?, last_name = ?, email = ?, handicap = ?, rfeg = ?, comment = ? WHERE id = ?',
            'isssdssi',
            [$maleInt, $firstName, $lastName, $email, $handicap, $rfeg, $comment, $id]
        );
    }

    public function delete(int $id): void
    {
        $this->executeUpdateQuery(
            'DELETE FROM event_guests WHERE id = ?',
            'i',
            [$id]
        );
    }
}
