# Tournament Planner (GOLF EL FARO, Amigos de Tauro)

A modern PHP web application for managing golf event registrations.

## What is this?

Tournament Planner is a web application for a golf club:
- **Members** view the calendar and register for tournaments and social events (dinners)
- **Guests** register without an account
- **Admins** create events (also in bulk), manage registrations, guests and users

It is an Angular single-page app (installable PWA) on top of a PHP JSON API.

### Flavours

One code base, built separately for two clubs (`frontend/flavors/<name>/`: logo, icons, theme, texts, switches):

- **`elfaro`** — GOLF EL FARO: everything on (dinners, guests, prices, deadlines, men/women lists).
- **`amigos`** — Amigos de Tauro: tournaments only, waitlist by priority, phone/handicap/priority per user,
  dark mode with a background photo on phones.

```bash
./run.sh amigos                     # develop as Amigos (default: elfaro)
./build.sh --flavor amigos          # build an upload for the Amigos host
```

## Quick Start

```bash
# 1. Configure database
# create .env with DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD (see CLAUDE.md)

# 2. Initialize database
mysql -u root -p < database/init.sql

# 3. Install the frontend dependencies once
(cd frontend && npm ci)

# 4. Start PHP (:5000) and the Angular dev server (:4200)
./run.sh

# 5. Open http://localhost:4200 - Login: admin / Admin123!
```

## Documentation

- **[docs/INSTALLATION.md](docs/INSTALLATION.md)** - Complete installation guide
- **[docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)** - Build, manual upload, rollback and cut-over checklist
- **[docs/WHATS-NEW.md](docs/WHATS-NEW.md)** - What changed for members, guests and admins in the Angular release, and ideas for later
- **[docs/FRONTEND.md](docs/FRONTEND.md)** - Angular app, screens, PWA
- **[docs/API.md](docs/API.md)** - JSON API
- **[docs/ROUTING.md](docs/ROUTING.md)** - Routing and middleware
- **[docs/TESTING.md](docs/TESTING.md)** - PHPUnit, Vitest and Playwright
- **[CLAUDE.md](CLAUDE.md)** - Development guide for Claude Code

## Requirements

- PHP 8.3+ (production runs 8.3)
- MySQL 8.0+ or MariaDB 10.6+
- Extensions: `pdo_mysql`, `mysqli`
- Apache with `mod_rewrite` (and optionally `mod_headers`) for production
- Node.js 26+ for development and building only - not needed on the server
- Docker for the end-to-end tests and the PHP 8.3 lint in `build.sh`

## Tech Stack

- **Frontend**: Angular 22 (signals, signal forms), Spartan UI, service worker (PWA)
- **Backend**: PHP 8.3 JSON API with attribute-based routing, middleware and generated TypeScript client
- **Database**: MySQL/MariaDB with repository pattern
- **Authentication**: Session-based with Argon2ID password hashing, CSRF protection

## Features

- Tournament management (create, edit, delete, lock/unlock, CSV export, bulk create)
- Social events linked to tournaments (menu + table selection, no waitlist)
- User authentication and admin roles; login with the username or with "first last" / "last first" name
- Event registration with waitlist support
- Free-text event prices and descriptions
- Guest registration (no account required)
- Calendar view showing both event types
- German/English/Spanish
- Responsive, installable PWA with an in-app update prompt
- Health monitoring endpoint (`/health`)
- Test suites: 456 PHPUnit, 304 Vitest, 69 Playwright E2E tests (59 El Faro + 10 against an Amigos build)

## Testing

```bash
composer test                  # PHPUnit
composer test:coverage-check   # with coverage, >= 85 %
cd frontend && npm test        # Vitest
cd frontend && npm run e2e     # Playwright against Apache + MariaDB in Docker
```

See [docs/TESTING.md](docs/TESTING.md) for details.

## Deploying to Production

Run `./build.sh --flavor <elfaro|amigos>` on a clean git tree and upload the two folders it creates
(`dist/<flavour>/1-upload-first/`, then `dist/<flavour>/2-upload-last/`) to that club's web root. Never upload the source tree. The full procedure, rollback and the
one-time cut-over checklist are in [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

## Quick Links

- Need help? → See [docs/INSTALLATION.md](docs/INSTALLATION.md)
- Deploying to production? → See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)
- Running tests? → See [docs/TESTING.md](docs/TESTING.md)

## License
This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.


