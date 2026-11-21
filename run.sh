#!/bin/bash
# Development: PHP (JSON API) on http://localhost:5000,
# Angular dev server on http://localhost:4200 (proxies /api to PHP).
set -e
trap 'kill 0' EXIT
ROUTE_CACHE_ENABLED=true php -S localhost:5000 -c php.ini &
cd frontend && npm start
