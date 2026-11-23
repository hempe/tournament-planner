# Testing Documentation

This document describes the integration testing setup for the Tournament Planner application.

## Overview

The test suite provides comprehensive integration tests that verify the complete application workflow including:

- User creation and management
- Tournament event creation, editing, locking, registration, waitlist, export
- Social event creation, menu/table setup, registration, guest registration
- Bulk event creation (recurring weekly events)
- Guest registration for both event types
- Multi-language support validation

## Requirements

- PHP 8.3 or higher with `mysqli` extension enabled
- PCOV extension for code coverage (install via: `yay -S php-pcov` on Arch/Manjaro)
- MySQL/MariaDB database server
- Composer (for installing PHPUnit)
- Database credentials matching your `.env` configuration

## Installation

1. **Install Composer** (if not already installed):
   ```bash
   # On Linux
   curl -sS https://getcomposer.org/installer | php
   sudo mv composer.phar /usr/local/bin/composer

   # Or use your package manager
   sudo pacman -S composer  # Arch/Manjaro
   sudo apt install composer  # Ubuntu/Debian
   ```

2. **Enable mysqli PHP Extension**:
   ```bash
   # Uncomment the mysqli extension in php.ini
   sudo sed -i 's/^;extension=mysqli/extension=mysqli/' /etc/php/php.ini

   # Verify mysqli is loaded
   php -m | grep mysqli
   ```

3. **Grant Database Permissions** (one-time setup):

   The test database user needs permissions to create/drop test databases and manipulate data:

   ```bash
   sudo ./tests/grant-test-permissions.sh
   ```

   This grants the following permissions to the `TP` user:
   - `CREATE, DROP` - Create and drop test databases
   - `REFERENCES` - Create foreign key constraints
   - `ALTER` - Modify table structure
   - `SELECT, INSERT, UPDATE, DELETE` - Manipulate test data

   **Note**: These permissions only apply to test databases and are required for automated testing.

4. **Install Test Dependencies**:
   ```bash
   composer install
   ```

## Test Database

The tests use a separate database (`TP_test`) to avoid affecting your production data.

### Database Configuration

Test database settings are configured in:
- `phpunit.xml` - PHPUnit environment variables
- `config/testing.php` - Testing-specific configuration

Default test database credentials:
- Host: `localhost`
- Database: `TP_test`
- Username: `TP`
- Password: `g0lf3lf4r0`

## Running Tests

### Quick Start

Run all tests using Composer:

```bash
# Run all tests
composer test

# Run only integration tests
composer test:integration

# Run with code coverage report
composer test:coverage

# Generate HTML coverage report
composer test:coverage-html
```

### Code Coverage

The project uses PCOV for fast code coverage analysis.

To view detailed coverage:

```bash
# Text report in terminal
composer test:coverage

# HTML report in browser
composer test:coverage-html
open coverage/index.html
```

### Manual Test Execution

If you prefer to run tests manually:

```bash
# Run tests
vendor/bin/phpunit

# Run with coverage (requires PCOV enabled)
php -d pcov.enabled=1 vendor/bin/phpunit --coverage-text

# Run specific test suite
vendor/bin/phpunit --testsuite integration

# Run a specific test file
vendor/bin/phpunit tests/Integration/Api/AuthApiTest.php

# Run a specific test method
vendor/bin/phpunit --filter testLoginWithValidCredentials   # any test method name
```

## Test Structure

Three layers; run all of them for cross-cutting changes.

| Layer | Command | Count |
|---|---|---|
| PHPUnit (API, core, models, security) | `composer test` | 456 tests |
| Vitest (Angular unit/component tests) | `cd frontend && npm test` | 304 tests in 45 files |
| Playwright E2E (Apache + MariaDB in Docker) | `cd frontend && npm run e2e` | 69 tests: 59 El Faro (desktop + iPhone 13) + 10 against an Amigos build (desktop + dark-mode iPhone 13) |

**Flavours:** PHPUnit and Vitest always run as El Faro; each switch is tested in both states within the same run
(`Flavor::override(['guests' => false])` in PHPUnit — reset per test by `IntegrationTestCase` — and
`provideTestFlavor()` + `setTestFlavor(amigosFlavor)` in Vitest). `npm run e2e` builds and tests El Faro, then builds
an Amigos release and runs `frontend/e2e/amigos.spec.ts` against it with `e2e/seed-amigos.sql`.
The old Amigos schema migration is covered by `AmigosMigrationTest` (its own database `TP_test_migration`, made-up data).

Never run PHPUnit in parallel (or alongside `npm run e2e`): all suites share the test database `TP_test`.

```
tests/Integration/
├── IntegrationTestCase.php      # Base class: DB reset, login helpers, request() dispatcher
├── Api/                         # One file per API area (auth, calendar, events, social events,
│                                #   admin endpoints, guests, CSRF, middleware, generated client, health, ...)
├── Core/                        # Config, Request, Response, Router, RouteCache, Translator, Validator, ...
├── Models/                      # Repository tests
├── Security/SecurityTest.php    # Hashing, escaping, rate limit
├── Services/                    # Recurring events
├── LocalizationTest.php
└── TranslationValidationTest.php
```

`GeneratedApiTest` fails when `frontend/src/app/core/api/generated.ts` is stale (`composer api:generate`).
`composer test:coverage-check` fails below 85 % line coverage.

## Test Database Lifecycle

### Initialization
The test database is automatically created and seeded with:
- Clean schema from `database/init.sql`
- Default admin user (username: `admin`, password: `Admin123!`)

### Per-Test Cleanup
Before each test method runs:
- All table data is truncated (keeping schema)
- Admin user is re-seeded
- Session is cleared

### Final Cleanup
After all tests complete:
- Test database is completely dropped
- No test data remains on the system

## Writing New Tests

### Basic Test Structure

```php
<?php

namespace TP\Tests\Integration;

class MyNewTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase(); // Start with clean data
    }

    public function testSomething(): void
    {
        // 1. Setup
        $this->loginAsAdmin();

        // 2. Execute
        $userId = DB::$users->create('testuser', 'password');

        // 3. Assert
        $this->assertGreaterThan(0, $userId);
    }
}
```

### Available Helper Methods

From `IntegrationTestCase`:

```php
// Authentication
$this->loginAsAdmin();
$this->loginAs($username, $password);

// Database
$this->cleanDatabase();  // Truncate all tables, re-seed admin

// HTTP Requests (if needed in future)
$response = $this->request('POST', '/api/events', [...]);  // see tests/Integration/Api for examples
```

## Common Issues

### "Class 'mysqli' not found"
- Enable the mysqli extension in `/etc/php/php.ini`:
  ```bash
  sudo sed -i 's/^;extension=mysqli/extension=mysqli/' /etc/php/php.ini
  ```
- Verify it's loaded: `php -m | grep mysqli`

### "INSERT/CREATE/ALTER command denied" or "Access denied to database"
- Run the permission grant script:
  ```bash
  sudo ./tests/grant-test-permissions.sh
  ```
- Or manually grant permissions:
  ```bash
  sudo mysql -e "GRANT CREATE, DROP, REFERENCES, ALTER, SELECT, INSERT, UPDATE, DELETE ON *.* TO 'TP'@'localhost'; FLUSH PRIVILEGES;"
  ```

### "Database connection failed"
- Verify MySQL/MariaDB is running: `sudo systemctl status mysql` or `sudo systemctl status mariadb`
- Check database credentials in `.env` and `phpunit.xml`
- Ensure the `TP` database user exists with correct password

### "composer: command not found"
- Install Composer: see Installation section above
- Or install PHPUnit globally: `sudo pacman -S php-phpunit`

### "Permission denied" on scripts
- Make scripts executable: `chmod +x run-tests.sh tests/*.sh`

### Tests fail with "Session expired"
- This is normal - tests clean session between runs
- If tests fail unexpectedly, check database state with manual queries

## CI/CD Integration

To integrate tests into CI/CD pipeline:

```yaml
# Example GitHub Actions workflow
- name: Run Integration Tests
  run: |
    composer install
    ./run-tests.sh
```

## Test Output

Successful test run example:

```
======================================
  Tournament Planner Integration Tests
======================================

Step 1: Initializing test database...
✓ Test database initialized successfully!

Step 2: Running integration tests...

=== Running Complete Event Management Workflow ===

1. Creating new user...
   ✓ User created with ID: 2

2. Creating single event...
   ✓ Event created: Test Golf Event on 2026-03-15

3. Bulk creating weekly events...
   ✓ Created 13 weekly events
   ✓ All bulk events are locked

4. Testing lock/unlock functionality...
   ✓ Event locked successfully
   ✓ Event unlocked successfully

5. Testing user registration...
   ✓ User registered successfully

6. Testing locked event registration prevention...
   ✓ Event is locked, registration should be prevented at application level

7. Testing user unregistration...
   ✓ User unregistered successfully

8. Testing waitlist functionality...
   ✓ Waitlist working: 2 confirmed, 1 on waitlist

9. Testing automatic waitlist promotion...
   ✓ User automatically promoted from waitlist

=== All Integration Tests Passed! ===

OK, but there were issues!
Tests: 571, Assertions: 1359, Warnings: 3, Deprecations: 9.

Step 3: Cleaning up test database...
✓ Test database removed successfully!

======================================
  ✓ All tests passed!
======================================
```

## Performance

Typical test execution time:
- Database initialization: ~1 second
- Test suite execution: ~40-45 seconds (631 tests)
- **Total: ~45 seconds**

## Best Practices

1. Run the tests before committing.
2. Keep tests independent; call `cleanDatabase()` in `setUp()`.
3. Cover success and failure paths, and anonymous / member / admin access for every route.
4. `IntegrationTestCase::request()` resets `$_GET` and `$_POST` on every call; use `$_SESSION = []` to log out within a test.

## Frontend tests

The Angular app in `frontend/` has its own tests (see [FRONTEND.md](FRONTEND.md)). Run inside `frontend/`:

| Command | What it does |
|---|---|
| `npm test` | Vitest unit/component tests |
| `npm run lint` | ESLint (incl. the RxJS/HttpClient ban) |
| `npm run check:i18n` | fail on translation keys that don't exist |

## End-to-end tests (Playwright)

`cd frontend && npm run e2e` builds two releases (`BUILD_VERSION=e2e-1` / `e2e-2`), starts the production-like
stack in Docker (`php:8.3-apache` with the real `.htaccess` + `mariadb:11`, see `e2e/stack.sh`), runs the Playwright
specs in `frontend/e2e/` (desktop Chromium, plus an iPhone 13 emulation project for auth/member/guest) and tears
everything down. Seed data lives in `e2e/seed.sql` (logins `admin` / `member` / `other`, events in 2099); every
mutating spec resets the database first. `update.spec.ts` (tagged `@slow`) deploys the second release into the running
site and checks the service-worker update prompt; skip it with `E2E_SKIP_SLOW=1 npm run e2e`. Extra arguments are
passed to Playwright, e.g. `npm run e2e -- admin.spec.ts`. Requires Docker and `npx playwright install chromium` once.

## Continuous integration (GitHub Actions)

`.github/workflows/ci.yml`:

| Job | Runs | What it does |
|---|---|---|
| `php` | every push and pull request | PHP 8.3 + MariaDB 11 service; `composer api:check` (generated TypeScript types are current), then `composer test:coverage-check` (PHPUnit with PCOV, coverage >= 85 %). PHPUnit's known deprecations do not fail the build, test failures do. |
| `frontend` | every push and pull request | Node 26; `npm run lint`, `npm run check:i18n`, `npm test`, `npx ng build --configuration production` |
| `e2e` | manually only | the Playwright suite above (Docker on the runner) |

The tests read their database settings from `$_ENV`; `phpunit.xml` does not override variables that already exist in
the environment (and `config/testing.php` no longer hard-codes the host), so the workflow sets `DB_*` for the MariaDB service (and `variables_order=EGPCS` so PHP fills `$_ENV`).

To start the E2E job: GitHub > Actions > CI > Run workflow > tick "e2e" (or
`gh workflow run ci.yml --ref <branch> -f e2e=true`).
