#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
# Manifests created on the host must be visible before PHPCS/PHPMD reads them.
ddev mutagen sync
exec ddev exec php tools/quality/run.php gate "$@"
