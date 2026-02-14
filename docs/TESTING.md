# Testing Documentation

This document describes the integration testing setup for the Tournament Planner application.

## Overview

The test suite provides comprehensive integration tests that verify the complete application workflow including:

- User creation and management
- Single event creation
- Bulk event creation (recurring weekly events)
- Event locking and unlocking
- User registration and unregistration
- Waitlist functionality and automatic promotion
- Event updates and deletion

## Requirements

- PHP 8.1 or higher with `mysqli` extension enabled
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

Use the main test runner script that handles everything:

```bash
./run-tests.sh
```

This script will:
1. Initialize the test database with clean schema
2. Run all integration tests
3. Clean up the test database afterward

### Manual Test Execution

If you prefer to run tests manually:

```bash
# 1. Initialize test database
./tests/init-test-db.sh

# 2. Run tests
vendor/bin/phpunit --testsuite integration

# 3. Clean up
./tests/cleanup-test-db.sh
```

### Running Specific Tests

```bash
# Run only integration tests
composer test:integration

# Run a specific test file
vendor/bin/phpunit tests/Integration/EventManagementTest.php

# Run a specific test method
vendor/bin/phpunit --filter testCompleteEventManagementWorkflow
```

## Test Structure

### Directory Layout

```
tests/
├── bootstrap.php                    # Test bootstrapper
├── init-test-db.sh                 # Database initialization script
├── cleanup-test-db.sh              # Database cleanup script
└── Integration/
    ├── IntegrationTestCase.php     # Base test class
    └── EventManagementTest.php     # Main integration tests
```

### Test Files

#### `IntegrationTestCase.php`

Base class for all integration tests providing:
- Automatic test database setup and teardown
- Helper methods for simulating HTTP requests
- Authentication helpers (`loginAsAdmin()`, `loginAs()`)
- Database cleanup utilities

#### `EventManagementTest.php`

Comprehensive integration tests covering:

1. **Complete Workflow Test** (`testCompleteEventManagementWorkflow`)
   - Creates a regular user
   - Creates a single event
   - Bulk creates weekly recurring events
   - Tests lock/unlock functionality
   - Tests user registration and unregistration
   - Tests waitlist functionality and automatic promotion

2. **User Management Test** (`testUserCreationAndDeletion`)
   - Creates users via API
   - Verifies user in database
   - Deletes users

3. **Event Management Test** (`testEventUpdateAndDelete`)
   - Updates event name and capacity
   - Deletes events

## What Gets Tested

### User Management
- ✅ Create new user with username and password
- ✅ Verify user exists in database
- ✅ Delete user
- ✅ User creation with duplicate username (validation)

### Single Event Creation
- ✅ Create event with name, date, and capacity
- ✅ Event is created unlocked by default
- ✅ Event appears in event list
- ✅ Event data is correctly stored

### Bulk Event Creation
- ✅ Create multiple recurring events (e.g., every Wednesday)
- ✅ Correct date calculation for day-of-week
- ✅ All bulk events are created as locked
- ✅ Event count matches expected number

### Lock/Unlock Functionality
- ✅ Lock an event
- ✅ Verify event is locked in database
- ✅ Unlock an event
- ✅ Verify event is unlocked
- ✅ Locked status prevents registration (application-level check)

### User Registration
- ✅ Register user for an event
- ✅ Registration includes optional comment
- ✅ Registration state is "confirmed" (state = 1)
- ✅ Multiple users can register up to capacity

### Waitlist Functionality
- ✅ Users beyond capacity go to waitlist (state = 2)
- ✅ Waitlisted users don't count toward capacity
- ✅ When a confirmed user unregisters, waitlisted user is automatically promoted
- ✅ Promotion maintains timestamp order (first in waitlist promoted first)

### Unregistration
- ✅ User can unregister from event
- ✅ Registration is completely removed
- ✅ Waitlist users are promoted when spots open

### Event Updates
- ✅ Update event name
- ✅ Update event capacity
- ✅ Capacity changes trigger waitlist recalculation

### Event Deletion
- ✅ Delete event
- ✅ Event registrations are cascade deleted (foreign key)

## Test Database Lifecycle

### Initialization
The test database is automatically created and seeded with:
- Clean schema (tables: users, events, event_users)
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
$response = $this->request('POST', '/events/new', [
    'name' => 'Event',
    'date' => '2026-01-01',
    'capacity' => 20
]);
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

OK (3 tests, 42 assertions)

Step 3: Cleaning up test database...
✓ Test database removed successfully!

======================================
  ✓ All tests passed!
======================================
```

## Performance

Typical test execution time:
- Database initialization: ~1 second
- Test suite execution: ~2-3 seconds
- Database cleanup: ~0.5 seconds
- **Total: ~4 seconds**

## Best Practices

1. **Always run tests before committing** to catch regressions early
2. **Keep tests independent** - each test should work in isolation
3. **Use descriptive test names** - clearly state what is being tested
4. **Add echo statements** for verbose output showing test progress
5. **Clean database before each test** to ensure consistent state
6. **Test both success and failure cases** where applicable

## Future Enhancements

Potential additions to the test suite:

- [ ] HTTP endpoint tests (full request/response cycle)
- [ ] Authentication/authorization tests
- [ ] Form validation tests
- [ ] CSRF token tests
- [ ] API endpoint tests (if API added)
- [ ] Performance/load tests
- [ ] Browser automation tests (Selenium/Playwright)

## Support

For issues or questions about testing:
1. Check this documentation
2. Review test output for error messages
3. Check database logs: `sudo journalctl -u mysql`
4. Open an issue on GitHub with test output
