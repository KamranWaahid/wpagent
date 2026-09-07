#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAGING="$(mktemp -d)"
OUT_DIR="$ROOT/dist"
ZIP="$OUT_DIR/wpagent.zip"

mkdir -p "$OUT_DIR"
mkdir -p "$STAGING/wpagent"

rsync -a \
  --exclude vendor \
  --exclude .phpunit.cache \
  --exclude tests \
  --exclude composer.json \
  --exclude composer.lock \
  --exclude phpunit.xml.dist \
  "$ROOT/plugin/" "$STAGING/wpagent/"

rm -f "$ZIP"
(
  cd "$STAGING"
  zip -r "$ZIP" wpagent >/dev/null
)

rm -rf "$STAGING"
echo "Wrote $ZIP"
