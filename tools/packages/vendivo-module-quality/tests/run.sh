#!/usr/bin/env bash
set -euo pipefail

PACKAGE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BIN="$PACKAGE_DIR/bin/vendivo-module-quality"
TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/vendivo-module-quality.XXXXXX")"
PROJECT_DIR="$TEMP_DIR/project"
MODULE='Acme_Blog'
MODULE_PATH='app/code/Acme/Blog'
GATE_LOG="$TEMP_DIR/gates.log"
GATE_RUNNER="$TEMP_DIR/gate-runner"
OUTPUT="$TEMP_DIR/output.log"
trap 'rm -rf "$TEMP_DIR"' EXIT

mkdir -p \
  "$PROJECT_DIR/$MODULE_PATH/etc" \
  "$PROJECT_DIR/$MODULE_PATH/Model" \
  "$PROJECT_DIR/$MODULE_PATH/Test/Unit" \
  "$PROJECT_DIR/$MODULE_PATH/Test/Integration" \
  "$PROJECT_DIR/$MODULE_PATH/view/frontend/templates"
printf '%s\n' '<?php' > "$PROJECT_DIR/$MODULE_PATH/registration.php"
printf '%s\n' '<config><module name="Acme_Blog"/></config>' > "$PROJECT_DIR/$MODULE_PATH/etc/module.xml"
printf '%s\n' '<schema/>' > "$PROJECT_DIR/$MODULE_PATH/etc/db_schema.xml"
printf '%s\n' '{}' > "$PROJECT_DIR/$MODULE_PATH/composer.json"
printf '%s\n' '<?php // committed' > "$PROJECT_DIR/$MODULE_PATH/Model/Committed.php"
printf '%s\n' '<?php // staged' > "$PROJECT_DIR/$MODULE_PATH/Model/Staged.php"
printf '%s\n' '<?php // deleted' > "$PROJECT_DIR/$MODULE_PATH/Model/Deleted.php"
printf '%s\n' '<p>base</p>' > "$PROJECT_DIR/$MODULE_PATH/view/frontend/templates/unstaged.phtml"
printf '%s\n' '<?php' > "$PROJECT_DIR/$MODULE_PATH/Test/Unit/BlogTest.php"
printf '%s\n' '<?php' > "$PROJECT_DIR/$MODULE_PATH/Test/Integration/BlogTest.php"

git -C "$PROJECT_DIR" init -q
git -C "$PROJECT_DIR" config user.email quality@example.com
git -C "$PROJECT_DIR" config user.name 'Module Quality Test'
git -C "$PROJECT_DIR" add -- "$MODULE_PATH"
git -C "$PROJECT_DIR" commit -qm base
BASE_COMMIT="$(git -C "$PROJECT_DIR" rev-parse HEAD)"

printf '%s\n' '<?php // committed after base' > "$PROJECT_DIR/$MODULE_PATH/Model/Committed.php"
git -C "$PROJECT_DIR" add -- "$MODULE_PATH/Model/Committed.php"
git -C "$PROJECT_DIR" commit -qm committed-change
printf '%s\n' '<?php // staged after base' > "$PROJECT_DIR/$MODULE_PATH/Model/Staged.php"
git -C "$PROJECT_DIR" add -- "$MODULE_PATH/Model/Staged.php"
printf '%s\n' '<p>unstaged after base</p>' > "$PROJECT_DIR/$MODULE_PATH/view/frontend/templates/unstaged.phtml"
printf '%s\n' 'type Query { blog: String }' > "$PROJECT_DIR/$MODULE_PATH/etc/schema.graphqls"
printf '%s\n' '<schema><table name="blog"/></schema>' > "$PROJECT_DIR/$MODULE_PATH/etc/db_schema.xml"
printf '%s\n' '// changed test' >> "$PROJECT_DIR/$MODULE_PATH/Test/Unit/BlogTest.php"
printf '%s\n' 'notes' > "$PROJECT_DIR/$MODULE_PATH/notes.txt"
rm "$PROJECT_DIR/$MODULE_PATH/Model/Deleted.php"

"$BIN" manifest "$MODULE" --root="$PROJECT_DIR" --base-commit="$BASE_COMMIT" > "$OUTPUT"
cat > "$TEMP_DIR/expected-manifest" <<EOF
$MODULE_PATH/Model/Committed.php
$MODULE_PATH/Model/Deleted.php
$MODULE_PATH/Model/Staged.php
$MODULE_PATH/Test/Unit/BlogTest.php
$MODULE_PATH/etc/db_schema.xml
$MODULE_PATH/etc/schema.graphqls
$MODULE_PATH/notes.txt
$MODULE_PATH/view/frontend/templates/unstaged.phtml
EOF
diff -u "$TEMP_DIR/expected-manifest" "$OUTPUT"

printf '%s\n' \
  "$PROJECT_DIR/$MODULE_PATH/Model/Staged.php" \
  'app/code/Other/Module/Model/Ignore.php' \
  "./$MODULE_PATH/etc/module.xml" \
  > "$TEMP_DIR/source-changes"
"$BIN" manifest "$MODULE" \
  --root="$PROJECT_DIR" \
  --source-changes-file="$TEMP_DIR/source-changes" > "$OUTPUT"
printf '%s\n' \
  "$MODULE_PATH/Model/Staged.php" \
  "$MODULE_PATH/etc/module.xml" \
  > "$TEMP_DIR/expected-source-manifest"
diff -u "$TEMP_DIR/expected-source-manifest" "$OUTPUT"

cat > "$GATE_RUNNER" <<'GATE'
#!/usr/bin/env bash
set -euo pipefail
gate="$1"
shift
printf '%s %s\n' "$gate" "$*" >> "${GATE_LOG:?}"
if [ "$gate" = composer ]; then
  printf 'OK [MODULE-COMPOSER] checked=1 errors=0 warnings=%s\n' "${COMPOSER_WARNINGS:-0}"
fi
if [ "$gate" = "${FAIL_GATE:-}" ]; then
  printf '%s\n' 'app/code/Acme/Blog/Model/Committed.php:42: fixture failure [fixture.error]' >&2
  exit 1
fi
GATE
chmod +x "$GATE_RUNNER"

PACKAGED_PATH='packages/acme/module-blog'
mkdir -p "$PROJECT_DIR/packages/acme"
cp -R "$PROJECT_DIR/$MODULE_PATH" "$PROJECT_DIR/$PACKAGED_PATH"
"$BIN" manifest "$MODULE" --root="$PROJECT_DIR" --base-commit="$BASE_COMMIT" \
  --module-path="$PACKAGED_PATH" > "$OUTPUT"
grep -Fxq "$PACKAGED_PATH/Model/Committed.php" "$OUTPUT"
: > "$GATE_LOG"
GATE_LOG="$GATE_LOG" "$BIN" static "$MODULE" --root="$PROJECT_DIR" \
  --module-path="$PACKAGED_PATH" --scope=full --gate-runner="$GATE_RUNNER" \
  --report-dir=var/packaged-reports > "$OUTPUT"
grep -Fxq "phpcs $MODULE $PACKAGED_PATH full" "$GATE_LOG"
grep -Fxq 'SUMMARY: PASS module=Acme_Blog mode=static scope=full gates=4' "$OUTPUT"

: > "$GATE_LOG"
GATE_LOG="$GATE_LOG" "$BIN" static "$MODULE" \
  --root="$PROJECT_DIR" \
  --base-commit="$BASE_COMMIT" \
  --gate-runner="$GATE_RUNNER" \
  --report-dir=var/reports \
  --manifest-dir=var/manifests > "$OUTPUT"
grep -Fxq '[1] Vendivo PHPCS (changed files) ... PASS' "$OUTPUT"
grep -Fxq '[2] PHPMD (changed files) ... PASS' "$OUTPUT"
grep -Fxq 'SUMMARY: PASS module=Acme_Blog mode=static scope=changed gates=4' "$OUTPUT"
if [ "$(wc -l < "$OUTPUT" | tr -d ' ')" -gt 5 ]; then
  printf '%s\n' 'Successful changed static output is not compact.' >&2
  exit 1
fi
test "$(wc -l < "$GATE_LOG" | tr -d ' ')" -eq 4
for static_gate in phpcs phpmd phpstan arkitect; do
  if [ "$(grep -c "^$static_gate " "$GATE_LOG" || true)" -ne 1 ]; then
    printf 'Static profile must run %s exactly once.\n' "$static_gate" >&2
    exit 1
  fi
done
grep -Fq "phpcs $MODULE $MODULE_PATH files var/manifests/module-quality-$MODULE-static-phpcs-files.txt" "$GATE_LOG"
grep -Fq "phpmd $MODULE $MODULE_PATH files var/manifests/module-quality-$MODULE-static-phpmd-files.txt var/phpmd.result-cache.php" "$GATE_LOG"
grep -Fxq "$MODULE_PATH/Test/Unit/BlogTest.php" \
  "$PROJECT_DIR/var/manifests/module-quality-$MODULE-static-phpcs-files.txt"
grep -Fxq "$MODULE_PATH/etc/schema.graphqls" \
  "$PROJECT_DIR/var/manifests/module-quality-$MODULE-static-phpcs-files.txt"
if grep -Fq 'notes.txt' "$PROJECT_DIR/var/manifests/module-quality-$MODULE-static-phpcs-files.txt"; then
  printf '%s\n' 'Unsupported extensions must not be sent to PHPCS.' >&2
  exit 1
fi
grep -Fxq "$MODULE_PATH/Model/Committed.php" \
  "$PROJECT_DIR/var/manifests/module-quality-$MODULE-static-phpmd-files.txt"
if grep -Eq 'Deleted\.php|/Test/' "$PROJECT_DIR/var/manifests/module-quality-$MODULE-static-phpmd-files.txt"; then
  printf '%s\n' 'Deleted files and tests must not be sent to PHPMD.' >&2
  exit 1
fi

: > "$GATE_LOG"
GATE_LOG="$GATE_LOG" "$BIN" static "$MODULE" \
  --root="$PROJECT_DIR" \
  --gate-runner="$GATE_RUNNER" \
  --report-dir=var/fallback-reports > "$OUTPUT" 2>&1
grep -Fq 'WARN: changed-file manifest unavailable; analyzing the full module.' "$OUTPUT"
grep -Fxq "phpcs $MODULE $MODULE_PATH full" "$GATE_LOG"
grep -Fxq "phpmd $MODULE $MODULE_PATH full var/phpmd.result-cache.php" "$GATE_LOG"

: > "$GATE_LOG"
set +e
GATE_LOG="$GATE_LOG" FAIL_GATE=phpstan "$BIN" static "$MODULE" \
  --root="$PROJECT_DIR" \
  --scope=full \
  --gate-runner="$GATE_RUNNER" \
  --report-dir=var/reports > "$OUTPUT" 2>&1
full_result="$?"
set -e
test "$full_result" -eq 1
grep -q '^arkitect ' "$GATE_LOG"
grep -q '^DIAGNOSTICS: unique=1 new=1 unchanged=0 resolved=0 shown=1 omitted=0$' "$OUTPUT"
grep -q '^Artifacts: summary=.* log=.*$' "$OUTPUT"
grep -q '^SUMMARY: FAIL module=Acme_Blog mode=static scope=full gates=4 failures=1 failed=PHPStan$' "$OUTPUT"
if [ "$(wc -l < "$OUTPUT" | tr -d ' ')" -gt 10 ]; then
  printf '%s\n' 'Failed full static output is not compact.' >&2
  exit 1
fi

: > "$GATE_LOG"
set +e
GATE_LOG="$GATE_LOG" FAIL_GATE=composer "$BIN" check "$MODULE" \
  --root="$PROJECT_DIR" \
  --base-commit="$BASE_COMMIT" \
  --gate-runner="$GATE_RUNNER" \
  --report-dir=var/check-reports > "$OUTPUT" 2>&1
check_result="$?"
set -e
test "$check_result" -eq 1
if grep -q '^xml-format ' "$GATE_LOG"; then
  printf '%s\n' 'Check mode must stop after the first failing gate.' >&2
  exit 1
fi

: > "$GATE_LOG"
GATE_LOG="$GATE_LOG" "$BIN" done "$MODULE" \
  --root="$PROJECT_DIR" \
  --base-commit="$BASE_COMMIT" \
  --integration \
  --gate-runner="$GATE_RUNNER" \
  --report-dir=var/done-reports > "$OUTPUT"
test "$(sed -n '1s/ .*//p' "$GATE_LOG")" = 'schema-whitelist'
grep -q '^unit .*Test/Unit$' "$GATE_LOG"
grep -q '^integration .*Test/Integration$' "$GATE_LOG"
for done_gate in phpcs phpmd phpstan arkitect; do
  if [ "$(grep -c "^$done_gate " "$GATE_LOG" || true)" -ne 1 ]; then
    printf 'Done profile must run %s exactly once.\n' "$done_gate" >&2
    exit 1
  fi
done
grep -Fq 'SUMMARY: PASS module=Acme_Blog mode=done' "$OUTPUT"

: > "$GATE_LOG"
GATE_LOG="$GATE_LOG" COMPOSER_WARNINGS=2 "$BIN" done "$MODULE" \
  --root="$PROJECT_DIR" \
  --base-commit="$BASE_COMMIT" \
  --gate-runner="$GATE_RUNNER" \
  --report-dir=var/warning-reports > "$OUTPUT"
grep -Eq '^\[[0-9]+\] Composer metadata and lifecycle \.\.\. WARN warnings=2$' "$OUTPUT"
grep -Eq '^SUMMARY: PASS module=Acme_Blog mode=done gates=[0-9]+ advisories=2$' "$OUTPUT"

rm "$PROJECT_DIR/$MODULE_PATH/Test/Unit/BlogTest.php"
: > "$GATE_LOG"
GATE_LOG="$GATE_LOG" "$BIN" done "$MODULE" \
  --root="$PROJECT_DIR" \
  --base-commit="$BASE_COMMIT" \
  --gate-runner="$GATE_RUNNER" \
  --report-dir=var/skip-reports > "$OUTPUT"
grep -Eq '^\[[0-9]+\] Unit tests \.\.\. SKIP no-tests$' "$OUTPUT"
grep -Fq 'INTEGRATION: SKIP;' "$OUTPUT"

if "$BIN" static "$MODULE" --scope=invalid > "$OUTPUT" 2>&1; then
  printf '%s\n' 'Invalid static scope was accepted.' >&2
  exit 1
fi
grep -Fq 'Scope must be changed or full' "$OUTPUT"

rm "$PROJECT_DIR/$MODULE_PATH/composer.json"
: > "$GATE_LOG"
set +e
GATE_LOG="$GATE_LOG" "$BIN" audit "$MODULE" \
  --root="$PROJECT_DIR" \
  --gate-runner="$GATE_RUNNER" \
  --report-dir=var/missing-composer-reports > "$OUTPUT" 2>&1
missing_composer_result="$?"
set -e
test "$missing_composer_result" -eq 1
grep -Fq '[2] Composer metadata and lifecycle ... SKIP missing-composer-json' "$OUTPUT"
if grep -q '^composer ' "$GATE_LOG"; then
  printf '%s\n' 'Missing composer.json must not be reported again by the metadata gate.' >&2
  exit 1
fi
grep -Fq 'failures=1' "$OUTPUT"

"$BIN" --help > /dev/null 2>&1
printf '%s\n' 'Vendivo module-quality package tests passed.'
