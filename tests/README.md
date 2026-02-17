# Testing

## Running Tests

```bash
# All tests
composer test

# With detailed output
composer test -- --testdox

# Specific file
vendor/bin/phpunit tests/Integration/Controllers/EventControllerTest.php
```

## Code Coverage

PCOV is required (install via your package manager, e.g. `yay -S php-pcov` on Arch).

```bash
# Text summary
composer test:coverage

# HTML report (opens coverage/)
composer test:coverage-html
```

PCOV must be enabled at runtime via `-d pcov.enabled=1` (included in the composer scripts).

## Test Structure

```
tests/
├── bootstrap.php                        # Test bootstrapper
├── init-test-db.sh                      # Initialize test database
├── cleanup-test-db.sh                   # Remove test database
├── grant-test-permissions.sh            # Set up DB permissions
└── Integration/
    ├── IntegrationTestCase.php          # Base class (login, request helpers)
    ├── EventManagementTest.php          # Event workflow (DB-level)
    ├── LocalizationTest.php             # i18n / locale switching
    ├── TranslationValidationTest.php    # Translation key completeness
    ├── Controllers/
    │   ├── AuthControllerTest.php       # Login / logout endpoints
    │   ├── EventControllerTest.php      # Event CRUD, register, lock, bulk
    │   ├── HomeControllerTest.php       # Home page
    │   ├── LanguageControllerTest.php   # Language switch endpoint
    │   └── UserControllerTest.php       # User CRUD, admin toggle, password
    ├── Core/
    │   └── ConfigTest.php               # Config singleton and env access
    └── Security/
        └── SecurityTest.php             # CSRF, hashing, escaping, rate limit
```

## Database Setup

Tests use a separate database configured in `phpunit.xml` (`TP_test`).

```bash
./tests/init-test-db.sh          # Create test DB and schema
./tests/grant-test-permissions.sh # Grant DB user permissions
./tests/cleanup-test-db.sh       # Drop test DB
```

## Writing Tests

Extend `IntegrationTestCase` for controller/integration tests:

```php
class MyTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testSomething(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('GET', '/events');
        $this->assertEquals(200, $response->getStatus()->value);
    }
}
```

Helper methods from `IntegrationTestCase`:
- `loginAsAdmin()` / `loginAsUser()` - set up session
- `request(string $method, string $uri, array $data = [])` - make HTTP request
- `createTestEvent(string $name, ...)` - create a test event
- `createTestUser(string $username, ...)` - create a test user
