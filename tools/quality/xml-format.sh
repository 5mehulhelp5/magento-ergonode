#!/usr/bin/env bash
# Project-owned backend tooling shared by Makefile entrypoints.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MODE="${1:-}"
TARGET="${2:-}"
PRETTIER_BIN="${XML_PRETTIER_BIN:-$ROOT_DIR/node_modules/.bin/prettier}"
CONFIG_PATH="$ROOT_DIR/.prettierrc.json"
IGNORE_PATH="$ROOT_DIR/.prettierignore"
FILES=()

usage() {
  printf '%s\n' 'Usage: xml-format.sh check|write file-or-directory' >&2
}

if [ "$#" -ne 2 ] || [[ ! "$MODE" =~ ^(check|write)$ ]]; then
  usage
  exit 2
fi

if [[ "$TARGET" == *://* ]]; then
  printf 'XML formatting requires a local filesystem path: %s\n' "$TARGET" >&2
  exit 2
fi

if [ ! -e "$TARGET" ]; then
  printf 'XML formatting path does not exist: %s\n' "$TARGET" >&2
  exit 2
fi

if [ ! -x "$PRETTIER_BIN" ]; then
  printf '%s\n' \
    "Local Prettier XML dependency is unavailable: $PRETTIER_BIN" \
    'Run: ddev exec npm ci' >&2
  exit 2
fi

if [ -f "$TARGET" ]; then
  if [[ "$TARGET" != *.xml ]]; then
    printf 'XML formatting scope is not a lowercase .xml file: %s\n' "$TARGET" >&2
    exit 2
  fi
  TARGET="$(cd "$(dirname "$TARGET")" && pwd -P)/$(basename "$TARGET")"
  FILES+=("$TARGET")
elif [ -d "$TARGET" ]; then
  TARGET="$(cd "$TARGET" && pwd -P)"
  while IFS= read -r file; do
    FILES+=("$file")
  done < <(find "$TARGET" -type f -name '*.xml' -print | LC_ALL=C sort)
else
  printf 'XML formatting scope must be a regular file or directory: %s\n' "$TARGET" >&2
  exit 2
fi

if [ "${#FILES[@]}" -eq 0 ]; then
  printf 'XML formatting scope contains no lowercase .xml files: %s\n' "$TARGET" >&2
  exit 2
fi

PRETTIER_ARGS=(
  --config "$CONFIG_PATH"
  --ignore-path "$IGNORE_PATH"
  --plugin "$ROOT_DIR/node_modules/@prettier/plugin-xml/src/plugin.js"
  --parser xml
  --tab-width 4
  --print-width 200
  --no-single-attribute-per-line
  --xml-quote-attributes double
  --no-xml-self-closing-space
  --no-xml-sort-attributes-by-key
  --xml-whitespace-sensitivity preserve
)

if [ "$MODE" = 'check' ]; then
  PRETTIER_ARGS+=(--check)
else
  PRETTIER_ARGS+=(--write)
fi

cd "$ROOT_DIR"
"$PRETTIER_BIN" "${PRETTIER_ARGS[@]}" "${FILES[@]}"
