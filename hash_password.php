<?php

require_once __DIR__ . '/bootstrap.php';

use TP\Core\Security;

if ($argc !== 3) {
    echo "Usage: php hash_password.php <username> <password>\n";
    exit(1);
}

$username = $argv[1];
$password = $argv[2];

$hashedPassword = Security::getInstance()->hashPassword($password);

echo "Username: {$username}\n";
echo "Hashed password: {$hashedPassword}\n";
echo "\n";
echo "SQL command to insert user:\n";
echo "INSERT INTO users (username, password, admin) VALUES ('{$username}', '{$hashedPassword}', 0);\n";
echo "\n";
echo "SQL command to update existing user:\n";
echo "UPDATE users SET password = '{$hashedPassword}' WHERE username = '{$username}';\n";