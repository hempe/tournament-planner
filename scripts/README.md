# Scripts

Utility scripts for development and administration.

## Available Scripts

### hash_password.php

CLI tool for generating password hashes for manual user creation.

**Usage:**
```bash
php scripts/hash_password.php <username> <password>
```

**Example:**
```bash
php scripts/hash_password.php admin SecurePassword123!
```

**Output:**
- Hashed password using Argon2ID
- SQL INSERT command for new user
- SQL UPDATE command for existing user

This is useful for:
- Creating admin users manually
- Resetting forgotten passwords
- Bulk user imports
