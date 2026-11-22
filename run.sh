#!/bin/bash
# Development: PHP (JSON API) on http://localhost:5000,
# Angular dev server on http://localhost:4200 (proxies /api to PHP).
#   ./run.sh            El Faro
#   ./run.sh amigos     Amigos de Tauro
set -e
cd "$(dirname "$0")"
FLAVOR="${1:-elfaro}"
if [ ! -f "frontend/flavors/$FLAVOR/flavor.json" ]; then
    echo "Unknown flavour: $FLAVOR (one of: $(ls frontend/flavors | tr '\n' ' '))" >&2
    exit 2
fi
trap 'kill 0' EXIT
APP_FLAVOR="$FLAVOR" ROUTE_CACHE_ENABLED=true php -S localhost:5000 -c php.ini &
cd frontend && npm start -- --configuration "development-$FLAVOR"
