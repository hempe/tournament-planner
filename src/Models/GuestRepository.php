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
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            (float) $row['handicap'],
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
        string $firstName,
        string $lastName,
        string $email,
        float $handicap,
        ?string $rfeg,
        ?string $comment
    ): int {
        $stmt = $this->prepareAndExecute(
            'INSERT INTO event_guests (event_id, first_name, last_name, email, handicap, rfeg, comment) VALUES (?, ?, ?, ?, ?, ?, ?)',
            'isssdss',
            [$eventId, $firstName, $lastName, $email, $handicap, $rfeg, $comment]
        );
        $insertId = $this->conn->insert_id;
        $stmt->close();
        return $insertId;
    }

    public function update(
        int $id,
        string $firstName,
        string $lastName,
        string $email,
        float $handicap,
        ?string $rfeg,
        ?string $comment
    ): void {
        $this->executeUpdateQuery(
            'UPDATE event_guests SET first_name = ?, last_name = ?, email = ?, handicap = ?, rfeg = ?, comment = ? WHERE id = ?',
            'sssdssi',
            [$firstName, $lastName, $email, $handicap, $rfeg, $comment, $id]
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
