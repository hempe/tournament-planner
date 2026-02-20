# Golf El Faro - Tournament Planner

A modern PHP web application for managing golf event registrations.

## What is this?

Tournament Planner is a web application that allows:
- **Users** to view and register for golf events
- **Admins** to create events and manage registrations
- **Calendar view** of all upcoming tournaments

Built with modern PHP 8.1+, clean MVC architecture, and zero external dependencies.

## Quick Start

```bash
# 1. Configure database
cp .env.example .env
# Edit .env with your database credentials

# 2. Initialize database
mysql -u root -p < database/init.sql

# 3. Start server
php -S localhost:5000 -c php.ini

# 4. Open browser
# http://localhost:5000
# Login: admin / Admin123!
```

That's it! 🚀

## Documentation

### Getting Started
- **[docs/INSTALLATION.md](docs/INSTALLATION.md)** - Complete installation guide
- **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** - Production deployment instructions

### For Developers
- **[docs/TESTING.md](docs/TESTING.md)** - Testing guide and code coverage
- **[docs/ROUTING.md](docs/ROUTING.md)** - Routing and middleware system
- **[docs/COMPONENTS.md](docs/COMPONENTS.md)** - UI component system
- **[docs/IFRAME_MODE.md](docs/IFRAME_MODE.md)** - Iframe embedding support

### For AI Assistants
- **[CLAUDE.md](CLAUDE.md)** - Development guide for Claude Code

## Requirements

- PHP 8.1+
- MySQL 8.0+ or MariaDB 10.6+
- Extensions: `pdo_mysql`, `mysqli`

## Tech Stack

- **Backend**: Pure PHP 8.1+ (no frameworks, zero dependencies)
- **Database**: MySQL with repository pattern
- **Architecture**: Modern MVC with Request/Response pattern
- **Routing**: Custom router with middleware support
- **Authentication**: Session-based with Argon2ID password hashing

## Features

✅ Event management (create, edit, delete)
✅ User authentication and admin roles
✅ Event registration with waitlist support
✅ Calendar view
✅ Multi-language support (German/English/Spanish)
✅ Responsive design
✅ User-friendly error pages (404, 403, 500)
✅ Health monitoring endpoint
✅ Comprehensive test suite with 83.6% line coverage (325 tests)

## Testing

```bash
# Run all tests
composer test

# Run with code coverage
composer test:coverage

# Generate HTML coverage report
composer test:coverage-html
```

See [TESTING.md](docs/TESTING.md) for detailed testing documentation.

## Quick Links

- Need help? → See [docs/INSTALLATION.md](docs/INSTALLATION.md)
- Deploying to production? → See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)
- Running tests? → See [docs/TESTING.md](docs/TESTING.md)

## License
This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

