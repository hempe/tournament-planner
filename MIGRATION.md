# Migration Guide: Completed Modernization

This document describes the completed migration of the Golf El Faro application from a legacy RouterBuilder system to a modern production-ready architecture.

## Migration Status: ✅ COMPLETE

The application has been fully migrated to use modern PHP patterns, Request/Response architecture, and middleware-based routing.

## Overview of Changes

### 🔧 Core Improvements

1. **Strong Typing**: All code now uses PHP 8.1+ strict types with `declare(strict_types=1)`
2. **PSR-4 Autoloading**: Proper namespace structure and autoloading (one class per file)
3. **Modern Routing**: Request/Response pattern with middleware pipeline support
4. **Configuration Management**: Environment-based `.env` configuration system
5. **Security**: Input sanitization, validation, and authentication middleware
6. **Structured Project**: Clean separation of concerns (Controllers, Models, Views, Components)
7. **Logging**: Logger with multiple log levels
8. **Validation**: Robust ValidationRule system with error handling

### 📁 Current File Structure

```
src/
├── Core/              # Framework core classes
│   ├── Application.php
│   ├── Router.php
│   ├── Request.php
│   ├── Response.php
│   ├── Config.php
│   ├── Validator.php
│   ├── ValidationRule.php
│   ├── ValidationError.php
│   ├── ValidationResult.php
│   ├── MiddlewareInterface.php
│   ├── RouteGroup.php
│   └── Component.php
├── Controllers/       # HTTP request handlers
│   ├── AuthController.php
│   ├── EventController.php
│   ├── HomeController.php
│   └── UserController.php
├── Models/           # Data models and repositories
│   ├── DB.php
│   ├── User.php
│   ├── EventRepository.php
│   └── UserRepository.php
├── Middleware/       # Route middleware
│   ├── AuthMiddleware.php
│   └── AdminMiddleware.php
├── Views/            # View templates (organized by feature)
│   ├── Events/
│   ├── Home/
│   └── Users/
├── Components/       # Reusable UI components
└── Layout/           # Header and footer templates

Configuration:
├── .env              # Environment configuration (not in git)
├── config/           # Environment-specific config files
│   ├── development.php
│   ├── production.php
│   └── testing.php
└── bootstrap.php     # Application bootstrap

Entry Point:
└── index.php         # Route definitions and app initialization
```

## What Was Migrated

### 1. Router System ✅

**Old System (Removed):**
- `RouterBuilder` with view() method and action callbacks
- Route files in `src/pages/*/routes.php`
- `getRouterBuilder()` function for route dispatching
- Legacy `Route`, `RouteItem`, `RouterView`, `RouterAction` classes

**New System (Current):**
- Modern `Router` class with `get()`, `post()`, `put()`, `delete()` methods
- Route definitions in `index.php` using controller references
- Middleware pipeline support (`AuthMiddleware`, `AdminMiddleware`)
- Nested route groups with `RouteGroup` class
- URL parameter extraction (e.g., `/events/{id}`)

### 2. Request/Response Pattern ✅

**Old System (Removed):**
- Direct `$_GET`, `$_POST`, `$_SESSION` access
- Manual header() calls and output buffering
- No structured request/response objects

**New System (Current):**
- `Request` object with sanitization and validation
- `Response` object with static constructors (ok, redirect, json, etc.)
- Type-safe getter methods: `getString()`, `getInt()`, `getBool()`
- Built-in validation via `$request->validate()`

### 3. Configuration System ✅

**Old System (Removed):**
- `private/credential.dev.php` with hardcoded credentials
- `private/credential.live.php` for production
- `getConnection()` function

**New System (Current):**
- `.env` file for environment variables
- `Config` class with `get()` method for nested keys
- Environment-specific config files in `config/` directory
- Automatic merging of environment configs

### 4. Directory Structure ✅

**Old Structure (Removed):**
- `src/pages/events/views/` - Event views
- `src/pages/home/views/` - Home views
- `src/pages/users/views/` - User views
- `src/pages/*/routes.php` - Route definitions
- `src/Components/Components/` - Nested components directory
- `private/` - Credential files

**New Structure (Current):**
- `src/Views/Events/` - Event views
- `src/Views/Home/` - Home views
- `src/Views/Users/` - User views
- `index.php` - All route definitions
- `src/Components/` - Flat components directory
- `.env` - Configuration file (gitignored)

### 5. PSR-4 Autoloading ✅

**Changes Made:**
- Split `Validator.php` into separate files:
  - `ValidationRule.php`
  - `ValidationError.php`
  - `ValidationResult.php`
- Moved Components from nested directory to proper namespace path
- Removed old `src/Core/Index.php` manual include file
- All files follow one-class-per-file convention

### 6. Database Layer ✅

**Old System (Removed):**
- `src/Core/DB.php` with credential include
- Manual `require_once` for repositories

**New System (Current):**
- `src/Models/DB.php` using Config system
- Automatic connection from `.env` credentials
- Proper error handling and charset configuration
- Repository pattern maintained (EventRepository, UserRepository)

## Code Migration Examples

### Router Migration

**Before (Removed):**
```php
// src/pages/events/routes.php
function getEventRoutes(string $method): RouterBuilder {
    $routes = new RouterBuilder('/events', dirname(__FILE__), $method);

    if ($method == 'GET') {
        $routes->view('list.php', 'Events', User::loggedIn(), '', [
            'register' => fn($userId, $comment) => DB::$events->register($id, $userId, $comment)
        ]);
    }

    return $routes;
}

// src/pages/routes.php
function getRouterBuilder(string $request, string $method): RouterBuilder {
    if (str_starts_with($request, '/events')) {
        require_once dirname(__FILE__) . '/events/routes.php';
        return getEventRoutes($method);
    }
    // ...
}
```

**After (Current):**
```php
// index.php
use TP\Core\RouteGroup;
use TP\Middleware\AuthMiddleware;
use TP\Controllers\EventController;

$router->group(
    new RouteGroup('/events', [new AuthMiddleware()]),
    function (Router $router) {
        $router->get('/', [EventController::class, 'index']);
        $router->get('/{id}', [EventController::class, 'show']);
        $router->post('/{id}/register', [EventController::class, 'register']);
    }
);
```

### Controller Migration

**Before (Views were loaded directly):**
```php
// src/pages/events/routes.php
$routes->view('list.php', 'Events', User::loggedIn());

// src/pages/events/views/list.php
<?php
require __DIR__ . '/../../Layout/header.php';
$events = DB::$events->all();
// ... render HTML ...
require __DIR__ . '/../../Layout/footer.php';
```

**After (Controller pattern):**
```php
// src/Controllers/EventController.php
final class EventController
{
    public function index(Request $request): Response
    {
        $events = DB::$events->all();

        ob_start();
        require __DIR__ . '/../Views/Events/List.php';
        $content = ob_get_clean();

        return Response::ok($content);
    }
}
```

### Request Handling Migration

**Before:**
```php
// Direct superglobal access
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['popup_error'] = 'Required fields missing';
    header('Location: /login');
    exit;
}
```

**After:**
```php
// Type-safe Request object with validation
public function login(Request $request): Response
{
    $validation = $request->validate([
        new ValidationRule('username', ['required', 'string']),
        new ValidationRule('password', ['required', 'string']),
    ]);

    if (!$validation->isValid) {
        flash('error', __('auth.required_fields'));
        return Response::redirect('/login');
    }

    $data = $request->getValidatedData();
    // ... authenticate ...
}
```

### Configuration Migration

**Before:**
```php
// private/credential.dev.php
<?php
function getConnection(): mysqli {
    return new mysqli("localhost", "TP", "password", "TPDb");
}

// src/Core/DB.php
include('private/credential.dev.php');
self::$conn = getConnection();
```

**After:**
```php
// .env
DB_HOST=localhost
DB_USERNAME=TP
DB_PASSWORD=password
DB_NAME=TPDb

// src/Models/DB.php
public static function initialize(): void
{
    $config = Config::getInstance();

    self::$conn = new mysqli(
        (string) $config->get('database.host'),
        (string) $config->get('database.username'),
        (string) $config->get('database.password'),
        (string) $config->get('database.name'),
        (int) $config->get('database.port')
    );
}
```

### Middleware Pattern

**Before (Manual checks in every route):**
```php
// Repeated in every route handler
if (!User::loggedIn()) {
    header('Location: /login');
    exit;
}

if (!User::admin()) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
```

**After (Middleware pipeline):**
```php
// src/Middleware/AuthMiddleware.php
final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!User::loggedIn()) {
            return Response::redirect('/login');
        }
        return $next($request);
    }
}

// Applied at route level
$router->get('/events', [EventController::class, 'index'], [new AuthMiddleware()]);

// Or at group level
$router->group(
    new RouteGroup('/admin', [new AuthMiddleware(), new AdminMiddleware()]),
    function (Router $router) {
        // All routes here require authentication AND admin
    }
);
```

## Current Application Setup

### Development Environment

1. **Start the development server:**
   ```bash
   php -S localhost:5000 -c php.ini
   ```

2. **Configure environment:**
   Create `.env` file with database credentials:
   ```env
   APP_ENV=development
   APP_NAME="Golf El Faro"

   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=TPDb
   DB_USERNAME=TP
   DB_PASSWORD=your_password

   LOG_LEVEL=DEBUG
   ```

3. **Initialize database:**
   ```bash
   mysql < init.sql
   ```

### Testing the Application

**Core functionality to verify:**
- ✅ User authentication (`/login`)
- ✅ Event listing (`/events`)
- ✅ Event details with parameters (`/events/{id}`)
- ✅ Event registration (requires auth)
- ✅ Admin functions (requires admin role)
- ✅ User management (admin only)
- ✅ Health check endpoint (`/health`)

**Test different routes:**
```bash
# Health check
curl http://localhost:5000/health

# Login page
curl http://localhost:5000/login

# Protected routes (should redirect without auth)
curl -I http://localhost:5000/events
```

### Deployment Considerations

1. **Web server configuration:**
   - Enable security headers
   - Configure SSL/HTTPS
   - Set up log rotation for `logs/app.log`
   - Ensure `.env` is not publicly accessible

2. **Production environment:**
   ```env
   APP_ENV=production
   LOG_LEVEL=INFO
   SESSION_LIFETIME=3600
   ```

3. **Database optimization:**
   ```sql
   -- Recommended indexes for production
   CREATE INDEX idx_events_date ON events(date);
   CREATE INDEX idx_event_users_event_id ON event_users(eventId);
   CREATE INDEX idx_event_users_user_id ON event_users(userId);
   ```

## Key Changes from Legacy System

### Removed Components
- ❌ `RouterBuilder` class and routing system
- ❌ `src/pages/*/routes.php` route files
- ❌ `private/credential.*.php` configuration files
- ❌ `src/Core/Index.php` manual includes
- ❌ Direct `$_GET`, `$_POST`, `$_SESSION` access in controllers
- ❌ `getRouterBuilder()` and `pass()` helper functions
- ❌ Old `Route`, `RouteItem`, `RouterView`, `RouterAction` classes

### New Components
- ✅ Modern `Router` with middleware pipeline
- ✅ `Request` and `Response` objects
- ✅ `MiddlewareInterface` and middleware classes
- ✅ `RouteGroup` for nested routing
- ✅ `.env` configuration system
- ✅ `Config` class with nested key support
- ✅ Separate validation classes (PSR-4 compliant)
- ✅ Controller classes with type hints

### API Changes

**Route Registration:**
```php
// Old: src/pages/events/routes.php function
function getEventRoutes($method) { ... }

// New: index.php declarations
$router->get('/events', [EventController::class, 'index']);
```

**Request Handling:**
```php
// Old: Direct superglobals
$id = $_GET['id'];

// New: Type-safe Request object
$id = $request->getInt('id');
```

**Configuration Access:**
```php
// Old: Include credential file
include('private/credential.dev.php');
$conn = getConnection();

// New: Config class
$config = Config::getInstance();
$host = $config->get('database.host');
```

## Benefits of Current Architecture

### Security Improvements
- ✅ Input sanitization via `Request` object
- ✅ Validation framework with `ValidationRule`
- ✅ Secure password hashing (Argon2ID)
- ✅ Middleware-based authentication checks
- ✅ Type-safe request parameter extraction

### Code Quality Improvements
- ✅ PSR-4 autoloading (one class per file)
- ✅ Strong typing with `declare(strict_types=1)`
- ✅ Modern PHP 8.1+ features
- ✅ Consistent code organization
- ✅ Clear separation of concerns

### Maintainability Improvements
- ✅ Routes defined in one place (`index.php`)
- ✅ Controllers follow consistent pattern
- ✅ Middleware reusable across routes
- ✅ Environment-based configuration
- ✅ Structured error handling and logging

### Developer Experience
- ✅ Type hints and return types everywhere
- ✅ IDE autocompletion support
- ✅ Cleaner, more readable code
- ✅ Easy to add new routes and middleware
- ✅ Health check endpoint for monitoring

### Architecture Benefits
- ✅ Request/Response pattern (testable)
- ✅ Middleware pipeline (composable)
- ✅ Repository pattern (database abstraction)
- ✅ Component system (reusable UI)
- ✅ Configuration management (environment-aware)

## Documentation

### Available Documentation
- **CLAUDE.md** - Architecture overview and development guide
- **docs/routing.md** - Routing system documentation
- **MIGRATION.md** (this file) - Migration history and patterns

### Key Concepts

**Routing:**
```php
// Single route
$router->get('/path', [Controller::class, 'method'], [middleware]);

// Route group
$router->group(new RouteGroup('/prefix', [middleware]), function($r) {
    $r->get('/sub', [Controller::class, 'method']);
});
```

**Controllers:**
```php
public function method(Request $request, array $params): Response
{
    // Access input
    $value = $request->getString('key');

    // Validate
    $validation = $request->validate([...]);

    // Return response
    return Response::ok($content);
    return Response::redirect('/path');
    return Response::json(['data' => $value]);
}
```

**Middleware:**
```php
public function handle(Request $request, callable $next): Response
{
    // Before logic
    if (!authorized()) {
        return Response::redirect('/login');
    }

    // Continue pipeline
    return $next($request);
}
```

## Troubleshooting

### Common Issues

**Database connection fails:**
- Check `.env` file exists with correct credentials
- Verify MySQL is running
- Check user permissions in database

**Routes not found (404):**
- Check route is registered in `index.php`
- Verify HTTP method matches (GET vs POST)
- Check middleware isn't blocking access

**Autoloading errors:**
- Ensure class names match file names
- Check namespace declarations
- Verify one class per file

**Validation errors:**
- Check ValidationRule syntax
- Ensure field names match form inputs
- Review validation error messages

### Debug Mode

Enable detailed logging in `.env`:
```env
APP_ENV=development
LOG_LEVEL=DEBUG
```

Then check `logs/app.log` for detailed information.

### Health Check

Monitor application status:
```bash
curl http://localhost:5000/health
```

Returns JSON with database status, memory usage, and configuration.