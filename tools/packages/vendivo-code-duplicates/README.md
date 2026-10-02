# vendivo/code-duplicates

Reusable structural PHP duplicate detector powered by `nikic/php-parser`.
The package is developed in `packages/vendivo-code-duplicates` and installed in
the Vendivo backend through a Composer `path` repository.

## Responsibilities

The package owns:

- PHP source discovery;
- AST parsing and structural normalization;
- duplicate detection and scoring;
- human-readable, JSON and concise AI-oriented output;
- the generic `vendor/bin/code-duplicates` executable.

The Vendivo-specific scope is configured by the thin project wrapper in
`dev/tools/code-duplicates/detect.php`. The wrapper defaults to
`app/code/Vendivo`, while the generic binary defaults to `src`.

## Installation

The backend registers the package as a local Composer path repository:

```json
{
    "require-dev": {
        "vendivo/code-duplicates": "@dev"
    },
    "repositories": [
        {
            "type": "path",
            "url": "packages/vendivo-code-duplicates",
            "options": {
                "symlink": true
            }
        }
    ]
}
```

The package, rather than the Magento project, owns the direct dependency on
`nikic/php-parser`. A root-level Composer install exposes its binary:

```bash
composer install
vendor/bin/code-duplicates --help
```

## Usage

The generic binary scans `src` by default. Paths passed after the options
replace that default:

```bash
vendor/bin/code-duplicates
vendor/bin/code-duplicates src packages/example/src
```

The Vendivo backend wrapper scans `app/code/Vendivo` by default:

```bash
make -f dev/Makefile code-duplicates
make -f dev/Makefile code-duplicates format=ai
make -f dev/Makefile code-duplicates format=json
make -f dev/Makefile code-duplicates format=ai limit=3
```

Additional CLI arguments go through `args`:

```bash
make -f dev/Makefile code-duplicates \
    format=ai \
    limit=3 \
    args='--min-score=50 app/code/Vendivo/Blog app/code/Vendivo/BlogGraphQl'
```

## Options

| Option | Default | Meaning |
| --- | --- | --- |
| `--min-score=N` | `40` | Minimum structural score required for a finding. |
| `--min-occurrences=N` | `2` | Minimum occurrences in one duplicate group. |
| `--format=text\|json\|ai` | `text` | Human, machine-readable or concise agent output. |
| `--limit=N` | `text: 10`, `ai: 5` | Limit displayed groups; `0` shows all. JSON is never limited. |
| `--ignore-literals` | disabled | Treat string, integer and float values as placeholders. |
| `--include-tests` | disabled | Include files below `Test/` and `Tests/`. |
| `--fail-on-duplicates` | disabled | Return exit status `1` when findings exist. |
| `-h`, `--help` | — | Display CLI help. |

Formatting, comments and local variable names are always ignored. Function,
method, class and property names remain significant. Literal values remain
significant unless `--ignore-literals` is enabled.

## Output formats

### Human-readable text

The default `text` format presents the 10 highest-scoring groups and reports
the number of hidden findings:

```text
Structural duplicates: 21 group(s) in 1107 PHP files (minimum score: 40). Showing 10.

[1] Score 103, 8 statement(s), 2 occurrence(s)
  - app/code/Vendivo/Example.php:20-40
  - app/code/Vendivo/Copy.php:25-45

11 additional group(s) hidden. Use --limit=0 to show all.
```

### Concise AI output

The `ai` format defaults to five groups, uses stable labels and ends with one
next-step instruction:

```text
CODE_DUPLICATES status=found files=1107 groups=21 shown=5 min_score=40
DUPLICATE id=1 score=103 statements=8 occurrences=2
- app/code/Vendivo/Example.php:20-40
- app/code/Vendivo/Copy.php:25-45
NEXT inspect shown groups; use --format=json for the complete report or --limit=0 for all concise findings.
```

Errors occupy one line and return status `2`:

```text
CODE_DUPLICATES status=error message="Path does not exist: missing-path"
```

### JSON

JSON always contains every duplicate group, regardless of `--limit`:

```json
{
    "schemaVersion": 1,
    "status": "found",
    "files": 1107,
    "minimumScore": 40,
    "duplicateGroups": 21,
    "duplicates": [
        {
            "score": 103,
            "statementCount": 8,
            "occurrenceCount": 2,
            "occurrences": [
                {
                    "file": "app/code/Vendivo/Example.php",
                    "startLine": 20,
                    "endLine": 40
                }
            ]
        }
    ]
}
```

`schemaVersion` changes when the machine-readable contract changes
incompatibly. `status` is one of `ok`, `found` or `error`.

## Exit statuses

| Status | Meaning |
| --- | --- |
| `0` | Analysis completed, including the default case with findings. |
| `1` | Findings exist and `--fail-on-duplicates` was requested. |
| `2` | Invalid arguments, unreadable input or an analysis failure. |

The detector is report-only in Vendivo. `make -f dev/Makefile quality` runs its
tests but does not fail because existing source contains duplicates. Activating
`--fail-on-duplicates` in CI requires a separate policy or a baseline.

## Detection model

The detector:

1. parses selected PHP files into ASTs;
2. extracts contiguous executable statement sequences;
3. normalizes formatting, comments and local variable names;
4. preserves relationships between variables across a fragment;
5. fingerprints candidate windows and verifies their normalized ASTs;
6. extends matches to their longest common statement sequence;
7. suppresses findings contained by a larger reported group.

The structural score follows the public PhpStorm-facing formula:

```text
score = 2 * statements + expressions
```

Renamed variables can match, but one variable used twice does not match two
different variables. Invalid UTF-8 literal bytes are preserved through base64
normalization rather than discarded.

## Scope and limitations

Directory scans ignore `vendor`, `generated`, `var`, `node_modules` and `.git`.
`Test/` and `Tests/` are excluded unless `--include-tests` is provided.
Non-existing scopes and scopes without PHP files are errors.

The detector finds structurally identical contiguous statement sequences. It
does not attempt semantic equivalence, reordered statements, control-flow
equivalence or probabilistic AI similarity. It approximates useful IDE
duplicate detection rather than reproducing PhpStorm internals exactly.

## Development and validation

Run the regression suite from the backend root:

```bash
make -f dev/Makefile code-duplicates-test
```

It covers variable renaming and identity, literals, non-UTF-8 input, nested
result suppression, exclusions, CLI statuses and all output formats. The suite
is included in `make -f dev/Makefile quality`.

Focused static validation:

```bash
vendor/bin/phpstan analyse --no-progress --level=max \
    packages/vendivo-code-duplicates/src \
    packages/vendivo-code-duplicates/bin/code-duplicates \
    packages/vendivo-code-duplicates/tests

vendor/bin/phpcs --standard=PSR12 --extensions=php \
    packages/vendivo-code-duplicates
```
