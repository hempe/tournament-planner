# Testing Guide

## Running Tests

### Quick Start

Run all tests:
```bash
vendor/bin/phpunit
```

Run with detailed output:
```bash
vendor/bin/phpunit --testdox
```

Run specific test suite:
```bash
vendor/bin/phpunit tests/Integration/EventManagementTest.php
```

## Code Coverage

PHPUnit provides built-in code coverage reporting. To use it, you need a PHP coverage extension installed.

### Install Coverage Extension

**Option 1: PCOV (Recommended for CI/CD)**
```bash
pecl install pcov
```

**Option 2: Xdebug (Better for local debugging)**
```bash
pecl install xdebug
```

After installation, enable the extension in your `php.ini`.

### Generate Coverage Reports

**HTML Report (most detailed):**
```bash
vendor/bin/phpunit --coverage-html coverage-report
```
Then open `coverage-report/index.html` in your browser.

**Text Report (terminal output):**
```bash
vendor/bin/phpunit --coverage-text
```

**Summary only:**
```bash
vendor/bin/phpunit --coverage-text --only-summary-for-coverage-text
```

**XML Reports (for CI tools):**
```bash
# Clover format (for tools like Codecov, Coveralls)
vendor/bin/phpunit --coverage-clover coverage.xml

# Cobertura format (for GitLab, Jenkins)
vendor/bin/phpunit --coverage-cobertura coverage-cobertura.xml
```

### Coverage Configuration

Coverage settings are defined in `phpunit.xml`:

```xml
<source>
    <include>
        <directory>src</directory>
    </include>
</source>
```

This configuration tells PHPUnit to measure coverage for all files in the `src/` directory.

## Test Structure

```
tests/
├── bootstrap.php              # Test bootstrapper
├── Integration/               # Integration tests
│   ├── IntegrationTestCase.php   # Base test class
│   ├── EventManagementTest.php   # Event tests
│   ├── LocalizationTest.php      # i18n tests
│   └── TranslationValidationTest.php  # Translation validation
├── init-test-db.sh           # Initialize test database
├── cleanup-test-db.sh        # Remove test database
└── grant-test-permissions.sh # Set up DB permissions
```

## Current Test Coverage

Run the tests to see current coverage:

```bash
# 19 integration tests
vendor/bin/phpunit --testdox

# To see what's covered
vendor/bin/phpunit --coverage-text
```

### What's Tested

✅ **Event Management** (EventManagementTest.php)
- Complete workflow: user creation, event creation, bulk events
- Lock/unlock functionality
- User registration/unregistration
- Waitlist functionality and auto-promotion
- Event updates and deletion

✅ **Localization** (LocalizationTest.php)
- Locale switching (de, en, es)
- Translation loading for all locales
- Session persistence
- Fallback behavior
- Language switch HTTP endpoint
- Theme translations

✅ **Translation Validation** (TranslationValidationTest.php)
- All locale files exist
- Identical keys across all locales
- No empty translations
- Required keys present
- Language name consistency

### What's Not Tested

See the [API Endpoint Inventory](#api-endpoint-inventory) below for gaps.

High priority missing tests:
- User admin toggle (`POST /users/{id}/admin`)
- Password change (`POST /users/{id}/password`)
- Comment updates (`POST /events/{id}/comment`)
- Health check endpoint (`GET /health`)

## Database Setup

Tests use a separate test database defined in `phpunit.xml`:

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="DB_NAME" value="TP_test"/>
</php>
```

**Initialize test database:**
```bash
./tests/init-test-db.sh
```

**Grant permissions:**
```bash
./tests/grant-test-permissions.sh
```

**Cleanup:**
```bash
./tests/cleanup-test-db.sh
```

## Writing New Tests

### Integration Test Template

```php
<?php

declare(strict_types=1);

namespace TP\Tests\Integration;

class MyNewTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    public function testSomething(): void
    {
        echo "\n=== Testing Something ===\n";

        // Arrange
        $this->loginAsAdmin();

        // Act
        $result = // ... perform action

        // Assert
        $this->assertEquals($expected, $result);

        echo "   ✓ Test passed\n";
    }
}
```

### Helper Methods Available

From `IntegrationTestCase`:
- `loginAsAdmin()` - Authenticate as admin user
- `cleanDatabase()` - Clear all test data
- `request($method, $uri, $data)` - Make HTTP request

## Continuous Integration

For CI/CD pipelines, use these commands:

```bash
# Install PCOV for coverage
pecl install pcov

# Run tests with coverage
vendor/bin/phpunit --coverage-clover coverage.xml

# Upload to Codecov (example)
bash <(curl -s https://codecov.io/bash)
```

## API Endpoint Inventory

All application endpoints and their test status:

### Authentication
- ✅ `POST /login` - Login tested
- ⚠️ `POST /logout` - Not explicitly tested

### Events
- ✅ `POST /events/` - Create event (via DB layer)
- ✅ `POST /events/{id}/update` - Update event
- ✅ `POST /events/{id}/delete` - Delete event
- ✅ `POST /events/{id}/lock` - Lock event
- ✅ `POST /events/{id}/unlock` - Unlock event
- ✅ `POST /events/{id}/register` - Register user
- ✅ `POST /events/{id}/unregister` - Unregister user
- ❌ `POST /events/{id}/comment` - Update comment (NOT TESTED)
- ✅ `POST /events/bulk/store` - Bulk create (via DB layer)
- ❌ `POST /events/bulk/preview` - Preview bulk events (NOT TESTED)

### Users
- ✅ `POST /users/` - Create user
- ✅ `POST /users/{id}/delete` - Delete user
- ❌ `POST /users/{id}/admin` - Toggle admin (NOT TESTED)
- ❌ `POST /users/{id}/password` - Change password (NOT TESTED)

### Language
- ✅ `POST /language/switch` - Switch language
- ❌ `GET /language/current` - Get current language (NOT TESTED)

### Monitoring
- ❌ `GET /health` - Health check (NOT TESTED)

## Best Practices

1. **Always clean the database** in `setUp()` for isolation
2. **Use descriptive test names** that explain what's being tested
3. **Add echo statements** to show test progress
4. **Test one thing per test** method
5. **Use the arrange-act-assert** pattern
6. **Mock external dependencies** when appropriate
7. **Test both success and failure** cases

## Troubleshooting

**Tests fail with database errors:**
- Ensure test database exists: `./tests/init-test-db.sh`
- Check credentials in `phpunit.xml`

**Coverage not working:**
- Install PCOV or Xdebug: `pecl install pcov`
- Verify extension is enabled: `php -m | grep -i pcov`

**Tests are slow:**
- Consider splitting into unit tests (no database)
- Use transactions for faster database cleanup
- Run specific test files instead of full suite
