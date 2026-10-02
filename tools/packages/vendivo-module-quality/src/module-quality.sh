#!/usr/bin/env bash
set -uo pipefail

ROOT_DIR="${MODULE_QUALITY_ROOT:-$PWD}"
MODULE_ROOT="${MODULE_QUALITY_MODULE_ROOT:-app/code}"
REPORT_DIR="${MODULE_QUALITY_REPORT_DIR:-$ROOT_DIR/var/module-quality/reports}"
MANIFEST_DIR="${MODULE_QUALITY_MANIFEST_DIR:-$REPORT_DIR}"
GATE_RUNNER="${MODULE_QUALITY_GATE_RUNNER:-}"
PACKAGE_DIR="${MODULE_QUALITY_PACKAGE_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
CHANGED_FILES_SCRIPT="$PACKAGE_DIR/src/changed-files.sh"
DIAGNOSTIC_SUMMARY_SCRIPT="$PACKAGE_DIR/src/diagnostic-summary.pl"

MODE="${1:-}"
MODULE="${2:-}"
MODE_OPTION="${3:-}"
RUN_INTEGRATION=0
STATIC_SCOPE='full'
FAIL_FAST=0
VERBOSE="${MODULE_QUALITY_VERBOSE:-0}"
DIAGNOSTIC_LINES="${MODULE_QUALITY_DIAGNOSTIC_LINES:-8}"
DIAGNOSTIC_COLUMNS="${MODULE_QUALITY_DIAGNOSTIC_COLUMNS:-500}"
FAILURES=0
WARNINGS=0
GATE_INDEX=0
GATE_OUTCOME='PASS'
FAILED_GATES=()
CHANGE_MANIFEST_AVAILABLE=0
SCOPED_ANALYSIS=0
PHPCS_GATE_LABEL='PHPCS'
PHPMD_GATE_LABEL='PHPMD'
PHPCS_SUPPORTED_EXTENSIONS="${MODULE_QUALITY_PHPCS_EXTENSIONS:-php phtml graphqls less css html xml js}"
PHPMD_CACHE_FILE="${MODULE_QUALITY_PHPMD_CACHE:-var/phpmd.result-cache.php}"

usage() {
  printf '%s\n' \
    'Usage: module-quality.sh check|audit|done Vendor_Module [integration=0|1]' \
    '       module-quality.sh static Vendor_Module [changed|full]' >&2
}

case "$MODE" in
  check)
    RUN_INTEGRATION="${MODE_OPTION:-0}"
    STATIC_SCOPE='changed'
    FAIL_FAST=1
    ;;
  audit|done)
    RUN_INTEGRATION="${MODE_OPTION:-0}"
    ;;
  static)
    STATIC_SCOPE="${MODE_OPTION:-changed}"
    if [ "$STATIC_SCOPE" = 'changed' ]; then
      FAIL_FAST=1
    fi
    ;;
  *) usage; exit 2 ;;
esac

if [[ ! "$MODULE" =~ ^[A-Z][A-Za-z0-9]*_[A-Z][A-Za-z0-9]*$ ]]; then
  usage
  exit 2
fi
if [ "$MODE" = 'static' ] && [[ ! "$STATIC_SCOPE" =~ ^(changed|full)$ ]]; then
  usage
  exit 2
fi
if [[ ! "$RUN_INTEGRATION" =~ ^(0|1)$ ]]; then
  usage
  exit 2
fi

MODULE_PATH="${MODULE_QUALITY_MODULE_PATH:-$MODULE_ROOT/${MODULE/_//}}"
cd "$ROOT_DIR" || exit 2
if [ ! -d "$MODULE_PATH" ]; then
  printf 'Module path does not exist: %s\n' "$MODULE_PATH" >&2
  exit 2
fi
if [ -z "$GATE_RUNNER" ] || [ ! -x "$GATE_RUNNER" ]; then
  printf 'Gate runner is required and must be executable: %s\n' "${GATE_RUNNER:--}" >&2
  exit 2
fi

mkdir -p "$REPORT_DIR" "$MANIFEST_DIR"
CHANGED_FILES_MANIFEST="$MANIFEST_DIR/module-quality-$MODULE-$MODE-changed-files.txt"
PHPCS_FILES_MANIFEST="$MANIFEST_DIR/module-quality-$MODULE-$MODE-phpcs-files.txt"
PHPMD_FILES_MANIFEST="$MANIFEST_DIR/module-quality-$MODULE-$MODE-phpmd-files.txt"

relative_project_path() {
  local path="$1"

  case "$path" in
    "$ROOT_DIR"/*) printf '%s\n' "${path#"$ROOT_DIR"/}" ;;
    *) printf '%s\n' "$path" ;;
  esac
}

sanitize_log() {
  perl -pe 's/\e\[[0-9;]*[mK]//g' | cut -c "1-$DIAGNOSTIC_COLUMNS"
}

diagnostic_key() {
  local label="$1"

  printf '%s' "$label" \
    | tr '[:upper:] ' '[:lower:]-' \
    | tr -cd 'a-z0-9_-'
}

print_log_excerpt() {
  local log_file="$1"
  local line_count
  local head_lines
  local tail_lines
  local omitted_lines

  line_count="$(wc -l < "$log_file")"
  if [ "$line_count" -le "$DIAGNOSTIC_LINES" ]; then
    sanitize_log < "$log_file"
    return
  fi

  head_lines=$((DIAGNOSTIC_LINES * 3 / 4))
  tail_lines=$((DIAGNOSTIC_LINES - head_lines))
  omitted_lines=$((line_count - DIAGNOSTIC_LINES))
  head -n "$head_lines" "$log_file" | sanitize_log
  printf '... %s log lines omitted ...\n' "$omitted_lines"
  tail -n "$tail_lines" "$log_file" | sanitize_log
}

run_gate() {
  local label="$1"
  local gate_key
  local log_file
  local state_file
  local summary_file
  local result
  shift

  GATE_INDEX=$((GATE_INDEX + 1))
  GATE_OUTCOME='PASS'
  gate_key="$(diagnostic_key "$label")"
  log_file="$REPORT_DIR/module-quality-$MODULE-$MODE-$(printf '%02d' "$GATE_INDEX").log"
  state_file="$REPORT_DIR/.diagnostics/$MODULE-$gate_key.json"
  summary_file="$REPORT_DIR/module-quality-$MODULE-$MODE-$(printf '%02d' "$GATE_INDEX")-summary.json"
  if [ "$VERBOSE" -eq 1 ]; then
    printf '[%s] RUN %s\n' "$GATE_INDEX" "$label"
  else
    printf '[%s] %s ... ' "$GATE_INDEX" "$label"
  fi
  "$@" > "$log_file" 2>&1
  result="$?"
  if [ "$result" -eq 0 ]; then
    if [ -e "$state_file" ]; then
      mkdir -p "$(dirname "$state_file")"
      printf '%s\n' '{"diagnostics":[]}' > "$state_file"
    fi
    if [ "$VERBOSE" -eq 1 ]; then
      cat "$log_file"
      printf '[%s] %s %s\n' "$GATE_INDEX" "$GATE_OUTCOME" "$label"
    else
      printf '%s\n' "$GATE_OUTCOME"
    fi
    return 0
  fi

  FAILURES=$((FAILURES + 1))
  FAILED_GATES+=("$label")
  if [ "$VERBOSE" -eq 1 ]; then
    printf '[%s] FAIL %s (exit %s)\n' "$GATE_INDEX" "$label" "$result"
  else
    printf 'FAIL (exit %s)\n' "$result"
  fi
  if [ "$VERBOSE" -eq 1 ]; then
    cat "$log_file" >&2
    printf 'Artifact: log=%s\n' "$(relative_project_path "$log_file")" >&2
  else
    if perl "$DIAGNOSTIC_SUMMARY_SCRIPT" \
      --gate="$label" \
      --log="$log_file" \
      --state="$state_file" \
      --output="$summary_file" \
      --limit="$DIAGNOSTIC_LINES" \
      --columns="$DIAGNOSTIC_COLUMNS" >&2; then
      printf 'Artifacts: summary=%s log=%s\n' \
        "$(relative_project_path "$summary_file")" \
        "$(relative_project_path "$log_file")" >&2
    else
      printf '%s\n' 'Diagnostic normalizer failed; raw excerpt follows:' >&2
      print_log_excerpt "$log_file" >&2
      printf 'Artifact: log=%s\n' "$(relative_project_path "$log_file")" >&2
    fi
  fi
  if [ "$FAIL_FAST" -eq 1 ]; then
    print_summary
    exit 1
  fi
}

run_adapter() {
  local gate="$1"
  shift

  "$GATE_RUNNER" "$gate" "$MODULE" "$MODULE_PATH" "$@"
}

check_required_files() {
  local missing=0
  local path

  for path in registration.php etc/module.xml composer.json; do
    if [ ! -f "$MODULE_PATH/$path" ]; then
      printf 'Missing required module file: %s/%s\n' "$MODULE_PATH" "$path" >&2
      missing=1
    fi
  done

  return "$missing"
}

run_unit_tests() {
  local test_path="$MODULE_PATH/Test/Unit"

  if [ ! -d "$test_path" ] || ! find "$test_path" -type f -name '*Test.php' -print -quit | grep -q .; then
    GATE_OUTCOME='SKIP no-tests'
    return 0
  fi
  run_adapter unit "$test_path"
}

run_integration_tests() {
  local test_path="$MODULE_PATH/Test/Integration"

  if [ ! -d "$test_path" ] || ! find "$test_path" -type f -name '*Test.php' -print -quit | grep -q .; then
    GATE_OUTCOME='SKIP no-tests'
    return 0
  fi
  run_adapter integration "$test_path"
}

run_composer_check() {
  local output
  local result
  local warning_count

  if [ ! -f "$MODULE_PATH/composer.json" ]; then
    GATE_OUTCOME='SKIP missing-composer-json'
    return 0
  fi

  output="$(run_adapter composer 2>&1)"
  result="$?"
  printf '%s\n' "$output"
  warning_count="$(printf '%s\n' "$output" | sed -n \
    's/^.*\[MODULE-COMPOSER\].* warnings=\([0-9][0-9]*\)$/\1/p' \
    | tail -1)"
  WARNINGS=$((WARNINGS + ${warning_count:-0}))
  if [ "${warning_count:-0}" -gt 0 ]; then
    GATE_OUTCOME="WARN warnings=$warning_count"
  fi

  return "$result"
}

prepare_change_manifest() {
  local scope_log="$REPORT_DIR/module-quality-$MODULE-$MODE-changed-files.log"

  : > "$CHANGED_FILES_MANIFEST"
  if bash "$CHANGED_FILES_SCRIPT" "$MODULE" > "$CHANGED_FILES_MANIFEST" 2> "$scope_log"; then
    CHANGE_MANIFEST_AVAILABLE=1
  else
    printf 'WARN: changed-file manifest unavailable; analyzing the full module. Details: %s\n' \
      "$(relative_project_path "$scope_log")" >&2
  fi
}

prepare_scoped_analysis() {
  local extension
  local path

  if [ "$STATIC_SCOPE" != 'changed' ] || [ "$CHANGE_MANIFEST_AVAILABLE" -ne 1 ]; then
    return
  fi

  : > "$PHPCS_FILES_MANIFEST"
  : > "$PHPMD_FILES_MANIFEST"
  while IFS= read -r path; do
    if [ ! -f "$path" ]; then
      continue
    fi
    extension="${path##*.}"
    case " $PHPCS_SUPPORTED_EXTENSIONS " in
      *" $extension "*) printf '%s\n' "$path" >> "$PHPCS_FILES_MANIFEST" ;;
    esac
    case "$path" in
      *.php)
        if [[ "$path" != */Test/* ]]; then
          printf '%s\n' "$path" >> "$PHPMD_FILES_MANIFEST"
        fi
        ;;
    esac
  done < "$CHANGED_FILES_MANIFEST"

  SCOPED_ANALYSIS=1
  PHPCS_GATE_LABEL='Vendivo PHPCS (changed files)'
  PHPMD_GATE_LABEL='PHPMD (changed files)'
}

run_phpcs() {
  if [ "$SCOPED_ANALYSIS" -ne 1 ]; then
    run_adapter phpcs full
    return
  fi
  if [ ! -s "$PHPCS_FILES_MANIFEST" ]; then
    GATE_OUTCOME='SKIP no-supported-files'
    return 0
  fi
  run_adapter phpcs files "$(relative_project_path "$PHPCS_FILES_MANIFEST")"
}

run_phpmd() {
  if [ "$SCOPED_ANALYSIS" -ne 1 ]; then
    run_adapter phpmd full "$PHPMD_CACHE_FILE"
    return
  fi
  if [ ! -s "$PHPMD_FILES_MANIFEST" ]; then
    GATE_OUTCOME='SKIP no-production-php'
    return 0
  fi
  run_adapter phpmd files "$(relative_project_path "$PHPMD_FILES_MANIFEST")" "$PHPMD_CACHE_FILE"
}

run_static_analysis() {
  run_gate "$PHPCS_GATE_LABEL" run_phpcs
  run_gate "$PHPMD_GATE_LABEL" run_phpmd
  run_gate 'PHPStan' run_adapter phpstan
  run_gate 'PHPArkitect' run_adapter arkitect
}

db_schema_changed() {
  local schema_path="$MODULE_PATH/etc/db_schema.xml"

  if [ "$CHANGE_MANIFEST_AVAILABLE" -eq 1 ]; then
    grep -Fxq "$schema_path" "$CHANGED_FILES_MANIFEST"
    return
  fi
  git status --porcelain=v1 --untracked-files=all -- "$schema_path" | grep -q .
}

audit_development_lifecycle() {
  local log_file="$REPORT_DIR/module-quality-$MODULE-$MODE-advisory.log"
  local marker_count

  rg -n -i \
    '@deprecated|class_alias[[:space:]]*\(|interface_alias[[:space:]]*\(|backward compatibility|backwards compatibility|legacy (alias|adapter|fallback)|compatibility (alias|adapter|fallback)' \
    "$MODULE_PATH" \
    --glob '*.php' --glob '*.phtml' --glob '*.xml' --glob '*.graphqls' \
    > "$log_file" 2>/dev/null || true

  if [ ! -s "$log_file" ]; then
    printf '%s\n' 'ADVISORY-SCAN: PASS markers=0'
    return 0
  fi

  WARNINGS=$((WARNINGS + 1))
  marker_count="$(wc -l < "$log_file" | tr -d ' ')"
  printf 'ADVISORY-SCAN: WARN markers=%s; verify released-contract need\n' "$marker_count"
  if [ "$VERBOSE" -eq 1 ]; then
    cat "$log_file"
  else
    print_log_excerpt "$log_file"
  fi
  printf 'Artifact: log=%s\n' "$(relative_project_path "$log_file")"
}

print_summary() {
  local index
  local failed_list=''
  local scope_part=''
  local advisory_part=''

  if [ "$MODE" = 'static' ]; then
    scope_part=" scope=$STATIC_SCOPE"
  else
    advisory_part=" advisories=$WARNINGS"
  fi
  if [ "$FAILURES" -eq 0 ]; then
    printf 'SUMMARY: PASS module=%s mode=%s%s gates=%s%s\n' \
      "$MODULE" "$MODE" "$scope_part" "$GATE_INDEX" "$advisory_part"
  else
    for index in "${!FAILED_GATES[@]}"; do
      if [ -n "$failed_list" ]; then
        failed_list+=','
      fi
      failed_list+="${FAILED_GATES[$index]}"
    done
    printf 'SUMMARY: FAIL module=%s mode=%s%s gates=%s failures=%s%s failed=%s\n' \
      "$MODULE" "$MODE" "$scope_part" "$GATE_INDEX" "$FAILURES" \
      "$advisory_part" "$failed_list" >&2
  fi
}

if [ "$STATIC_SCOPE" = 'changed' ] || [ "$MODE" = 'done' ]; then
  prepare_change_manifest
fi
prepare_scoped_analysis

if [ "$MODE" = 'static' ]; then
  run_static_analysis
  print_summary
  [ "$FAILURES" -eq 0 ]
  exit "$?"
fi

if [ "$MODE" = 'done' ] && db_schema_changed; then
  run_gate 'Declarative schema whitelist check' run_adapter schema-whitelist
fi

run_gate 'Required Magento module files' check_required_files
run_gate 'Composer metadata and lifecycle' run_composer_check
run_gate 'Magento XML formatting' run_adapter xml-format
run_gate 'Magento XML schema' run_adapter xml-schema
run_gate 'Area-module boundary' run_adapter area
run_static_analysis
run_gate 'Unit tests' run_unit_tests
if [ -d "$MODULE_PATH/Test/Js" ]; then
  run_gate 'JavaScript tests' run_adapter js "$MODULE_PATH/Test/Js"
fi

if [ "$MODE" != 'check' ]; then
  audit_development_lifecycle
fi

if [ "$MODE" = 'done' ] && [ "$RUN_INTEGRATION" -eq 1 ]; then
  run_gate 'Magento integration tests' run_integration_tests
elif [ "$MODE" = 'done' ]; then
  printf '%s\n' 'INTEGRATION: SKIP; enable for DB/bootstrap/schema/index/fixture changes'
fi

print_summary
[ "$FAILURES" -eq 0 ]
exit "$?"
