#!/bin/bash

# Main test runner script
# Initializes test database, runs tests with coverage, and cleans up

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

# Check for coverage drivers
HAS_PCOV=$(php -m | grep -i pcov || true)
HAS_XDEBUG=$(php -m | grep -i xdebug || true)
COVERAGE_AVAILABLE=false

if [ -n "$HAS_PCOV" ] || [ -n "$HAS_XDEBUG" ]; then
    COVERAGE_AVAILABLE=true
    if [ -n "$HAS_PCOV" ]; then
        echo "✓ Code coverage enabled (PCOV)"
    else
        echo "✓ Code coverage enabled (Xdebug)"
    fi
else
    echo "⚠ Code coverage not available"
    echo "  Install PCOV or Xdebug for coverage reports:"
    echo "  pecl install pcov"
fi
echo ""

# Initialize test database
echo "Step 1: Initializing test database..."
./tests/init-test-db.sh
echo ""

# Run tests with or without coverage
echo "Step 2: Running integration tests..."
echo ""

if [ "$COVERAGE_AVAILABLE" = true ]; then
    # Run with coverage
    vendor/bin/phpunit \
        --testsuite integration \
        --colors=always \
        --testdox \
        --coverage-html coverage-report \
        --coverage-text

    TEST_EXIT_CODE=$?
else
    # Run without coverage
    vendor/bin/phpunit \
        --testsuite integration \
        --colors=always \
        --testdox

    TEST_EXIT_CODE=$?
fi

echo ""

# Cleanup test database
echo "Step 3: Cleaning up test database..."
./tests/cleanup-test-db.sh
echo ""

# Show results
echo "======================================"
if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo "  ✓ All tests passed!"
    echo "======================================"

    if [ "$COVERAGE_AVAILABLE" = true ]; then
        echo ""
        echo "Code Coverage Report:"
        echo "  HTML: coverage-report/index.html"
        echo "  Open with: open coverage-report/index.html"
        echo ""
        echo "To view coverage:"
        echo "  xdg-open coverage-report/index.html  # Linux"
        echo "  open coverage-report/index.html      # macOS"
        echo "  start coverage-report/index.html     # Windows"
    fi
    echo ""
    exit 0
else
    echo "  ✗ Some tests failed"
    echo "======================================"
    exit $TEST_EXIT_CODE
fi
