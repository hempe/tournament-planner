<?php

declare(strict_types=1);

namespace TP\Models;

use TP\Core\Config;
use mysqli;
use mysqli_sql_exception;
use Exception;

final class DB
{
    public static EventRepository $events;
    public static UserRepository $users;
    private static mysqli $conn;

    public static function initialize(): void
    {
        self::$conn = self::createConnection();
        self::$events = new EventRepository(self::$conn);
        self::$users = new UserRepository(self::$conn);
    }

    private static function createConnection(): mysqli
    {
        $config = Config::getInstance();

        $host = (string) $config->get('database.host', 'localhost');
        $port = (int) $config->get('database.port', 3306);
        $username = (string) $config->get('database.username', 'root');
        $password = (string) $config->get('database.password', '');
        $database = (string) $config->get('database.name', 'TPDb');
        $charset = (string) $config->get('database.charset', 'utf8mb4');

        try {
            $conn = new mysqli($host, $username, $password, $database, $port);

            if ($conn->connect_error) {
                throw new Exception("Database connection failed: " . $conn->connect_error);
            }
        } catch (mysqli_sql_exception $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }

        $conn->set_charset($charset);

        return $conn;
    }

    public static function getConnection(): mysqli
    {
        return self::$conn;
    }
}