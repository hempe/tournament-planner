#!/bin/bash

set -e

# Load .env
if [ ! -f ".env" ]; then
    echo "Error: .env file not found"
    exit 1
fi

export $(grep -v '^#' .env | grep -v '^$' | xargs)

HOST="${DB_HOST:-localhost}"
PORT="${DB_PORT:-3306}"
NAME="${DB_NAME:-TPDb}"
USER="${DB_USERNAME:-root}"
PASS="${DB_PASSWORD:-}"

echo "Running migrations on $NAME@$HOST..."

mariadb -h "$HOST" -P "$PORT" -u "$USER" -p"$PASS" "$NAME" <<'SQL'
-- Create event_guests table if it doesn't exist
CREATE TABLE IF NOT EXISTS event_guests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    male BOOLEAN NOT NULL DEFAULT 1,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    handicap DECIMAL(4,1) NOT NULL,
    rfeg VARCHAR(255),
    comment VARCHAR(2048),
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT FK_GUEST_EVENT FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

-- Add male column to existing tables if not already present
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS male BOOLEAN NOT NULL DEFAULT 1;

ALTER TABLE event_guests
    ADD COLUMN IF NOT EXISTS male BOOLEAN NOT NULL DEFAULT 1;
SQL

echo "Done."
