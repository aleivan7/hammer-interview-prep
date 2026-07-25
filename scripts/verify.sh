#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "==> Backend formatting"
(
    cd "$ROOT/backend"
    vendor/bin/pint --test
)

echo "==> Backend tests"
(
    cd "$ROOT/backend"
    php artisan test
)

echo "==> Frontend type check"
(
    cd "$ROOT/frontend"
    npm run typecheck
)

echo "==> Frontend tests"
(
    cd "$ROOT/frontend"
    npm run test
)

echo "==> Frontend production build"
(
    cd "$ROOT/frontend"
    npm run build
)

echo "==> All verification checks passed"
