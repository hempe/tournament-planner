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
    │   ├── GuestControllerTest.php      # Guest registration and management
    │   ├── HomeControllerTest.php       # Home page and guest redirect
    │   ├── LanguageControllerTest.php   # Language switch endpoint
    │   └── UserControllerTest.php       # User CRUD, admin toggle, password
    ├── Core/
    │   ├── ConfigTest.php               # Config singleton and env access
    │   ├── DateTimeHelperTest.php       # Date formatting helpers
    │   ├── RequestTest.php              # Request parsing, sanitization
    │   ├── ResponseTest.php             # Response status, redirect, json
    │   ├── RouteCacheTest.php           # Route cache read/write
    │   └── ValidatorTest.php            # Validation rules
    ├── Models/
    │   └── EventRepositoryTest.php      # Repository methods (all, fix, available)
    └── Security/
        └── SecurityTest.php             # CSRF, hashing, escaping, rate limit
```

## Database Setup

Tests use a separate database configured in `phpunit.xml` (`TP_test`).

```bash
./tests/init-test-db.sh           # Create test DB and schema
./tests/grant-test-permissions.sh # Grant DB user permissions
./tests/cleanup-test-db.sh        # Drop test DB
```

## Writing Tests

Extend `IntegrationTestCase` for controller/integration tests:

```php
class MyTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase(); // Start fresh
    }

    public function testSomething(): void
    {
        $this->loginAsAdmin();
        $response = $this->request('GET', '/events');
        $this->assertEquals(200, $response->statusCode);
    }
}
```

Helper methods from `IntegrationTestCase`:
- `loginAsAdmin()` — log in as the seeded admin user
- `loginAs(string $username, string $password)` — log in as any user
- `request(string $method, string $uri, array $data = [])` — make HTTP request, returns `TestResponse`
- `cleanDatabase()` — truncate all tables and re-seed admin user

`TestResponse` properties:
- `$response->statusCode` — HTTP status code
- `$response->body` — response body string

## Route Coverage Convention

Each controller test covers every route with three access levels:
- **Anonymous** — unauthenticated request (expect 303 redirect for auth-protected routes, 403 for admin-only routes on GuestController which lacks class-level AuthMiddleware)
- **Regular user** — authenticated non-admin (expect 403 for admin-only routes)
- **Admin** — authenticated admin (expect success)

For routes with `{id}` parameters, tests also verify:
- **Happy path** — create the resource first, then act on it
- **Not found** — use a non-existent ID (e.g. `99999`), expect 404
