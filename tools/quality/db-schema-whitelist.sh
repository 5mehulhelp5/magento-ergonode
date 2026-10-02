#!/usr/bin/env bash
# Project-owned backend tooling shared by Makefile entrypoints.

set -euo pipefail

usage() {
  printf '%s\n' \
    'Usage: db-schema-whitelist.sh generate|check Vendor_Module [module-path] -- command [args...]' >&2
  exit 2
}

[ "$#" -ge 2 ] || usage

MODE=$1
MODULE=$2
shift 2

if [ "${1:-}" = '--' ]; then
  MODULE_PATH="app/code/${MODULE/_//}"
else
  [ "$#" -gt 0 ] || usage
  MODULE_PATH=$1
  shift
fi

[ "${1:-}" = '--' ] || usage
shift
[ "$#" -gt 0 ] || usage

[[ "$MODE" =~ ^(generate|check)$ ]] || usage
[[ "$MODULE" =~ ^[A-Za-z][A-Za-z0-9]*_[A-Za-z][A-Za-z0-9]*$ ]] || usage
[ -n "$MODULE_PATH" ] || usage

SCHEMA_PATH="$MODULE_PATH/etc/db_schema.xml"
WHITELIST_PATH="$MODULE_PATH/etc/db_schema_whitelist.json"

if [ ! -f "$SCHEMA_PATH" ]; then
  printf 'Declarative schema does not exist: %s\n' "$SCHEMA_PATH" >&2
  exit 2
fi

SNAPSHOT=''
WHITELIST_EXISTED=0
RESTORE_ON_EXIT=0

cleanup() {
  local original_status="$?"
  local cleanup_failed=0

  trap - EXIT

  if [ "$RESTORE_ON_EXIT" -eq 1 ]; then
    if [ "$WHITELIST_EXISTED" -eq 1 ]; then
      if ! cp "$SNAPSHOT" "$WHITELIST_PATH"; then
        printf 'Failed to restore declarative schema whitelist: %s\n' "$WHITELIST_PATH" >&2
        cleanup_failed=1
      fi
    elif ! rm -f "$WHITELIST_PATH"; then
      printf 'Failed to restore absent declarative schema whitelist: %s\n' "$WHITELIST_PATH" >&2
      cleanup_failed=1
    fi
  fi

  if [ -n "$SNAPSHOT" ] && ! rm -f "$SNAPSHOT"; then
    printf 'Failed to remove declarative schema whitelist snapshot: %s\n' "$SNAPSHOT" >&2
    cleanup_failed=1
  fi

  if [ "$cleanup_failed" -ne 0 ]; then
    exit 2
  fi

  exit "$original_status"
}

if ! SNAPSHOT="$(mktemp "${TMPDIR:-/tmp}/db-schema-whitelist.XXXXXX")"; then
  printf '%s\n' 'Failed to create declarative schema whitelist snapshot.' >&2
  exit 2
fi
trap cleanup EXIT

if [ -f "$WHITELIST_PATH" ]; then
  if ! cp "$WHITELIST_PATH" "$SNAPSHOT"; then
    printf 'Failed to snapshot declarative schema whitelist: %s\n' "$WHITELIST_PATH" >&2
    exit 2
  fi
  WHITELIST_EXISTED=1
fi

if [ "$MODE" = 'check' ]; then
  RESTORE_ON_EXIT=1
fi

if ! "$@" setup:db-declaration:generate-whitelist "--module-name=$MODULE"; then
  printf '%s\n' 'Magento declarative schema whitelist generation command failed.' >&2
  exit 2
fi

if [ ! -f "$WHITELIST_PATH" ]; then
  printf 'Magento did not generate declarative schema whitelist: %s\n' "$WHITELIST_PATH" >&2
  exit 2
fi

CHANGED=1
if [ "$WHITELIST_EXISTED" -eq 1 ]; then
  if cmp -s "$SNAPSHOT" "$WHITELIST_PATH"; then
    CMP_STATUS=0
  else
    CMP_STATUS="$?"
  fi

  case "$CMP_STATUS" in
    0) CHANGED=0 ;;
    1) CHANGED=1 ;;
    *)
      printf 'Failed to compare declarative schema whitelist: %s\n' "$WHITELIST_PATH" >&2
      exit 2
      ;;
  esac
fi

if [ "$MODE" = 'check' ]; then
  if [ "$CHANGED" -eq 1 ]; then
    printf 'Declarative schema whitelist drift detected: %s\n' "$WHITELIST_PATH" >&2
    exit 1
  fi

  printf 'Declarative schema whitelist current: %s\n' "$WHITELIST_PATH"
  exit 0
fi

if [ "$CHANGED" -eq 0 ]; then
  STATUS=current
elif [ "$WHITELIST_EXISTED" -eq 1 ]; then
  STATUS=updated
else
  STATUS=created
fi

printf 'Declarative schema whitelist %s: %s\n' "$STATUS" "$WHITELIST_PATH"
