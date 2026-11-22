<?php

declare(strict_types=1);

namespace TP\Tests\Integration\Models;

use mysqli;
use PHPUnit\Framework\TestCase;

/** init.sql + database/migrations/amigos-legacy.sql on a copy of the old Amigos schema (made-up data). */
class AmigosMigrationTest extends TestCase
{
    private const DB = 'TP_test_migration';
    private mysqli $conn;

    protected function setUp(): void
    {
        $this->conn = new mysqli(
            $_ENV['DB_HOST'] ?? 'localhost', $_ENV['DB_USERNAME'] ?? 'TP', $_ENV['DB_PASSWORD'] ?? '', '', (int) ($_ENV['DB_PORT'] ?? 3306)
        );
        $this->conn->query('DROP DATABASE IF EXISTS ' . self::DB);
        $this->conn->query('CREATE DATABASE ' . self::DB . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->conn->select_db(self::DB);
        $this->runSql((string) file_get_contents(__DIR__ . '/fixtures/amigos-legacy-schema.sql'));
    }

    protected function tearDown(): void
    {
        $this->conn->query('DROP DATABASE IF EXISTS ' . self::DB);
        $this->conn->close();
    }

    private function runSql(string $sql): void
    {
        $sql = preg_replace('/^\s*(CREATE\s+DATABASE|USE)\b[^;]*;/mi', '', $sql) ?? $sql;
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if (preg_replace('/^(--[^\n]*\n|\s)+/', '', $statement . "\n") === '') {
                continue; // comment-only chunk
            }
            $this->conn->query($statement);
        }
    }

    private function value(string $sql): mixed
    {
        return $this->conn->query($sql)->fetch_row()[0] ?? null;
    }

    private function initSql(): string
    {
        return (string) file_get_contents(__DIR__ . '/../../../database/init.sql');
    }

    public function testInitSqlUpgradesTheLegacySchemaAndKeepsData(): void
    {
        $this->runSql($this->initSql());
        $this->runSql($this->initSql()); // idempotent

        $this->assertSame(4, (int) $this->value('SELECT COUNT(*) FROM users'));
        $this->assertSame(2, (int) $this->value('SELECT COUNT(*) FROM events'));
        $this->assertSame(3, (int) $this->value('SELECT COUNT(*) FROM event_users'));
        $this->assertSame('1200', (string) $this->value("SELECT prio FROM users WHERE username = 'Muster Max'"));
        $this->assertSame('12.4', (string) $this->value("SELECT handicap FROM users WHERE username = 'Muster Max'"));
        $this->assertSame('decimal(4,1)', (string) $this->value(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . self::DB . "' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'handicap'"
        ));
        $this->assertSame(1, (int) $this->value(
            "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '" . self::DB . "' AND TABLE_NAME = 'event_users' AND INDEX_NAME = 'unique_user_event' AND SEQ_IN_INDEX = 1"
        ));
        foreach (['event_guests', 'social_events', 'social_registrations'] as $table) {
            $this->assertSame(1, (int) $this->value(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . self::DB . "' AND TABLE_NAME = '$table'"
            ), $table);
        }
        $this->assertSame(1, (int) $this->value('SELECT MIN(mixed) FROM events'));
    }
}
