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

# Assets — combine + minify + fingerprint CSS
echo "Building CSS bundle..."
mkdir -p "$DIST/styles"
CSS_FILES="styles/style.css styles/calendar.css styles/confirm.css styles/error.css styles/success.css"
MINIFIED=$(php bin/minify-css.php $CSS_FILES)
CSS_HASH=$(echo "$MINIFIED" | php -r "echo substr(hash('sha256', stream_get_contents(STDIN)), 0, 16);")
echo "$MINIFIED" > "$DIST/styles/styles.${CSS_HASH}.css"
echo "  → styles.${CSS_HASH}.css"

# Combine + minify + fingerprint JS
echo "Building JS bundle..."
JS_FILES="src/scripts/social-prompt.js src/scripts/confirm.js src/scripts/error.js src/scripts/success.js src/scripts/fieldset.js src/scripts/scroll.js src/scripts/form-state.js"
JS_MINIFIED=$(php bin/minify-js.php $JS_FILES)
JS_HASH=$(echo "$JS_MINIFIED" | php -r "echo substr(hash('sha256', stream_get_contents(STDIN)), 0, 16);")
mkdir -p "$DIST/src/scripts"
echo "$JS_MINIFIED" > "$DIST/src/scripts/scripts.${JS_HASH}.js"
echo "  → scripts.${JS_HASH}.js"
# Remove the individual files that were copied with src/
rm -f $(for f in $JS_FILES; do echo "$DIST/$f"; done)

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

# Pre-build route cache so the first production request pays no scan cost
echo "Pre-building route cache..."
cp -r bin "$DIST/bin"
(cd "$DIST" && php bin/build-routes.php)
rm -rf "$DIST/bin"

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
