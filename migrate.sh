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

mysql -h "$HOST" -P "$PORT" -u "$USER" -p"$PASS" "$NAME" <<'SQL'
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS male BOOLEAN NOT NULL DEFAULT 1;

ALTER TABLE event_guests
    ADD COLUMN IF NOT EXISTS male BOOLEAN NOT NULL DEFAULT 1;
SQL

echo "Done."
