#!/bin/bash

# Main test runner script
# Initializes test database, runs tests, and cleans up

set -e

echo "======================================"
echo "  Tournament Planner Integration Tests"
echo "======================================"
echo ""

# Check if composer dependencies are installed
if [ ! -d "vendor" ]; then
    echo "Installing composer dependencies..."
    composer install
    echo ""
fi

# Initialize test database
echo "Step 1: Initializing test database..."
./tests/init-test-db.sh
echo ""

# Run tests
echo "Step 2: Running integration tests..."
echo ""
vendor/bin/phpunit --testsuite integration --colors=always

TEST_EXIT_CODE=$?
echo ""

# Cleanup test database
echo "Step 3: Cleaning up test database..."
./tests/cleanup-test-db.sh
echo ""

if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo "======================================"
    echo "  ✓ All tests passed!"
    echo "======================================"
    exit 0
else
    echo "======================================"
    echo "  ✗ Some tests failed"
    echo "======================================"
    exit $TEST_EXIT_CODE
fi
