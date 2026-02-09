<?php

declare(strict_types=1);

namespace TP\Models;

use mysqli;
use mysqli_stmt;
use Exception;

abstract class BaseRepository
{
    public function __construct(protected readonly mysqli $conn) {}

    protected function prepareAndExecute(string $query, string $types, array $params): mysqli_stmt
    {
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $this->conn->error);
        }

        if (!empty($types) && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt;
    }

    protected function fetchAll(mysqli_stmt $stmt): array
    {
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    protected function fetchSingle(mysqli_stmt $stmt): ?array
    {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    protected function executeUpdateQuery(string $query, string $types, array $params): void
    {
        $stmt = $this->prepareAndExecute($query, $types, $params);
        $stmt->close();
    }

    protected function fetchSingleValue(string $query, string $types, array $params): mixed
    {
        $stmt = $this->prepareAndExecute($query, $types, $params);
        $stmt->bind_result($value);
        $stmt->fetch();
        $stmt->close();
        return $value;
    }

    protected function fetchMappedRows(string $query, string $types, array $params, callable $mapper): array
    {
        $stmt = $this->prepareAndExecute($query, $types, $params);
        $rows = $this->fetchAll($stmt);
        return array_map($mapper, $rows);
    }
}