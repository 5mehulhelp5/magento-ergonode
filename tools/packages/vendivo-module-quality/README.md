# Vendivo Module Quality

`vendivo/module-quality` is a Composer-installed orchestration tool for
module-scoped quality gates. It owns deterministic Git manifests, analysis
profiles, compact diagnostics and reports. Project-specific commands remain in
an executable gate-runner adapter.

## Installation

Add the package as a development dependency. A monorepo can use a Composer
`path` repository; a shared package can use a VCS or Composer repository.

```json
{
  "require-dev": {
    "vendivo/module-quality": "@dev"
  },
  "repositories": [
    {
      "type": "path",
      "url": "packages/vendivo-module-quality",
      "options": {"symlink": true}
    }
  ]
}
```

The package targets PHP 8.3+ projects. Its host-side CLI requires Bash, Git,
Perl and standard Unix text utilities; analyzed PHP runs through the project
adapter. It does not require a Composer plugin or register install/update
hooks.

## CLI

```bash
vendor/bin/vendivo-module-quality static Vendor_Blog --scope=changed \
  --gate-runner=tools/module-quality-gate
vendor/bin/vendivo-module-quality check Vendor_Blog \
  --base-commit=HEAD~1 --gate-runner=tools/module-quality-gate
vendor/bin/vendivo-module-quality done Vendor_Blog --integration \
  --gate-runner=tools/module-quality-gate
vendor/bin/vendivo-module-quality manifest Vendor_Blog --base-commit=HEAD~1
```

`check` and `static --scope=changed` limit PHPCS and PHPMD to existing changed
files. PHPStan and PHPArkitect always receive the full module. `audit`, `done`
and `static --scope=full` run all static analyzers against the full module.
`check` and changed static analysis fail fast; the full profiles collect all
gate results.

The changed-file manifest is the sorted union of:

- committed changes between `--base-commit` and `HEAD`;
- staged changes;
- unstaged changes;
- untracked files.

`--source-changes-file` replaces Git discovery when an external orchestrator
already has an authoritative manifest. Paths must be project-relative or
absolute paths below `--root`. Missing or invalid Git context makes a changed
analysis fall back to the full module.
For a Composer package outside `app/code`, use `--module-path=packages/vendor/module-name`
to scope manifests and gates to its actual project-relative directory. The
backend adapter resolves packaged Ergonode modules automatically.

## Gate-runner contract

The adapter is called as:

```text
gate-runner GATE Vendor_Module module/path [arguments...]
```

It must preserve the child exit code. Supported calls are:

| Gate | Extra arguments |
|---|---|
| `schema-whitelist` | none |
| `composer` | none |
| `xml-format` | none |
| `xml-schema` | none |
| `area` | none |
| `phpcs` | `full` or `files MANIFEST` |
| `phpmd` | `full CACHE_FILE` or `files MANIFEST CACHE_FILE` |
| `phpstan` | none |
| `arkitect` | none |
| `unit` | test directory |
| `integration` | test directory |

The package deliberately does not know about DDEV, Make targets, lock files,
Magento bootstrap commands, baselines or repository layouts beyond the
configurable module root. Those belong to the consuming repository's adapter.

## Reports

`--report-dir` contains complete gate logs, compact JSON summaries and
cross-run diagnostic state. `--manifest-dir` should point to a project path
visible to tools running in a container. PHPMD's cache defaults to
`var/phpmd.result-cache.php`; PHPCS caching is intentionally outside the
package's policy.

Default output is optimized for agents: one line per successful or skipped
gate, at most eight diagnostics per failure, one artifact line and one final
summary. Repeated failures show at most three unchanged diagnostics. Full gate
output is written to logs; use `--verbose` only for interactive diagnosis.

## Development

```bash
bash packages/vendivo-module-quality/tests/run.sh
```
