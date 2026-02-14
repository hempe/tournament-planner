# Integration Tests

This directory contains integration tests for the Tournament Planner application.

## Quick Start

```bash
# Run all tests (from project root)
./run-tests.sh
```

## What's Tested

- ✅ User creation and management
- ✅ Single event creation
- ✅ Bulk event creation (recurring events)
- ✅ Event locking and unlocking
- ✅ User registration and unregistration
- ✅ Waitlist functionality
- ✅ Event updates and deletion

## Files

- `bootstrap.php` - Test bootstrapper
- `init-test-db.sh` - Initialize test database
- `cleanup-test-db.sh` - Remove test database
- `Integration/IntegrationTestCase.php` - Base test class
- `Integration/EventManagementTest.php` - Main test suite

## Documentation

See [../docs/TESTING.md](../docs/TESTING.md) for complete documentation.
