# Ergonode_Publisher implementation plan — completed

## 1. Confirm the shared contract

- Define the immutable mutation operation contract.
- Define operation metadata without domain-specific required keys.
- Define batch document and execution result value objects.
- Define explicit empty-plan and no-op behavior.
- Keep all contracts independent from Magento entities and domain DTOs.

Proposed runtime structure:

```text
Api/
  MutationBatchBuilderInterface.php
  MutationBatchPlannerInterface.php
  MutationExecutorInterface.php
  Data/MutationOperationInterface.php
  Data/MutationResultInterface.php
Model/GraphQl/
  MutationBatchBuilder.php
  MutationBatchPlanner.php
  MutationExecutor.php
  MutationAliasGenerator.php
  MutationErrorMapper.php
Model/Data/
  MutationOperation.php
  MutationBatch.php
  MutationResult.php
  SynchronizationResult.php
```

## 2. Build GraphQL documents

- Accept operations from any domain module.
- Validate GraphQL field, alias and variable identifiers.
- Generate collision-free aliases and variable names.
- Render one mutation document for one or more operations.
- Keep all input values in the variables map.
- Permit only code-owned response selections.
- Preserve operation metadata without parsing the rendered document.
- Enforce 50 operations and 524,288 serialized variable bytes per batch.
- Split oversized plans deterministically in input order and reject a single
  operation that cannot fit an empty batch.

## 3. Execute and correlate results

- Delegate HTTP and authentication to `Ergonode_Core`.
- Correlate `data` keys and `errors[].path[0]` with operation aliases.
- Represent success, validation failure, transient failure and unresolved
  response explicitly.
- Re-read remote state after an ambiguous response instead of blindly
  repeating a mutation.

## 4. Retry policy

- Retry HTTP 429, timeouts and agreed transient 5xx responses.
- Respect `Retry-After` and configured request limits.
- Never retry schema or validation failures automatically.
- Retry only unresolved operations after a partially successful batch.
- Keep retry counters in process memory only.
- Require a verified re-read and a later batch between a prerequisite create
  or state change and dependent option, value or relationship operations.

## 5. Tests

- Unit-test single-operation and mixed-operation documents.
- Assert that input values never appear inline in the document.
- Cover alias collisions, partial errors and missing result keys.
- Cover transient retry classification and exhausted retries.
- Cover empty batches and invalid GraphQL identifiers.
- Parse generated documents with the GraphQL parser available in Magento.

## Completion criteria

- No domain mutation name exists in this module.
- Domain modules can build and execute mixed batches without string parsing.
- Partial results remain attributable to their source operations.
- Re-execution requires no persisted publisher state.
