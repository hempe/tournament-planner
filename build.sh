#!/bin/bash

# Build script — produces dist/1-upload-first/ and dist/2-upload-last/.
#   ./build.sh            clean working tree required
#   ./build.sh --dirty    allow uncommitted changes (version gets a -dirty suffix)

set -euo pipefail

DIRTY=0
for arg in "$@"; do
    case "$arg" in
        --dirty) DIRTY=1 ;;
        *) echo "Unknown argument: $arg" >&2; exit 2 ;;
    esac
done

cd "$(dirname "$0")"
DIST="dist"
STAGE="$DIST/.stage-frontend"
FIRST="$DIST/1-upload-first"
LAST="$DIST/2-upload-last"

# 1. Version
VERSION="$(date +%Y-%m-%d)-$(git rev-parse --short HEAD)"
STATUS="$(git status --porcelain)"
if [ -n "$STATUS" ]; then
    if [ "$DIRTY" -eq 0 ]; then
        echo "Working tree is dirty — commit or stash first (or use --dirty):" >&2
        echo "$STATUS" >&2
        exit 1
    fi
    VERSION="$VERSION-dirty"
fi

echo "======================================"
echo "  Tournament Planner Build $VERSION"
echo "======================================"

# Lint PHP against production's PHP version (8.3)
if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
    echo "Linting PHP with php:8.3-cli..."
    LINT_OUT="$(docker run --rm -v "$PWD":/app -w /app php:8.3-cli sh -c \
        'for f in $(find src bin -name "*.php") index.php bootstrap.php; do php -l "$f"; done' 2>&1 \
        | grep -v '^No syntax errors detected' || true)"
    if [ -n "$LINT_OUT" ]; then
        echo "$LINT_OUT" >&2
        echo "PHP 8.3 lint failed" >&2
        exit 1
    fi
else
    echo "WARNING: Docker not available — skipping PHP 8.3 lint." >&2
fi

# 2. API client must be up to date
echo "Checking generated API client..."
composer api:check

# 3. Frontend
echo "Building frontend..."
(cd frontend && npm ci && npx ng build --configuration production \
    --deploy-url="/app/$VERSION/" \
    --define "APP_BUILD_VERSION='\"$VERSION\"'")
BROWSER="frontend/dist/tournament-planner-ui/browser"

# 4. Stage frontend
rm -rf "$DIST"
mkdir -p "$STAGE/app/$VERSION"
cp "$BROWSER/index.html" "$STAGE/index.html"
(cd "$BROWSER" && find . -type f \
    ! -name index.html ! -name ngsw.json ! -name ngsw-worker.js \
    ! -name safety-worker.js ! -name worker-basic.min.js -print0 \
    | while IFS= read -r -d '' f; do
        mkdir -p "$OLDPWD/$STAGE/app/$VERSION/$(dirname "$f")"
        cp "$f" "$OLDPWD/$STAGE/app/$VERSION/$f"
    done)
for f in ngsw-worker.js safety-worker.js worker-basic.min.js; do
    [ -f "$BROWSER/$f" ] && cp "$BROWSER/$f" "$STAGE/$f"
done
cp site.webmanifest browserconfig.xml favicon* *.png *.svg "$STAGE/" 2>/dev/null || true

# index.html must reference only /app/<version>/ assets (or root-level static files / external URLs)
BAD=""
while IFS= read -r ref; do
    case "$ref" in
        "/app/$VERSION/"*|"/"|https://*|"#"*|data:*) ;;
        /*) [ -f "$STAGE${ref}" ] || BAD="$BAD$ref"$'\n' ;;
        *) BAD="$BAD$ref"$'\n' ;;
    esac
done < <(grep -oE '(src|href)="[^"]*"' "$STAGE/index.html" | sed -E 's/^[a-z]+="(.*)"$/\1/')
if [ -n "$BAD" ]; then
    echo "index.html references assets outside /app/$VERSION/:" >&2
    echo "$BAD" >&2
    exit 1
fi

# 5. Service worker manifest for the final layout
(cd frontend && npx ngsw-config "../$STAGE" ngsw-config.json /)

# 6. Backend
echo "Assembling backend..."
mkdir -p "$FIRST" "$LAST"
cp index.php bootstrap.php .htaccess "$FIRST/"
cp -r src "$FIRST/src"
mkdir -p "$FIRST/resources/i18n" "$FIRST/config"
cp frontend/src/i18n/*.json "$FIRST/resources/i18n/"
cp config/production.php "$FIRST/config/"
grep -v 'xdebug' php.ini > "$FIRST/php.ini"
mkdir -p "$FIRST/storage/cache" "$FIRST/logs"
touch "$FIRST/storage/cache/.gitkeep" "$FIRST/logs/.gitkeep"

cp composer.json composer.lock "$FIRST/"
composer install --working-dir="$FIRST" --no-dev --optimize-autoloader --no-interaction --quiet
rm "$FIRST/composer.json" "$FIRST/composer.lock"

cp -r bin "$FIRST/bin"
(cd "$FIRST" && php bin/build-routes.php)
rm -rf "$FIRST/bin"

# 7. Split
cp "$STAGE/index.html" "$STAGE/ngsw.json" "$LAST/"
echo "$VERSION" > "$LAST/version.txt"
rm "$STAGE/index.html" "$STAGE/ngsw.json"
cp -r "$STAGE"/. "$FIRST/"
rm -rf "$STAGE"

# 8. Keep a copy for rollback
mkdir -p releases
rm -rf "releases/$VERSION"
cp -r "$LAST" "releases/$VERSION"

# 9. Verify
php bin/verify-dist.php "$DIST"

# 10. Instructions
PREVIOUS="$(ls -1t releases | grep -vxF "$VERSION" | head -1 || true)"
echo ""
echo "======================================"
echo "  Build complete: $VERSION"
echo "======================================"
echo ""
echo "Upload (in this order):"
echo "  1. Contents of $FIRST/  ->  web root (merge, keep .env and existing app/ folders)"
echo "  2. Contents of $LAST/   ->  web root (index.html, ngsw.json, version.txt)"
echo ""
echo "Then check: https://<your-domain>/health  (version must be $VERSION)"
echo "Previous version: ${PREVIOUS:-none}  (rollback: upload releases/${PREVIOUS:-<version>}/ again)"
echo ""
echo "Don't forget the .env file on the server (DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD)."
