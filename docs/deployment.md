# Deployment and Setup Documentation

This guide covers the complete setup, configuration, and deployment process for the Golf El Faro application.

## System Requirements

### PHP Requirements
- **PHP Version**: 8.1 or higher
- **Required Extensions**:
  - `pdo_mysql` - MySQL database connectivity
  - `mysqli` - MySQL improved extension
  - `intl` - Internationalization support
  - `mbstring` - Multi-byte string support
  - `json` - JSON support
  - `session` - Session handling
  - `openssl` - Security functions

### Database Requirements
- **MySQL**: 8.0 or higher (recommended)
- **MariaDB**: 10.6 or higher (alternative)

### Server Requirements
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Memory**: 512MB RAM minimum, 1GB recommended
- **Storage**: 100MB minimum, 500MB recommended
- **Network**: HTTPS support required for production

## Installation

### 1. Clone Repository

```bash
git clone <repository-url> golf-el-faro
cd golf-el-faro
```

### 2. Configure Environment

Create environment configuration file:

```bash
cp .env.example .env
```

Edit `.env` with your configuration:

```env
# Application
APP_NAME="Golf El Faro"
APP_ENV=production
APP_URL=https://yourdomain.com
APP_TIMEZONE=Europe/Zurich
APP_LOCALE=de_CH

# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=TPDb
DB_USERNAME=TP
DB_PASSWORD=your_secure_password
DB_CHARSET=utf8mb4

# Security
SESSION_LIFETIME=3600
CSRF_TOKEN_NAME=_token
PASSWORD_MIN_LENGTH=8

# Logging
LOG_LEVEL=INFO
LOG_FILE=/path/to/logs/app.log
```

### 3. Database Setup

#### Create Database and User

```sql
CREATE DATABASE TPDb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'TP'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON TPDb.* TO 'TP'@'localhost';
FLUSH PRIVILEGES;
```

#### Initialize Database Schema

```bash
mysql -u TP -p TPDb < init.sql
```

#### Create Initial Admin User

```sql
INSERT INTO users (username, password, admin) VALUES 
('admin', '$2y$10$JVO3RR7VBMM06dyYMkSPKeVDk8CmtrdXYMdSktSaX0sSjVw.kAmCG', 1);
```

*Default password is 'admin123' - change immediately after first login*

### 4. File Permissions

Set appropriate file permissions:

```bash
# Create required directories
mkdir -p logs cache uploads

# Set permissions
chmod -R 755 src/
chmod -R 755 styles/
chmod -R 755 resources/
chmod -R 777 logs/
chmod -R 777 cache/
chmod -R 777 uploads/

# Secure sensitive files
chmod 600 .env
chmod 600 private/credential.*.php
```

### 5. Web Server Configuration

#### Apache Configuration

Create `.htaccess` in document root:

```apache
RewriteEngine On

# Redirect to HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Security headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# Cache static assets
<FilesMatch "\.(css|js|png|jpg|jpeg|gif|ico|svg)$">
    ExpiresActive On
    ExpiresDefault "access plus 1 month"
</FilesMatch>

# Deny access to sensitive files
<FilesMatch "\.(env|log|md)$">
    Order allow,deny
    Deny from all
</FilesMatch>

<Files "composer.json">
    Order allow,deny
    Deny from all
</Files>

<Directory "private/">
    Order allow,deny
    Deny from all
</Directory>

<Directory "logs/">
    Order allow,deny
    Deny from all
</Directory>
```

#### Nginx Configuration

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    root /path/to/golf-el-faro;
    index index.php;

    # SSL Configuration
    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";
    add_header Referrer-Policy "strict-origin-when-cross-origin";

    # Main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Security
        fastcgi_param HTTP_PROXY "";
        fastcgi_read_timeout 300;
    }

    # Static assets caching
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1M;
        add_header Cache-Control "public, immutable";
    }

    # Deny access to sensitive files
    location ~ /\.(env|git) {
        deny all;
    }

    location ~ \.(log|md)$ {
        deny all;
    }

    location /private/ {
        deny all;
    }

    location /logs/ {
        deny all;
    }
}
```

## Environment-Specific Configuration

### Development Environment

Create `config/development.php`:

```php
<?php
return [
    'app' => [
        'debug' => true,
    ],
    'logging' => [
        'level' => 'DEBUG',
    ],
    'database' => [
        'host' => 'localhost',
        'name' => 'TP_dev',
    ],
];
```

### Production Environment

Create `config/production.php`:

```php
<?php
return [
    'app' => [
        'debug' => false,
    ],
    'logging' => [
        'level' => 'WARNING',
        'file' => '/var/log/golf-el-faro/app.log',
    ],
    'security' => [
        'session_lifetime' => 7200, // 2 hours
    ],
];
```

### Testing Environment

Create `config/testing.php`:

```php
<?php
return [
    'app' => [
        'debug' => true,
    ],
    'database' => [
        'name' => 'TP_test',
    ],
    'logging' => [
        'level' => 'DEBUG',
        'file' => 'php://stderr',
    ],
];
```

## Security Configuration

### SSL/TLS Setup

1. **Obtain SSL Certificate**:
   - Use Let's Encrypt for free certificates
   - Or purchase from a trusted CA

2. **Configure HTTPS**:
   ```bash
   # Let's Encrypt example
   certbot --nginx -d yourdomain.com
   ```

3. **Test SSL Configuration**:
   - Use SSL Labs SSL Test
   - Verify all security headers

### Database Security

1. **Secure MySQL Installation**:
   ```bash
   mysql_secure_installation
   ```

2. **Database User Permissions**:
   - Create dedicated user for application
   - Grant only necessary privileges
   - Use strong passwords

3. **Connection Security**:
   - Use SSL for database connections
   - Restrict database access by IP

### File Security

1. **Sensitive File Protection**:
   ```bash
   # Move credentials outside web root
   mv private/ /etc/golf-el-faro/
   
   # Update paths in configuration
   ```

2. **Log File Security**:
   ```bash
   # Rotate logs
   /var/log/golf-el-faro/*.log {
       daily
       missingok
       rotate 52
       compress
       delaycompress
       notifempty
       create 644 www-data www-data
   }
   ```

## Performance Optimization

### PHP Configuration

Optimize `php.ini` for production:

```ini
# Memory and execution
memory_limit = 256M
max_execution_time = 30
max_input_time = 60

# File uploads
upload_max_filesize = 10M
post_max_size = 10M

# Sessions
session.gc_maxlifetime = 3600
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = "Strict"

# OPcache
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 4000
opcache.validate_timestamps = 0
opcache.save_comments = 0
```

### Database Optimization

1. **Indexing**:
   ```sql
   -- Add indexes for frequently queried columns
   CREATE INDEX idx_events_date ON events(date);
   CREATE INDEX idx_event_users_event_id ON event_users(eventId);
   CREATE INDEX idx_event_users_user_id ON event_users(userId);
   ```

2. **Query Optimization**:
   - Use prepared statements
   - Limit result sets
   - Avoid N+1 queries

### Caching Strategy

1. **Output Caching**:
   ```php
   // Cache rendered pages
   $cache = new FileCache('/path/to/cache');
   $key = "page_" . md5($request->getPath());
   
   if ($cache->has($key)) {
       return $cache->get($key);
   }
   
   $content = renderPage();
   $cache->set($key, $content, 3600);
   ```

2. **Database Query Caching**:
   ```php
   // Cache expensive queries
   $events = $cache->remember('upcoming_events', 1800, function() {
       return DB::$events->getUpcoming();
   });
   ```

## Monitoring and Maintenance

### Health Checks

Create health check endpoint:

```php
$router->get('/health', function(Request $request): Response {
    $checks = [
        'database' => checkDatabaseConnection(),
        'storage' => checkStorageAccess(),
        'memory' => memory_get_usage() < 200 * 1024 * 1024,
    ];
    
    $healthy = array_reduce($checks, fn($carry, $check) => $carry && $check, true);
    $status = $healthy ? HttpStatus::OK : HttpStatus::SERVICE_UNAVAILABLE;
    
    return Response::json(['status' => $healthy ? 'healthy' : 'unhealthy', 'checks' => $checks], $status);
});
```

### Log Monitoring

1. **Log Rotation**:
   ```bash
   # Setup logrotate
   /var/log/golf-el-faro/*.log {
       daily
       missingok
       rotate 52
       compress
       delaycompress
       notifempty
   }
   ```

2. **Error Alerting**:
   ```bash
   # Monitor error logs
   tail -f /var/log/golf-el-faro/app.log | grep ERROR
   ```

### Backup Strategy

1. **Database Backups**:
   ```bash
   #!/bin/bash
   # Daily database backup
   mysqldump -u backup_user -p TPDb | gzip > /backups/db-$(date +%Y%m%d).sql.gz
   
   # Cleanup old backups (keep 30 days)
   find /backups -name "db-*.sql.gz" -mtime +30 -delete
   ```

2. **File Backups**:
   ```bash
   #!/bin/bash
   # Backup application files
   tar -czf /backups/files-$(date +%Y%m%d).tar.gz \
       --exclude='logs/*' \
       --exclude='cache/*' \
       /path/to/golf-el-faro/
   ```

## Troubleshooting

### Common Issues

1. **Permission Errors**:
   ```bash
   # Fix file permissions
   chown -R www-data:www-data /path/to/golf-el-faro
   chmod -R 755 /path/to/golf-el-faro
   chmod -R 777 logs/ cache/
   ```

2. **Database Connection Issues**:
   ```bash
   # Test database connection
   mysql -u TP -p -h localhost TPDb
   
   # Check MySQL service
   systemctl status mysql
   ```

3. **PHP Extension Issues**:
   ```bash
   # Check installed extensions
   php -m | grep -E "(pdo_mysql|mysqli|intl)"
   
   # Install missing extensions (Ubuntu/Debian)
   apt-get install php8.1-mysql php8.1-intl php8.1-mbstring
   ```

### Debug Mode

Enable debug mode in development:

```php
// In config/development.php
return [
    'app' => [
        'debug' => true,
    ],
    'logging' => [
        'level' => 'DEBUG',
    ],
];
```

### Error Logs

Monitor application logs:

```bash
# Real-time log monitoring
tail -f logs/app.log

# Search for specific errors
grep "ERROR" logs/app.log | tail -20

# Check PHP error logs
tail -f /var/log/php_errors.log
```

## Deployment Checklist

### Pre-Deployment

- [ ] Environment configuration reviewed
- [ ] Database credentials secure
- [ ] SSL certificate installed and tested
- [ ] File permissions set correctly
- [ ] Log rotation configured
- [ ] Backup strategy implemented

### Deployment

- [ ] Code deployed to production server
- [ ] Database migrations applied
- [ ] Configuration files updated
- [ ] Web server configuration applied
- [ ] Services restarted
- [ ] Health checks passing

### Post-Deployment

- [ ] Application accessible via HTTPS
- [ ] All features tested
- [ ] Performance monitoring active
- [ ] Error monitoring configured
- [ ] Backup verification completed
- [ ] Documentation updated

## Maintenance Tasks

### Daily
- Monitor error logs
- Check application health
- Verify backups completed

### Weekly
- Review performance metrics
- Check security alerts
- Update dependencies if needed

### Monthly
- Security audit
- Performance optimization review
- Backup restoration test
- SSL certificate renewal check