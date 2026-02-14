#!/bin/bash

# Grant test database permissions to TP user
# Run this once: sudo ./tests/grant-test-permissions.sh

DB_USER="${DB_USERNAME:-TP}"

echo "Granting test database privileges to user: $DB_USER"

mysql <<EOF
GRANT CREATE, DROP, REFERENCES, ALTER, SELECT, INSERT, UPDATE, DELETE ON *.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF

if [ $? -eq 0 ]; then
    echo "✓ Permissions granted successfully!"
    echo ""
    echo "User $DB_USER can now:"
    echo "  - Create/drop test databases"
    echo "  - Create tables with foreign keys"
    echo "  - Alter table structure"
    echo "  - Select/insert/update/delete data"
    echo "  - Run integration tests"
else
    echo "✗ Failed to grant permissions"
    exit 1
fi
