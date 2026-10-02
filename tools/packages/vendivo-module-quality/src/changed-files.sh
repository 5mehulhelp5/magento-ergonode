#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="${MODULE_QUALITY_ROOT:-$PWD}"
MODULE_ROOT="${MODULE_QUALITY_MODULE_ROOT:-app/code}"
BASE_COMMIT="${MODULE_QUALITY_BASE_COMMIT:-}"
SOURCE_CHANGES_FILE="${MODULE_QUALITY_SOURCE_CHANGES_FILE:-}"
MODULE="${1:-}"

if [[ ! "$MODULE" =~ ^[A-Z][A-Za-z0-9]*_[A-Z][A-Za-z0-9]*$ ]]; then
  printf '%s\n' 'Usage: changed-files.sh Vendor_Module' >&2
  exit 2
fi

MODULE_PATH="${MODULE_QUALITY_MODULE_PATH:-$MODULE_ROOT/${MODULE/_//}}"

filter_module_paths() {
  LC_ALL=C awk -v root="$ROOT_DIR/" -v prefix="$MODULE_PATH/" '
    BEGIN {
      gsub(/\/+/, "/", root)
      gsub(/\/+/, "/", prefix)
    }
    {
      sub(/\r$/, "")
      sub(/^\.\//, "")
      gsub(/\/+/, "/")
      if (index($0, root) == 1) {
        $0 = substr($0, length(root) + 1)
      }
      if (index($0, prefix) == 1) {
        print
      }
    }
  ' | LC_ALL=C sort -u
}

if [ -n "$SOURCE_CHANGES_FILE" ]; then
  if [ ! -f "$SOURCE_CHANGES_FILE" ]; then
    printf 'Source changes file does not exist: %s\n' "$SOURCE_CHANGES_FILE" >&2
    exit 2
  fi
  filter_module_paths < "$SOURCE_CHANGES_FILE"
  exit 0
fi

if [ -z "$BASE_COMMIT" ]; then
  printf 'No reliable Git base for %s; provide --base-commit or --source-changes-file.\n' \
    "$MODULE" >&2
  exit 2
fi
if ! git -C "$ROOT_DIR" cat-file -e "$BASE_COMMIT^{commit}" 2>/dev/null; then
  printf 'Invalid Git base for %s: %s\n' "$MODULE" "$BASE_COMMIT" >&2
  exit 2
fi

cd "$ROOT_DIR"
{
  git -c core.quotePath=false diff \
    --no-renames --name-only --diff-filter=ACDMRTUXB "$BASE_COMMIT" HEAD -- "$MODULE_PATH"
  git -c core.quotePath=false diff \
    --no-renames --name-only --diff-filter=ACDMRTUXB -- "$MODULE_PATH"
  git -c core.quotePath=false diff \
    --cached --no-renames --name-only --diff-filter=ACDMRTUXB -- "$MODULE_PATH"
  git -c core.quotePath=false ls-files --others --exclude-standard -- "$MODULE_PATH"
} | filter_module_paths
