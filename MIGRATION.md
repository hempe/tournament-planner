# Migration Guide: Legacy to Production-Ready

This guide explains how to migrate from the legacy Golf El Faro application to the new production-ready version.

## Overview of Changes

### 🔧 Core Improvements

1. **Strong Typing**: All code now uses PHP 8.1+ strict types
2. **PSR Standards**: PSR-4 autoloading and PSR-12 coding standards
3. **Security**: Comprehensive security measures (CSRF, XSS, rate limiting)
4. **Architecture**: Modern router with middleware support
5. **Configuration**: Environment-based configuration management
6. **Internationalization**: Multi-language support
7. **Logging**: Structured logging with multiple levels
8. **Validation**: Robust input validation and sanitization

### 📁 File Structure Changes

**Legacy Structure:**
```
src/core/Router.php          → src/Core/RouterNew.php
src/core/Component.php       → src/Core/Component.php (enhanced)
src/core/DB.php             → src/Models/DB.php
src/core/Log.php            → src/Core/Logger.php
src/pages/*/routes.php      → Route definitions in index.php
```

**New Structure:**
```
src/
├── Core/           # Framework core classes
├── Controllers/    # Request handlers
├── Models/         # Data models
├── Components/     # UI components
├── Middleware/     # Request middleware
└── helpers.php     # Global functions
```

## Migration Steps

### 1. Backup Current System

```bash
# Backup database
mysqldump -u username -p TPDb > backup_$(date +%Y%m%d).sql

# Backup files
tar -czf backup_files_$(date +%Y%m%d).tar.gz /path/to/current/golf-el-faro/
```

### 2. Prepare New Environment

```bash
# Install new version alongside current
git clone <new-repository> golf-el-faro-new
cd golf-el-faro-new

# Install dependencies
composer install

# Copy environment configuration
cp .env.example .env
# Edit .env with your current database credentials
```

### 3. Database Migration

The database schema remains the same, but you may want to add indexes for better performance:

```sql
-- Recommended indexes for production
CREATE INDEX idx_events_date ON events(date);
CREATE INDEX idx_event_users_event_id ON event_users(eventId);
CREATE INDEX idx_event_users_user_id ON event_users(userId);
CREATE INDEX idx_users_username ON users(username);
```

### 4. Data Migration

No data migration needed - the new system uses the same database schema.

### 5. Configuration Migration

**Legacy Configuration (`private/credential.dev.php`):**
```php
function getConnection() {
    return new mysqli("localhost", "username", "password", "database");
}
```

**New Configuration (`.env`):**
```env
DB_HOST=localhost
DB_USERNAME=username
DB_PASSWORD=password
DB_NAME=database
```

### 6. Code Migration Patterns

#### Router Migration

**Legacy Route Definition:**
```php
// src/pages/events/routes.php
function getEventRoutes(string $method): RouterBuilder {
    $routes = new RouterBuilder('events', dirname(__FILE__), $method);
    return $routes->view(
        title: 'Events',
        file: 'list.php',
        require: User::admin()
    );
}
```

**New Route Definition:**
```php
// index.php
$router->get('/events', [EventController::class, 'index'], [new AuthMiddleware()]);
```

#### Component Migration

**Legacy Component:**
```php
// Direct echo in template
echo <<<HTML
    <div class="card">
        <h2>{$this->title}</h2>
        <p>{$this->content}</p>
    </div>
HTML;
```

**New Component (Security Enhanced):**
```php
// Automatic escaping
echo <<<HTML
    <div class="card">
        <h2>{$this->escapeHtml($this->title)}</h2>
        <p>{$this->escapeHtml($this->content)}</p>
    </div>
HTML;
```

#### Security Migration

**Legacy (Vulnerable):**
```php
$name = $_POST['name'];
echo "Hello " . $name;
```

**New (Secure):**
```php
$name = $request->getString('name');
echo "Hello " . e($name);
```

### 7. Feature Mapping

| Legacy Feature | New Implementation |
|---|---|
| `$_GET/$_POST` access | `$request->get()`, `$request->getString()` |
| Manual routing | Middleware-based routing |
| `Log::trace()` | `logger()->debug()` |
| Session errors | Flash messages |
| Direct SQL | Repository pattern |
| Hard-coded strings | Translation functions `__()` |

### 8. Testing Migration

1. **Set up test environment:**
   ```bash
   cp .env .env.testing
   # Set APP_ENV=testing in .env.testing
   ```

2. **Test core functionality:**
   - User authentication
   - Event creation/editing
   - User registration for events
   - Admin functions

3. **Performance testing:**
   ```bash
   # Basic performance test
   ab -n 1000 -c 10 http://localhost:5000/
   ```

### 9. Deployment Strategy

#### Option A: Blue-Green Deployment
1. Deploy new version to separate environment
2. Test thoroughly
3. Switch traffic when confident
4. Keep old version as rollback

#### Option B: Gradual Migration
1. Deploy new version to subdirectory
2. Migrate users gradually
3. Update DNS when complete

### 10. Post-Migration Tasks

1. **Update web server configuration:**
   - Enable security headers
   - Configure SSL/HTTPS
   - Set up log rotation

2. **Monitor and optimize:**
   - Check error logs
   - Monitor performance
   - Optimize database queries

3. **User training:**
   - Document any UI changes
   - Train administrators on new features

## Breaking Changes

### Removed Features
- Legacy router system
- Direct superglobal access
- Manual file inclusion
- Untyped function parameters

### Changed APIs
- All classes now use namespaces
- Constructor parameters are strongly typed
- Method signatures require type declarations

### New Requirements
- PHP 8.1+ (was PHP 7.4+)
- Composer for autoloading
- Environment configuration

## Rollback Plan

If issues arise during migration:

1. **Immediate rollback:**
   ```bash
   # Switch web server to old directory
   # Restore old DNS/routing
   ```

2. **Database rollback:**
   ```sql
   -- Only if schema changes were made
   -- Restore from backup
   ```

3. **Investigation:**
   - Check error logs
   - Identify specific issues
   - Plan fixes for next deployment

## Benefits After Migration

### Security Improvements
- ✅ CSRF protection on all forms
- ✅ XSS prevention through automatic escaping
- ✅ Rate limiting to prevent abuse
- ✅ Secure password hashing (Argon2ID)
- ✅ Input validation and sanitization

### Performance Improvements
- ✅ PSR-4 autoloading (no manual includes)
- ✅ Optimized routing system
- ✅ Built-in caching support
- ✅ Database query optimization

### Maintainability Improvements
- ✅ Strong typing throughout
- ✅ Comprehensive documentation
- ✅ Standardized code style
- ✅ Unit testing framework
- ✅ Error handling and logging

### Feature Improvements
- ✅ Multi-language support
- ✅ Responsive design components
- ✅ Admin interface improvements
- ✅ Better error messages
- ✅ Health monitoring endpoints

## Support During Migration

### Documentation
- Component system: `docs/components.md`
- Routing system: `docs/routing.md`
- Deployment guide: `docs/deployment.md`

### Troubleshooting
- Check application logs: `logs/app.log`
- Enable debug mode in development
- Use health check endpoint: `/health`

### Getting Help
1. Review documentation in `docs/` directory
2. Check troubleshooting section in deployment guide
3. Enable debug logging for detailed error information

---

**Migration Timeline Recommendation:**
- Week 1: Set up new environment and basic testing
- Week 2: Comprehensive testing and user acceptance
- Week 3: Production deployment and monitoring
- Week 4: Optimization and cleanup