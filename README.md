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
mysql -u root -p < init.sql

# 3. Start server
php -S localhost:5000 -c php.ini

# 4. Open browser
# http://localhost:5000
# Login: admin / Admin123!
```

That's it! 🚀

## Documentation

### Getting Started
- **[INSTALLATION.md](INSTALLATION.md)** - Complete installation guide
- **[DEPLOYMENT.md](DEPLOYMENT.md)** - Production deployment instructions

### For Developers
- **[DEVELOPMENT.md](DEVELOPMENT.md)** - How to add routes, views, and features
- **[APPLICATION.md](APPLICATION.md)** - Application overview and architecture
- **[MIGRATION.md](MIGRATION.md)** - Historical changes and migration guide

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
✅ Multi-language support (German/English)
✅ Responsive design
✅ Health monitoring endpoint

## Quick Links

- Need help? → See [INSTALLATION.md](INSTALLATION.md#troubleshooting)
- Deploying to production? → See [DEPLOYMENT.md](DEPLOYMENT.md)
- Want to add features? → See [DEVELOPMENT.md](DEVELOPMENT.md)
- Understanding the code? → See [APPLICATION.md](APPLICATION.md)

## License

[Your License Here]
