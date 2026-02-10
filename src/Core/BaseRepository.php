<?php
namespace TP\Core;
use \mysqli;

abstract class BaseRepository
{
    protected readonly mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Prepares and executes a query with parameters.
     *
     * @param string $query The SQL query.
     * @param string $types The types of the parameters (e.g., "i", "s", etc.).
     * @param array $params The parameters to bind.
     * @return \mysqli_stmt The prepared and executed statement.
     * @throws \Exception If the query fails.
     */
    protected function prepareAndExecute(string $query, string $types, array $params): \mysqli_stmt
    {
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            throw new \Exception("Prepare statement failed: " . $this->conn->error);
        }

        if (!empty($types) && !empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * Fetches all rows from a result set.
     *
     * @param \mysqli_stmt $stmt The executed statement.
     * @return array The fetched rows.
     */
    protected function fetchAll(\mysqli_stmt $stmt): array
    {
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    /**
     * Fetches a single row from a result set.
     *
     * @param \mysqli_stmt $stmt The executed statement.
     * @return array|null The fetched row or null if no rows exist.
     */
    protected function fetchSingle(\mysqli_stmt $stmt): ?array
    {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Executes an update query (e.g., INSERT, UPDATE, DELETE).
     *
     * @param string $query The SQL query to execute.
     * @param string $types The types of the parameters (e.g., "i", "s", etc.).
     * @param array $params The parameters to bind.
     * @throws \Exception If the query fails.
     */
    protected function executeUpdateQuery(string $query, string $types, array $params): void
    {
        $stmt = $this->prepareAndExecute($query, $types, $params);
        $stmt->close();
    }

    /**
     * Fetches a single value from a query result.
     *
     * @param string $query The SQL query to execute.
     * @param string $types The types of the parameters (e.g., "i", "s", etc.).
     * @param array $params The parameters to bind.
     * @return mixed The fetched value.
     * @throws \Exception If the query fails.
     */
    protected function fetchSingleValue(string $query, string $types, array $params): mixed
    {
        $stmt = $this->prepareAndExecute($query, $types, $params);
        $stmt->bind_result($value);
        $stmt->fetch();
        $stmt->close();
        return $value;
    }

    /**
     * Fetches rows from a query result and maps them using a callback.
     *
     * @param string $query The SQL query to execute.
     * @param string $types The types of the parameters (e.g., "i", "s", etc.).
     * @param array $params The parameters to bind.
     * @param callable $mapper A callback function to map each row to a desired format.
     * @return array The mapped rows.
     * @throws \Exception If the query fails.
     */
    protected function fetchMappedRows(string $query, string $types, array $params, callable $mapper): array
    {
        $stmt = $this->prepareAndExecute($query, $types, $params);
        $rows = $this->fetchAll($stmt);
        return array_map($mapper, $rows);
    }
}
