<?php

declare(strict_types=1);

namespace TP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TP\Models\DB;

/**
 * Base class for integration tests
 * Provides database setup/teardown and HTTP testing utilities
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static bool $databaseInitialized = false;

    /**
     * Set up test database before running tests
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!self::$databaseInitialized) {
            self::initializeTestDatabase();
            self::$databaseInitialized = true;
        }
    }

    /**
     * Clean session before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        $_COOKIE = [];
    }

    /**
     * Initialize test database from schema
     */
    private static function initializeTestDatabase(): void
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? 3306;
        $dbName = $_ENV['DB_NAME'] ?? 'TP_test';
        $username = $_ENV['DB_USERNAME'] ?? 'TP';
        $password = $_ENV['DB_PASSWORD'] ?? 'g0lf3lf4r0';

        // Connect without database to create it
        $conn = new \mysqli($host, $username, $password, '', (int)$port);

        if ($conn->connect_error) {
            throw new \RuntimeException("Database connection failed: " . $conn->connect_error);
        }

        // Drop and recreate test database
        $conn->query("DROP DATABASE IF EXISTS $dbName");
        $conn->query("CREATE DATABASE $dbName");
        $conn->select_db($dbName);

        // Load schema
        $schema = file_get_contents(__DIR__ . '/../../database/init.sql');

        // Remove CREATE DATABASE and USE statements (we already created it)
        $schema = preg_replace('/CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+\w+\s*;/i', '', $schema);
        $schema = preg_replace('/USE\s+\w+\s*;/i', '', $schema);

        // Split schema into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $schema)),
            fn($s) => !empty($s)
        );

        // Execute each statement individually for better error reporting
        foreach ($statements as $i => $statement) {
            try {
                if (!$conn->query($statement)) {
                    throw new \RuntimeException(
                        "Schema execution failed on statement #" . ($i + 1) . ": " .
                        substr($statement, 0, 100) .
                        "\nError: " . $conn->error
                    );
                }
            } catch (\mysqli_sql_exception $e) {
                throw new \RuntimeException(
                    "Schema execution failed on statement #" . ($i + 1) . ": " .
                    substr($statement, 0, 200) .
                    "\nError: " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        $conn->close();

        // Initialize DB singleton
        DB::initialize();

        // Seed admin user
        self::seedAdminUser();
    }

    /**
     * Create default admin user for testing
     */
    private static function seedAdminUser(): void
    {
        $adminUsername = 'admin';
        $adminPassword = 'Admin123!';

        $hashedPassword = password_hash(
            $adminPassword,
            PASSWORD_ARGON2ID,
            ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3]
        );

        $conn = DB::getConnection();
        $stmt = $conn->prepare("INSERT INTO users (username, password, admin) VALUES (?, ?, 1)");
        $stmt->bind_param("ss", $adminUsername, $hashedPassword);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Clean up all data from tables (keep schema)
     */
    protected function cleanDatabase(): void
    {
        $conn = DB::getConnection();
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $conn->query("TRUNCATE TABLE event_users");
        $conn->query("TRUNCATE TABLE events");
        $conn->query("TRUNCATE TABLE users");
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");

        // Re-seed admin
        self::seedAdminUser();
    }

    /**
     * Simulate HTTP request
     */
    protected function request(
        string $method,
        string $uri,
        array $data = [],
        array $headers = []
    ): TestResponse {
        // Set up request globals
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_HOST'] = 'localhost';

        foreach ($headers as $key => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            $_POST = $data;
        } else {
            $_GET = $data;
        }

        // Capture output
        ob_start();

        try {
            require __DIR__ . '/../../index.php';
            $output = ob_get_clean();
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }

        return new TestResponse($output, http_response_code());
    }

    /**
     * Login as a user
     */
    protected function loginAs(string $username, string $password): void
    {
        $_POST['username'] = $username;
        $_POST['password'] = $password;

        $response = $this->request('POST', '/login', [
            'username' => $username,
            'password' => $password
        ]);
    }

    /**
     * Login as admin
     */
    protected function loginAsAdmin(): void
    {
        $this->loginAs('admin', 'Admin123!');
    }
}

/**
 * Test response wrapper
 */
class TestResponse
{
    public function __construct(
        public readonly string $body,
        public readonly int $statusCode
    ) {}

    public function assertOk(): self
    {
        if ($this->statusCode !== 200) {
            throw new \RuntimeException("Expected status 200, got {$this->statusCode}");
        }
        return $this;
    }

    public function assertRedirect(): self
    {
        if ($this->statusCode !== 302 && $this->statusCode !== 301) {
            throw new \RuntimeException("Expected redirect, got status {$this->statusCode}");
        }
        return $this;
    }

    public function assertContains(string $needle): self
    {
        if (strpos($this->body, $needle) === false) {
            throw new \RuntimeException("Response does not contain: {$needle}");
        }
        return $this;
    }
}
