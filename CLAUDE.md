# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

**Start development server:**
```bash
php -S localhost:5000 -c php.ini
```

**Quick run script:**
```bash
./run.sh
```

**Database initialization:**
```bash
mysql < init.sql
```

**Environment setup:**
Copy `.env` file and configure database credentials:
```bash
# .env file should contain:
DB_HOST=localhost
DB_PORT=3306
DB_NAME=TPDb
DB_USERNAME=TP
DB_PASSWORD=your_password
```

## Architecture Overview

This is a PHP web application for golf event management with a modern MVC architecture:

### Core Architecture

- **Entry Point**: `index.php` - Defines routes using Request/Response pattern and starts the application
- **Routing System**: Modern router in `src/Core/Router.php` with middleware support and parameter extraction
- **Request/Response**: PSR-7-like Request and Response objects for HTTP handling
- **Component System**: Base component class in `src/Core/Component.php` for reusable UI elements
- **Database Layer**: Repository pattern with `src/Models/DB.php` as main database facade
- **Configuration**: Environment-based config in `.env` file, loaded by `src/Core/Config.php`

### Key Patterns

**Routing**: Routes are defined in `index.php` using modern API:
- `$router->get('/path', [Controller::class, 'method'])` - Register routes
- `$router->group(new RouteGroup('/prefix', [middleware]), callback)` - Group routes with middleware
- Routes support URL parameters like `/{id}` which are passed to controller methods
- Middleware can be applied at route or group level (e.g., AuthMiddleware, AdminMiddleware)

**Authentication**: Session-based authentication with `User` class providing static methods:
- `User::loggedIn()` - Check if user is authenticated
- `User::admin()` - Check admin privileges
- `User::canEdit($userId)` - Check if user can edit specific user data

**Controllers**: All controllers follow pattern `(Request $request, array $params): Response`
- Controllers return Response objects (ok, redirect, json, notFound, etc.)
- Validation handled via `$request->validate()` with ValidationRule objects

**Components**: All UI Components extend `Component` base class and implement `template()` method. Components support nested rendering and automatic output buffering. See [docs/COMPONENTS.md](docs/COMPONENTS.md) for comprehensive component documentation.

**Translation System**: All user-facing text uses `__('key')` function for internationalization. Translations defined in `resources/lang/de_CH.php`. Never hardcode German text in views.

**Database**: Repository pattern with static instances accessible via `DB::$events` and `DB::$users`. Database connection configured in `.env` file.

### Directory Structure

- `src/Core/` - Core framework classes (Router, Request, Response, Config, Validator, etc.)
- `src/Controllers/` - HTTP controllers handling requests
- `src/Models/` - Database models and repositories
- `src/Middleware/` - Route middleware (AuthMiddleware, AdminMiddleware)
- `src/Views/` - View templates organized by feature (Events, Users, Home)
- `src/Components/` - Reusable UI Components
- `src/Layout/` - Header and footer templates
- `styles/` - CSS files
- `src/scripts/` - JavaScript files

### Database Setup

Requires MySQL with extensions `pdo_mysql` and `mysqli` enabled. Database schema defined in `init.sql` with tables for users, events, and event registrations. Configure database credentials in `.env` file.

## Documentation

- **[Component System](docs/COMPONENTS.md)** - Comprehensive guide to UI components
  - [Table Component](docs/components/Table.md) - Data tables and form tables
  - [Page Component](docs/components/Page.md) - Page layout and navigation
  - [Form Component](docs/components/Form.md) - Forms with CSRF protection
  - [Card Component](docs/components/Card.md) - Content cards
- **[Translation System](resources/lang/de_CH.php)** - i18n strings

## Important Reminders

### Component Usage

**✅ DO:**
- Use `yield` in generator functions passed to components
- Use distinct item values in Table (e.g., `[0, 1, 2]` not `[null, null, null]`)
- Use `__()` for all user-facing text
- Check component documentation before using

**❌ DON'T:**
- Use `echo` in generator functions (won't render)
- Assume Table projection receives index parameter (only receives item)
- Add parameters that don't exist (e.g., `footer` on Table)
- Hardcode German text in views

### Common Patterns

**Multiple items in Page:**
```php
<?= new Page(function () {
    yield new Card('Title 1', 'Content 1');
    yield new Card('Title 2', 'Content 2');
}) ?>
```

**Table with data:**
```php
new Table(
    columns: ['Name', 'Email'],
    items: $users,
    projection: fn($user) => [$user->name, $user->email]
)
```

**Form table (multiple rows):**
```php
new Table(
    columns: ['Label', 'Input'],
    items: [0, 1, 2],  // Row indices
    projection: fn($i) => match($i) {
        0 => ['Field 1', '<input name="field1">'],
        1 => ['Field 2', '<input name="field2">'],
        2 => ['', new IconButton(...)]
    }
)
```