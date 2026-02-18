#!/bin/bash

set -e

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

echo "Running database/init.sql on $NAME@$HOST..."
mariadb -h "$HOST" -P "$PORT" -u "$USER" -p"$PASS" < database/init.sql
echo "Done."
