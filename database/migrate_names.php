<?php

/**
 * Migration: seed first_name / last_name from username.
 *
 * Rules:
 *  1. Strip academic/honorary title suffixes: ", Dr.", ", Dr", ", Prof.", ", Prof"
 *  2. Trim whitespace
 *  3. Split on whitespace
 *  4. last_name  = last token
 *  5. first_name = all remaining tokens joined by space
 *     (e.g. "Hansueli Sven Burri" → first="Hansueli Sven", last="Burri")
 *  6. If only one token: first_name = token, last_name = ""
 *
 * Run once on the production DB after deploying the schema migration that
 * added the first_name / last_name columns.
 *
 * Usage:
 *   php database/migrate_names.php
 */

declare(strict_types=1);

// Load .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
}

$host   = $_ENV['DB_HOST']     ?? 'localhost';
$port   = (int) ($_ENV['DB_PORT'] ?? 3306);
$dbname = $_ENV['DB_NAME']     ?? 'TPDb';
$user   = $_ENV['DB_USERNAME'] ?? 'TP';
$pass   = $_ENV['DB_PASSWORD'] ?? '';

$conn = new mysqli($host, $user, $pass, $dbname, $port);
if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error . PHP_EOL;
    exit(1);
}
$conn->set_charset('utf8mb4');

// Fetch users that still need seeding
$result = $conn->query("SELECT id, username FROM users WHERE first_name IS NULL OR first_name = ''");
if (!$result) {
    echo "Query failed: " . $conn->error . PHP_EOL;
    exit(1);
}

$updated = 0;
$skipped = 0;

while ($row = $result->fetch_assoc()) {
    $raw = $row['username'];

    // 1. Strip title suffixes (case-insensitive, comma-separated before name)
    $cleaned = preg_replace('/,\s*(Dr\.|Dr|Prof\.|Prof)\b/i', '', $raw);
    // Also strip leading titles
    $cleaned = preg_replace('/^(Dr\.|Dr|Prof\.|Prof)\s+/i', '', $cleaned ?? $raw);
    $cleaned = trim($cleaned ?? $raw);

    if ($cleaned === '') {
        // Fallback: use username as-is
        $cleaned = $raw;
    }

    // 2. Split on whitespace
    $parts = preg_split('/\s+/', $cleaned, -1, PREG_SPLIT_NO_EMPTY);

    if (empty($parts)) {
        echo "  SKIP  id={$row['id']} username=\"$raw\" (empty after cleaning)\n";
        $skipped++;
        continue;
    }

    $lastName  = array_pop($parts);
    $firstName = implode(' ', $parts); // empty string if only one token

    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE id = ?");
    $stmt->bind_param('ssi', $firstName, $lastName, $row['id']);
    $stmt->execute();
    $stmt->close();

    echo "  SET   id={$row['id']} username=\"$raw\" → first=\"$firstName\" last=\"$lastName\"\n";
    $updated++;
}

echo PHP_EOL . "Done: $updated updated, $skipped skipped." . PHP_EOL;

$conn->close();
