#!/usr/bin/env bash
set -euo pipefail

command -v rsync >/dev/null 2>&1 || { echo "rsync is required to package."; exit 1; }
command -v zip >/dev/null 2>&1 || { echo "zip is required to package."; exit 1; }

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_DIR="$ROOT/dist"
SLUG="team-profiles"
STAGE="$DIST_DIR/$SLUG"

mkdir -p "$DIST_DIR"
rm -rf "$STAGE"

# Copy plugin files into a clean staging directory.
rsync -a \
  --exclude '.git' \
  --exclude 'dist' \
  --exclude '.DS_Store' \
  --exclude '.gitignore' \
  --exclude '.vscode' \
  --exclude 'node_modules' \
  --exclude '*.zip' \
  "$ROOT/" "$STAGE/"

cd "$DIST_DIR"
zip -r "${SLUG}.zip" "$SLUG" >/dev/null
echo "Package created: $DIST_DIR/${SLUG}.zip"
