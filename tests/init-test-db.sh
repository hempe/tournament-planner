#!/bin/bash

# Initialize test database for integration tests

DB_USER="${DB_USERNAME:-TP}"
DB_PASS="${DB_PASSWORD:-g0lf3lf4r0}"
DB_NAME="${DB_NAME:-TP_test}"

echo "Initializing test database: $DB_NAME"
echo "Checking database permissions..."

# Test if user can create databases
if mysql -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME}_permission_test; DROP DATABASE IF EXISTS ${DB_NAME}_permission_test;" &>/dev/null; then
    echo "Using user: $DB_USER"
    MYSQL_CMD="mysql -u $DB_USER -p$DB_PASS"
    USE_SUDO=false
else
    echo "User $DB_USER doesn't have CREATE DATABASE privileges."
    echo "Using sudo mysql (root)..."
    MYSQL_CMD="sudo mysql"
    USE_SUDO=true
fi

# Drop and create test database
$MYSQL_CMD <<EOF
DROP DATABASE IF EXISTS $DB_NAME;
CREATE DATABASE $DB_NAME;
USE $DB_NAME;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    admin BOOLEAN NOT NULL
);

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    capacity INT NOT NULL,
    timestamp DATETIME NOT NULL,
    locked BOOLEAN NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS event_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userId INT NOT NULL,
    eventId INT NOT NULL,
    comment VARCHAR(2048),
    state INT NOT NULL,
    timestamp DATETIME NULL,
    CONSTRAINT FK_EVENT_USERS FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE ON UPDATE NO ACTION,
    CONSTRAINT FK_USER_EVENTS FOREIGN KEY (eventId) REFERENCES events(id) ON DELETE CASCADE ON UPDATE NO ACTION
);

ALTER TABLE event_users ADD UNIQUE KEY unique_user_event (userId, eventId);
EOF

# Grant permissions to TP user if we ran as root
if [ "$USE_SUDO" = true ]; then
    sudo mysql <<EOF
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF
fi

if [ $? -eq 0 ]; then
    echo "✓ Test database initialized successfully!"
    exit 0
else
    echo "✗ Failed to initialize test database"
    exit 1
fi
