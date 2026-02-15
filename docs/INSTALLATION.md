# Installation Guide

Complete guide for installing Tournament Planner on your local machine or development environment.

## Prerequisites

### Required Software
- **PHP 8.1 or higher**
  ```bash
  php -v  # Check version
  ```

- **MySQL 8.0+ or MariaDB 10.6+**
  ```bash
  mysql --version
  ```

### Required PHP Extensions
- `pdo_mysql` - PDO MySQL driver
- `mysqli` - MySQL improved extension
- `intl` - Internationalization support
- `mbstring` - Multibyte string support

Check installed extensions:
```bash
php -m | grep -E "(pdo_mysql|mysqli|intl|mbstring)"
```

Enable missing extensions in `php.ini`:
```ini
extension=pdo_mysql
extension=mysqli
extension=intl
extension=mbstring
```

## Installation Steps

### 1. Get the Code

```bash
git clone https://github.com/your-repo/tournament-planner.git
cd tournament-planner
```

### 2. Configure Environment

Create environment configuration file:
```bash
cp .env.example .env
```

Edit `.env` with your settings:
```env
# Application
APP_ENV=development
APP_NAME="Golf El Faro"
APP_URL=http://localhost:5000
APP_TIMEZONE=Europe/Zurich
APP_LOCALE=de

# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=TPDb
DB_USERNAME=TP
DB_PASSWORD=your_secure_password
DB_CHARSET=utf8mb4

# Logging
LOG_LEVEL=DEBUG
LOG_FILE=logs/app.log

# Security
SESSION_LIFETIME=7200
```

### 3. Set Up Database

Create database and user:
```bash
mysql -u root -p <<EOF
CREATE DATABASE TPDb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'TP'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON TPDb.* TO 'TP'@'localhost';
FLUSH PRIVILEGES;
EOF
```

Load the database schema:
```bash
mysql -u root -p TPDb < database/init.sql
```

Verify tables were created:
```bash
mysql -u TP -p TPDb -e "SHOW TABLES;"
```

You should see:
```
+------------------+
| Tables_in_TPDb   |
+------------------+
| event_users      |
| events           |
| users            |
+------------------+
```

### 4. Create Directories

Create required directories:
```bash
mkdir -p logs
chmod 775 logs
```

### 5. Test Installation

Start the development server:
```bash
php -S localhost:5000 -c php.ini
```

Open http://localhost:5000 in your browser.

### 6. First Login

Default admin credentials:
- **Username**: `admin`
- **Password**: `Admin123!`

**Important**: Change the admin password immediately after first login!

## Verify Installation

### Check Health Endpoint

```bash
curl http://localhost:5000/health
```

Expected response:
```json
{
  "status": "ok",
  "database": "connected",
  "php_version": "8.1.x",
  "environment": "development"
}
```

### Test Database Connection

```bash
mysql -u TP -p -h localhost TPDb -e "SELECT COUNT(*) FROM users;"
```

Should return at least 1 (the admin user).

## Optional: Sample Data

Create additional test users:
```sql
INSERT INTO users (username, password, admin)
VALUES
  ('user1', '$argon2id$v=19$m=65536,t=4,p=3$...', 0),
  ('user2', '$argon2id$v=19$m=65536,t=4,p=3$...', 0);
```

Create sample events:
```sql
INSERT INTO events (name, date, capacity, locked)
VALUES
  ('Spring Tournament', '2026-04-15', 20, 0),
  ('Summer Cup', '2026-07-20', 24, 0);
```

## Troubleshooting

### Database Connection Fails

**Error**: `Access denied for user 'TP'@'localhost'`

**Solution**:
1. Check `.env` has correct credentials
2. Verify user exists: `SELECT User, Host FROM mysql.user WHERE User='TP';`
3. Check user permissions: `SHOW GRANTS FOR 'TP'@'localhost';`
4. Try connecting manually: `mysql -u TP -p`

### Missing PHP Extensions

**Error**: `Call to undefined function mysqli_connect`

**Solution**:
1. Check which extensions are loaded: `php -m`
2. Enable extensions in `php.ini`:
   - Find php.ini location: `php --ini`
   - Uncomment or add: `extension=mysqli`
3. Restart PHP: `sudo systemctl restart php8.1-fpm` (if using PHP-FPM)

### Port Already in Use

**Error**: `Failed to listen on localhost:5000`

**Solution**:
```bash
# Find what's using port 5000
lsof -i :5000

# Use a different port
php -S localhost:8080 -c php.ini
```

### Permission Denied on logs/

**Error**: `Failed to open stream: Permission denied`

**Solution**:
```bash
# Make logs directory writable
chmod 775 logs

# Or change ownership (if running as www-data)
sudo chown -R www-data:www-data logs
```

### Page Not Found (404) on All Routes

**Solution**:
1. Ensure you're using the PHP built-in server: `php -S localhost:5000`
2. Check that `index.php` exists in the project root
3. Access via `http://localhost:5000` (not file:// URLs)

### Session Errors

**Error**: `session_start(): Failed to read session data`

**Solution**:
```bash
# Check session directory exists and is writable
php -r "echo session_save_path();"

# Create if needed and set permissions
sudo mkdir -p /var/lib/php/sessions
sudo chmod 733 /var/lib/php/sessions
```

### Database Schema Errors

**Error**: `Table 'TPDb.users' doesn't exist`

**Solution**:
```bash
# Drop and recreate database
mysql -u root -p <<EOF
DROP DATABASE IF EXISTS TPDb;
CREATE DATABASE TPDb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOF

# Reload schema
mysql -u root -p TPDb < database/init.sql
```

## Development Tools

### Quick Run Script

Create `run.sh`:
```bash
#!/bin/bash
php -S localhost:5000 -c php.ini
```

Make it executable:
```bash
chmod +x run.sh
./run.sh
```

### Watch Logs

```bash
tail -f logs/app.log
```

### Database Backup

```bash
mysqldump -u TP -p TPDb > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Database Restore

```bash
mysql -u TP -p TPDb < backup_YYYYMMDD_HHMMSS.sql
```

## Next Steps

- [Learn how to deploy to production](DEPLOYMENT.md)
- [Learn how to develop new features](DEVELOPMENT.md)
- [Understand the application structure](APPLICATION.md)

## Getting Help

If you encounter issues not covered here:
1. Check `logs/app.log` for detailed error messages
2. Enable debug logging: Set `LOG_LEVEL=DEBUG` in `.env`
3. Review the [APPLICATION.md](APPLICATION.md) for architecture details
