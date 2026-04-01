#!/bin/bash

# Build script — copies everything needed for production into dist/
# Upload the contents of dist/ to your server.

set -e

DIST="dist"

echo "======================================"
echo "  Tournament Planner Build"
echo "======================================"
echo ""

# Clean previous build
if [ -d "$DIST" ]; then
    echo "Cleaning previous dist/..."
    rm -rf "$DIST"
fi
mkdir -p "$DIST"

# PHP source
echo "Copying PHP source..."
cp index.php bootstrap.php .htaccess "$DIST/"
cp -r src "$DIST/src"

# Assets
echo "Copying assets..."
cp -r styles "$DIST/styles"
cp -r resources "$DIST/resources"

# Images & web manifests
echo "Copying images and manifests..."
cp *.png *.ico *.svg site.webmanifest browserconfig.xml "$DIST/" 2>/dev/null || true

# Production config only
echo "Copying config..."
mkdir -p "$DIST/config"
cp config/production.php "$DIST/config/"

# php.ini — strip xdebug settings for production
echo "Creating production php.ini..."
grep -v 'xdebug' php.ini > "$DIST/php.ini"

# Empty writable directories (server needs these)
echo "Creating writable directories..."
mkdir -p "$DIST/storage/cache"
mkdir -p "$DIST/logs"
touch "$DIST/storage/cache/.gitkeep"
touch "$DIST/logs/.gitkeep"

# Install production-only composer dependencies with optimised autoloader
echo "Installing production composer dependencies..."
cp composer.json composer.lock "$DIST/"
composer install \
    --working-dir="$DIST" \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --quiet

# Remove composer files from dist (not needed at runtime)
rm "$DIST/composer.json" "$DIST/composer.lock"

echo ""
echo "======================================"
echo "  Build complete → $DIST/"
echo "======================================"
echo ""
echo "Contents:"
ls "$DIST/"
echo ""
echo "Don't forget to create a .env file on the server:"
echo "  DB_HOST=..."
echo "  DB_PORT=3306"
echo "  DB_NAME=TPDb"
echo "  DB_USERNAME=..."
echo "  DB_PASSWORD=..."
