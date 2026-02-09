<?php
include('private/credential.dev.php');
require_once dirname(__FILE__) . '/EventRepository.php';
require_once dirname(__FILE__) . '/UserRepository.php';

class DB
{
    public static EventRepository $events;
    public static UserRepository $users;
    private static mysqli $conn;
    public static function initialize()
    {
        self::$conn = getConnection();
        self::$events = new EventRepository(self::$conn);
        self::$users = new UserRepository(self::$conn);
    }
}
