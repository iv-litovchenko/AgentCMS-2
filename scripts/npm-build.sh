#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "${ROOT}/apps/cms"
if [ ! -d node_modules ]; then
  npm ci
fi
npm run build
echo "OK: apps/cms/public/build/app.css"
