#!/usr/bin/env bash
# Run the FULL CI gate locally, mirroring .github/workflows/{lint,tests}.yml,
# so nothing is pushed that CI would reject. Keep this in sync with those files.
set -euo pipefail
cd "$(dirname "$0")"

echo "▶ Pint (PHP style)";            vendor/bin/pint --test
echo "▶ Prettier (format:check)";     npm run format:check
echo "▶ ESLint";                      npx eslint .
echo "▶ PHPStan (static analysis)";   vendor/bin/phpstan analyse --no-progress
echo "▶ Build (client + SSR)";        npm run build
echo "▶ JS unit tests (vitest)";      npm run test:js
echo "▶ Pest";                        ./vendor/bin/pest
echo "▶ composer audit";              composer audit --no-interaction
echo "▶ npm audit (gated allowlist)"; npm run audit:js

echo "✅ CI gate passed locally"
