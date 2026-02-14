#!/bin/bash

# Cleanup test database after tests complete

set -e

DB_USER="${DB_USERNAME:-TP}"
DB_PASS="${DB_PASSWORD:-g0lf3lf4r0}"
DB_NAME="${DB_NAME:-TP_test}"

echo "Cleaning up test database: $DB_NAME"

# Try with regular user first, fall back to sudo mysql if needed
if mysql -u "$DB_USER" -p"$DB_PASS" -e "SHOW DATABASES;" &>/dev/null; then
    mysql -u "$DB_USER" -p"$DB_PASS" -e "DROP DATABASE IF EXISTS $DB_NAME;"
else
    sudo mysql -e "DROP DATABASE IF EXISTS $DB_NAME;"
fi

if [ $? -eq 0 ]; then
    echo "✓ Test database removed successfully!"
else
    echo "✗ Failed to remove test database"
    exit 1
fi
