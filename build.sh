#!/bin/bash

# Build script — produces dist/<flavor>/1-upload-first/ and dist/<flavor>/2-upload-last/.
#   ./build.sh --flavor elfaro           clean working tree required; the flavour is required
#   ./build.sh --flavor amigos --dirty   allow uncommitted changes (version gets a -dirty suffix)
#   BUILD_VERSION=x ./build.sh --flavor elfaro   override the date-commit part of the version (E2E run)

set -euo pipefail
cd "$(dirname "$0")"

DIRTY=0
FLAVOR=""
FLAVORS="$(ls frontend/flavors | tr '\n' ' ')"
while [ $# -gt 0 ]; do
    case "$1" in
        --dirty) DIRTY=1 ;;
        --flavor) FLAVOR="${2:-}"; shift ;;
        --flavor=*) FLAVOR="${1#*=}" ;;
        *) echo "Unknown argument: $1" >&2; exit 2 ;;
    esac
    shift
done
if [ -z "$FLAVOR" ]; then
    echo "Missing --flavor <name> (one of: $FLAVORS)" >&2
    exit 2
fi
if [ ! -f "frontend/flavors/$FLAVOR/flavor.json" ]; then
    echo "Unknown flavour: $FLAVOR (one of: $FLAVORS)" >&2
    exit 2
fi

DIST="dist/$FLAVOR"
STAGE="$DIST/.stage-frontend"
FIRST="$DIST/1-upload-first"
LAST="$DIST/2-upload-last"

# 1. Version
VERSION="${BUILD_VERSION:-$(date +%Y-%m-%d)-$(git rev-parse --short HEAD)}-$FLAVOR"
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
echo "  Tournament Planner Build $VERSION ($FLAVOR)"
echo "======================================"

# Lint PHP against production's PHP version (8.3)
if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
    echo "Linting PHP with php:8.3-cli..."
    # Pull first: the progress output of an implicit pull would end up in LINT_OUT and fail the build
    docker image inspect php:8.3-cli >/dev/null 2>&1 || docker pull -q php:8.3-cli >/dev/null
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
(cd frontend && npm ci && npx ng build --configuration "production,$FLAVOR" \
    --deploy-url="/app/$VERSION/" \
    --define "APP_BUILD_VERSION='\"$VERSION\"'")
BROWSER="frontend/dist/tournament-planner-ui/browser"

# 4. Stage frontend
rm -rf "$DIST"
mkdir -p "$STAGE/app/$VERSION"
cp "$BROWSER/index.html" "$STAGE/index.html"
# The flavour's static files (icons, manifest, …) stay at the web root, everything else is versioned
ASSETS="frontend/flavors/$FLAVOR/assets"
ROOT_FILES="$(cd "$ASSETS" && find . -type f ! -name .gitkeep | sed 's|^\./||')"
(cd "$BROWSER" && find . -type f \
    ! -name index.html ! -name ngsw.json ! -name ngsw-worker.js \
    ! -name safety-worker.js ! -name worker-basic.min.js -print0 \
    | while IFS= read -r -d '' f; do
        if printf '%s\n' "$ROOT_FILES" | grep -qxF "${f#./}"; then
            continue
        fi
        mkdir -p "$OLDPWD/$STAGE/app/$VERSION/$(dirname "$f")"
        cp "$f" "$OLDPWD/$STAGE/app/$VERSION/$f"
    done)
for f in ngsw-worker.js safety-worker.js worker-basic.min.js; do
    [ -f "$BROWSER/$f" ] && cp "$BROWSER/$f" "$STAGE/$f"
done
while IFS= read -r f; do
    [ -n "$f" ] || continue
    mkdir -p "$STAGE/$(dirname "$f")"
    cp "$BROWSER/$f" "$STAGE/$f"
done <<< "$ROOT_FILES"

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
# Flavour: config/flavor.json (with its id) and the text overrides, read by TP\Core\Flavor / Translator
php -r '$f = json_decode(file_get_contents($argv[1]), true, 32, JSON_THROW_ON_ERROR); $f["id"] = $argv[2]; echo json_encode($f, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";' \
    "frontend/flavors/$FLAVOR/flavor.json" "$FLAVOR" > "$FIRST/config/flavor.json"
mkdir -p "$FIRST/resources/i18n/flavor"
cp frontend/flavors/"$FLAVOR"/i18n/*.json "$FIRST/resources/i18n/flavor/"
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
php bin/verify-dist.php "$DIST" "$FLAVOR"

# 10. Instructions
PREVIOUS="$(ls -1t releases | grep -e "-$FLAVOR\(-dirty\)\?$" | grep -vxF "$VERSION" | head -1 || true)"
echo ""
echo "======================================"
echo "  Build complete: $VERSION"
echo "  Flavour: $FLAVOR — upload to the $FLAVOR host only"
echo "======================================"
echo ""
echo "Upload (in this order):"
echo "  1. Contents of $FIRST/  ->  web root (merge, keep .env and existing app/ folders)"
echo "  2. Contents of $LAST/   ->  web root (index.html, ngsw.json, version.txt)"
echo ""
echo "Then check: https://<your-domain>/version.txt  (must show $VERSION; the app footer shows it too)"
echo "Previous version: ${PREVIOUS:-none}  (rollback: upload releases/${PREVIOUS:-<version>}/ again)"
echo ""
echo "Don't forget the .env file on the server (DB_HOST, DB_PORT, DB_NAME, DB_USERNAME, DB_PASSWORD)."
