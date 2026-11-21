#!/bin/bash
# Production-like E2E stack without docker compose:
#   MariaDB 11 + PHP 8.3 / Apache serving the merged dist/ output on http://localhost:${E2E_PORT:-8080}
#   ./e2e/stack.sh up     assemble e2e/.site from dist/, start db + web, wait until healthy
#   ./e2e/stack.sh down   remove containers, network and e2e/.site
#   ./e2e/stack.sh deploy <dist-dir>   merge another build's dist/ into the running site (update spec)
set -euo pipefail
cd "$(dirname "$0")/.."

NET=tp-e2e
DB=tp-e2e-db
WEB=tp-e2e-web
IMAGE=tp-e2e-web
PORT="${E2E_PORT:-8080}"
SITE="$PWD/e2e/.site"

merge_dist() {
    local dist="$1"
    mkdir -p "$SITE"
    # Like the real upload: keep the server's .env and runtime state (storage/, logs/ belong to www-data)
    tar -C "$dist/1-upload-first" --exclude=./storage --exclude=./logs -cf - . | tar -C "$SITE" -xf -
    mkdir -p "$SITE/storage/cache" "$SITE/logs"
    cp -r "$dist/2-upload-last/." "$SITE/"
}

down() {
    docker rm -f "$WEB" "$DB" >/dev/null 2>&1 || true
    docker network rm "$NET" >/dev/null 2>&1 || true
    rm -rf "$SITE"
}

case "${1:-}" in
up)
    down
    merge_dist dist
    cat > "$SITE/.env" <<ENV
APP_ENV=production
APP_SECURE=false
APP_URL=http://localhost:$PORT
DB_HOST=$DB
DB_PORT=3306
DB_NAME=TPDb
DB_USERNAME=TP
DB_PASSWORD=e2e-password
ENV
    chmod -R a+rwX "$SITE/storage" "$SITE/logs"

    docker network create "$NET" >/dev/null
    docker build -q -t "$IMAGE" e2e >/dev/null
    docker run -d --name "$DB" --network "$NET" \
        -e MARIADB_ROOT_PASSWORD=e2e-root -e MARIADB_USER=TP -e MARIADB_PASSWORD=e2e-password \
        -v "$PWD/database/init.sql:/docker-entrypoint-initdb.d/01-init.sql:ro" \
        -v "$PWD/e2e/seed.sql:/docker-entrypoint-initdb.d/02-seed.sql:ro" \
        -v "$PWD/e2e/grant.sql:/docker-entrypoint-initdb.d/03-grant.sql:ro" \
        --health-cmd 'healthcheck.sh --connect --innodb_initialized' --health-interval 2s --health-retries 60 \
        mariadb:11 >/dev/null
    docker run -d --name "$WEB" --network "$NET" -p "$PORT:80" -v "$SITE:/var/www/html" "$IMAGE" >/dev/null

    echo "Waiting for the stack..."
    for _ in $(seq 1 90); do
        # The init scripts run on a temporary server first; wait for the final one with the TP user granted.
        if [ "$(docker inspect -f '{{.State.Health.Status}}' "$DB")" = healthy ] \
            && docker exec "$DB" mariadb -uTP -pe2e-password TPDb -e 'SELECT 1 FROM users LIMIT 1' >/dev/null 2>&1 \
            && curl -fsS "http://localhost:$PORT/health" >/dev/null 2>&1; then
            echo "E2E stack ready on http://localhost:$PORT"
            exit 0
        fi
        sleep 2
    done
    echo "E2E stack did not become ready" >&2
    docker logs "$DB" 2>&1 | tail -20 >&2 || true
    docker logs "$WEB" 2>&1 | tail -20 >&2 || true
    exit 1
    ;;
down)
    down
    ;;
deploy)
    merge_dist "${2:?dist dir required}"
    ;;
*)
    echo "usage: $0 up|down|deploy <dist>" >&2
    exit 2
    ;;
esac
