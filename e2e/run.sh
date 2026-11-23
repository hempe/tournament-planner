#!/bin/bash
# Full E2E run: build two releases -> start Docker stack with release 1 -> Playwright -> tear down.
#   npm run e2e                     (from frontend/)
#   E2E_SKIP_SLOW=1 npm run e2e     skip the service-worker update spec
#   npm run e2e -- admin.spec.ts    extra arguments go to `playwright test` (El Faro only)
#   E2E_SKIP_AMIGOS=1 npm run e2e   skip the second run against an Amigos build
set -euo pipefail
cd "$(dirname "$0")/.."

cleanup() { e2e/stack.sh down; rm -rf releases/e2e-1-* releases/e2e-2-* releases/e2e-a-*; }
trap cleanup EXIT

rm -rf e2e/.dist-v1 e2e/.dist-v2
BUILD_VERSION=e2e-1 ./build.sh --dirty --flavor elfaro
mv dist/elfaro e2e/.dist-v1
BUILD_VERSION=e2e-2 ./build.sh --dirty --flavor elfaro
mv dist/elfaro e2e/.dist-v2
rm -rf dist
mkdir -p dist
cp -r e2e/.dist-v1/. dist/

e2e/stack.sh up
(cd frontend && npx playwright test "$@")

# Second run: an Amigos build with its own seed (skipped for single-spec runs)
if [ -z "${E2E_SKIP_AMIGOS:-}" ] && [ $# -eq 0 ]; then
    e2e/stack.sh down
    BUILD_VERSION=e2e-a ./build.sh --dirty --flavor amigos
    mv dist/amigos dist.e2e-amigos && rm -rf dist && mv dist.e2e-amigos dist
    E2E_SEED=e2e/seed-amigos.sql e2e/stack.sh up
    (cd frontend && E2E_FLAVOR=amigos E2E_SEED=e2e/seed-amigos.sql npx playwright test)
fi
