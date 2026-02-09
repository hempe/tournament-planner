#!/bin/bash

# Seed script for creating default admin user

DB_USER="TP"
DB_PASS="g0lf3lf4r0"
DB_NAME="TPDb"

ADMIN_USERNAME="admin"
ADMIN_PASSWORD="Admin123!"

echo "Checking if admin user exists..."

# Check if user exists
USER_EXISTS=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM users WHERE username='$ADMIN_USERNAME'")

if [ "$USER_EXISTS" -gt 0 ]; then
    echo "Admin user '$ADMIN_USERNAME' already exists. Skipping."
    exit 0
fi

echo "Creating admin user..."

# Generate password hash using PHP with the same algorithm as the application
HASHED_PASSWORD=$(php -r "echo password_hash('$ADMIN_PASSWORD', PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3]);")

# Insert admin user
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "INSERT INTO users (username, password, admin) VALUES ('$ADMIN_USERNAME', '$HASHED_PASSWORD', 1);"

if [ $? -eq 0 ]; then
    echo "✓ Admin user created successfully!"
    echo ""
    echo "Login credentials:"
    echo "  Username: $ADMIN_USERNAME"
    echo "  Password: $ADMIN_PASSWORD"
    echo ""
    echo "IMPORTANT: Please change this password after first login!"
else
    echo "✗ Failed to create admin user"
    exit 1
fi
