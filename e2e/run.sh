#!/bin/bash
# Full E2E run: build two releases -> start Docker stack with release 1 -> Playwright -> tear down.
#   npm run e2e                     (from frontend/)
#   E2E_SKIP_SLOW=1 npm run e2e     skip the service-worker update spec
#   npm run e2e -- admin.spec.ts    extra arguments go to `playwright test`
set -euo pipefail
cd "$(dirname "$0")/.."

cleanup() { e2e/stack.sh down; rm -rf releases/e2e-1-* releases/e2e-2-*; }
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
