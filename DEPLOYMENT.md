# Deployment Guide

Production deployment guide for Tournament Planner.

## Pre-Deployment Checklist

- [ ] Database configured with strong password
- [ ] `.env` file configured for production
- [ ] SSL certificate ready
- [ ] Web server (Apache/Nginx) installed
- [ ] PHP 8.1+ with required extensions
- [ ] Logs directory writable
- [ ] Backups configured

## Production Environment Setup

### 1. Configure Production .env

```env
APP_ENV=production
APP_NAME="Golf El Faro"
APP_URL=https://yourdomain.com
APP_TIMEZONE=Europe/Zurich
APP_LOCALE=de_CH

DB_HOST=localhost
DB_PORT=3306
DB_NAME=TPDb
DB_USERNAME=TP
DB_PASSWORD=strong_production_password
DB_CHARSET=utf8mb4

LOG_LEVEL=INFO
LOG_FILE=/var/www/golf-el-faro/logs/app.log

SESSION_LIFETIME=3600
```

### 2. Apache Configuration

Create `/etc/apache2/sites-available/golf-el-faro.conf`:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/golf-el-faro

    # Redirect to HTTPS
    Redirect permanent / https://yourdomain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/golf-el-faro

    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.com/privkey.pem

    <Directory /var/www/golf-el-faro>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # Route all requests through index.php
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.php [L]
    </Directory>

    # Security Headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"

    # Hide PHP version
    Header unset X-Powered-By
    ServerSignature Off

    # Logging
    ErrorLog ${APACHE_LOG_DIR}/golf-el-faro-error.log
    CustomLog ${APACHE_LOG_DIR}/golf-el-faro-access.log combined
</VirtualHost>
```

Enable required modules:
```bash
a2enmod rewrite ssl headers
a2ensite golf-el-faro
systemctl restart apache2
```

### 3. Nginx Configuration

Create `/etc/nginx/sites-available/golf-el-faro`:

```nginx
# Redirect HTTP to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    root /var/www/golf-el-faro;
    index index.php;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Hide server version
    server_tokens off;

    # Main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /\.env {
        deny all;
    }

    location ~ /\.git {
        deny all;
    }

    # Logging
    access_log /var/log/nginx/golf-el-faro-access.log;
    error_log /var/log/nginx/golf-el-faro-error.log;
}
```

Enable site:
```bash
ln -s /etc/nginx/sites-available/golf-el-faro /etc/nginx/sites-enabled/
nginx -t
systemctl restart nginx
```

### 4. File Permissions

Set proper ownership and permissions:

```bash
# Set ownership
chown -R www-data:www-data /var/www/golf-el-faro

# Application files (read-only)
find /var/www/golf-el-faro -type f -exec chmod 644 {} \;
find /var/www/golf-el-faro -type d -exec chmod 755 {} \;

# Make PHP files executable
chmod 755 /var/www/golf-el-faro/index.php
chmod 755 /var/www/golf-el-faro/bootstrap.php

# Writable logs directory
chmod 775 /var/www/golf-el-faro/logs
chown -R www-data:www-data /var/www/golf-el-faro/logs

# Protect sensitive files
chmod 600 /var/www/golf-el-faro/.env
```

### 5. SSL Certificate (Let's Encrypt)

Install Certbot:
```bash
# Ubuntu/Debian
apt-get install certbot python3-certbot-apache
# or
apt-get install certbot python3-certbot-nginx
```

Obtain certificate:
```bash
# For Apache
certbot --apache -d yourdomain.com -d www.yourdomain.com

# For Nginx
certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

Set up auto-renewal:
```bash
# Test renewal
certbot renew --dry-run

# Auto-renewal should already be set up via systemd timer
systemctl status certbot.timer
```

### 6. Database Optimization

Add indexes for production:
```sql
USE TPDb;

CREATE INDEX idx_events_date ON events(date);
CREATE INDEX idx_event_users_event_id ON event_users(eventId);
CREATE INDEX idx_event_users_user_id ON event_users(userId);
CREATE INDEX idx_users_username ON users(username);

-- Verify indexes
SHOW INDEX FROM events;
SHOW INDEX FROM event_users;
SHOW INDEX FROM users;
```

Configure MySQL for production:
```ini
# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
max_connections = 100
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
query_cache_type = 1
query_cache_size = 32M
```

Restart MySQL:
```bash
systemctl restart mysql
```

### 7. PHP Configuration

Production php.ini settings:
```ini
; Security
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; Performance
memory_limit = 256M
max_execution_time = 30
upload_max_filesize = 10M
post_max_size = 10M

; Session Security
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Strict
session.use_strict_mode = 1
session.gc_maxlifetime = 3600

; OpCache (for performance)
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
```

Restart PHP-FPM:
```bash
systemctl restart php8.1-fpm
```

### 8. Log Rotation

Create `/etc/logrotate.d/golf-el-faro`:
```
/var/www/golf-el-faro/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload php8.1-fpm > /dev/null 2>&1 || true
    endscript
}
```

Test log rotation:
```bash
logrotate -d /etc/logrotate.d/golf-el-faro
```

### 9. Firewall Configuration

Configure UFW (Ubuntu):
```bash
ufw allow 'Nginx Full'  # or 'Apache Full'
ufw allow OpenSSH
ufw enable
ufw status
```

Or iptables:
```bash
iptables -A INPUT -p tcp --dport 80 -j ACCEPT
iptables -A INPUT -p tcp --dport 443 -j ACCEPT
iptables-save > /etc/iptables/rules.v4
```

### 10. Monitoring Setup

#### Health Check Monitoring

Add to cron (`/etc/cron.d/golf-el-faro-health`):
```
*/5 * * * * www-data curl -sf https://yourdomain.com/health > /dev/null || echo "Health check failed" | mail -s "Golf El Faro Down" admin@example.com
```

#### Log Monitoring

Watch for errors:
```bash
tail -f /var/www/golf-el-faro/logs/app.log | grep ERROR
```

#### Disk Space Monitoring

```bash
df -h /var/www/golf-el-faro
```

## Backup Strategy

### Database Backups

Create backup script `/usr/local/bin/backup-golf-db.sh`:
```bash
#!/bin/bash
BACKUP_DIR="/var/backups/golf-el-faro"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

mysqldump -u TP -p'password' TPDb \
    | gzip > $BACKUP_DIR/db_backup_$DATE.sql.gz

# Keep only last 30 days
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +30 -delete
```

Add to cron (daily at 2 AM):
```bash
0 2 * * * /usr/local/bin/backup-golf-db.sh
```

### File Backups

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/golf-el-faro"
DATE=$(date +%Y%m%d_%H%M%S)

tar -czf $BACKUP_DIR/files_$DATE.tar.gz \
    /var/www/golf-el-faro \
    --exclude='/var/www/golf-el-faro/logs' \
    --exclude='/var/www/golf-el-faro/cache'

# Keep only last 7 days
find $BACKUP_DIR -name "files_*.tar.gz" -mtime +7 -delete
```

## Deployment Process

### Initial Deployment

```bash
# 1. Upload code
git clone https://github.com/your-repo/tournament-planner.git /var/www/golf-el-faro

# 2. Configure environment
cp .env.example .env
nano .env

# 3. Set permissions
chown -R www-data:www-data /var/www/golf-el-faro
chmod 600 .env

# 4. Initialize database
mysql -u root -p < init.sql

# 5. Test
curl https://yourdomain.com/health
```

### Updates/Redeployment

```bash
# 1. Backup first!
/usr/local/bin/backup-golf-db.sh

# 2. Put site in maintenance mode (optional)
# Create maintenance.html in document root

# 3. Pull latest code
cd /var/www/golf-el-faro
git pull origin main

# 4. Update database if needed
mysql -u TP -p TPDb < migrations/migration_YYYYMMDD.sql

# 5. Clear PHP OpCache
systemctl reload php8.1-fpm

# 6. Verify
curl https://yourdomain.com/health

# 7. Remove maintenance mode
```

## Post-Deployment Verification

### Checklist

- [ ] Site accessible via HTTPS
- [ ] HTTP redirects to HTTPS
- [ ] `/health` endpoint returns `{"status":"ok"}`
- [ ] Can login with admin account
- [ ] Database queries working
- [ ] Logs being written to `logs/app.log`
- [ ] SSL certificate valid
- [ ] Security headers present
- [ ] No PHP errors in logs

### Test Commands

```bash
# Check HTTPS
curl -I https://yourdomain.com

# Check health
curl https://yourdomain.com/health

# Check security headers
curl -I https://yourdomain.com | grep -E "(X-Frame-Options|X-Content-Type-Options)"

# Check logs
tail -20 /var/www/golf-el-faro/logs/app.log

# Check database
mysql -u TP -p TPDb -e "SELECT COUNT(*) FROM users;"
```

## Troubleshooting

### Site Not Accessible

1. Check web server status:
   ```bash
   systemctl status apache2  # or nginx
   ```

2. Check logs:
   ```bash
   tail -50 /var/log/apache2/error.log  # or /var/log/nginx/error.log
   ```

3. Verify DNS:
   ```bash
   dig yourdomain.com
   ```

### 500 Internal Server Error

1. Check PHP logs:
   ```bash
   tail -50 /var/www/golf-el-faro/logs/app.log
   ```

2. Check PHP-FPM status:
   ```bash
   systemctl status php8.1-fpm
   ```

3. Check file permissions:
   ```bash
   ls -la /var/www/golf-el-faro
   ```

### Database Connection Issues

1. Test connection:
   ```bash
   mysql -u TP -p -h localhost TPDb
   ```

2. Check `.env` configuration

3. Verify user permissions:
   ```sql
   SHOW GRANTS FOR 'TP'@'localhost';
   ```

## Security Hardening

### Additional Measures

1. **Rate Limiting** (Nginx):
   ```nginx
   limit_req_zone $binary_remote_addr zone=one:10m rate=10r/s;
   limit_req zone=one burst=20;
   ```

2. **Fail2ban** for failed login attempts

3. **Regular Security Updates**:
   ```bash
   apt-get update && apt-get upgrade
   ```

4. **Database User Restrictions**:
   ```sql
   REVOKE ALL PRIVILEGES ON *.* FROM 'TP'@'localhost';
   GRANT SELECT, INSERT, UPDATE, DELETE ON TPDb.* TO 'TP'@'localhost';
   ```

## Rollback Procedure

If deployment fails:

```bash
# 1. Restore database
mysql -u TP -p TPDb < /var/backups/golf-el-faro/db_backup_LATEST.sql.gz

# 2. Restore code
cd /var/www/golf-el-faro
git checkout previous-stable-tag

# 3. Restart services
systemctl restart apache2 php8.1-fpm
```

## Next Steps

- [Monitor application health](README.md#monitoring)
- [Review application logs regularly](INSTALLATION.md#watch-logs)
- [Set up automated backups](#backup-strategy)
- [Configure monitoring alerts](#health-check-monitoring)
