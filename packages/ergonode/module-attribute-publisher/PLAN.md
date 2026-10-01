# Ergonode_AttributePublisher implementation plan

## Status: completed for the verified Phase 2 scope — 2026-08-07

## 1. Verify schema coverage

- Capture attribute and option mutations from target Ergonode introspection.
- Record input types and minimum safe response selections.
- Confirm all Magento-to-Ergonode type mappings.
- Decide which option custom-field operations are in the initial scope.

Decision: option custom fields are excluded from Phase 2. The target exposes
mutations, but this project has no authoritative Magento desired-state source
or deletion semantics for them.

Historical limitation: the initial snapshot omitted fields of the referenced
`Unit` output type. As of 2026-09-18, the publisher selects `unit { name symbol }`,
matching the existing inbound definition query. Unit changes are verified by fresh
reads; an unreadable requested parameter returns `UNSUPPORTED` in every mode.
The incomplete historical fixture is not evidence of live endpoint compatibility.

## 2. Define module contracts

- Add an attribute synchronization service contract.
- Add normalized desired-state and remote-state value objects.
- Add an attribute type resolver and explicit unsupported-type result.
- Define synchronization modes: create-only, update and reconcile.

Proposed runtime structure:

```text
Api/AttributeSynchronizerInterface.php
Model/Data/AttributeState.php
Model/Data/AttributeOptionState.php
Model/GraphQl/AttributeCreateMutationBuilder.php
Model/GraphQl/AttributeNameMutationBuilder.php
Model/GraphQl/AttributeMetadataMutationBuilder.php
Model/GraphQl/AttributeParametersMutationBuilder.php
Model/GraphQl/AttributeOptionMutationBuilder.php
Model/Sync/AttributeStateLoader.php
Model/Sync/AttributeSyncPlanner.php
Model/Sync/AttributeSynchronizer.php
```

## 3. Implement definition reconciliation

- Normalize Magento code, label, scope and frontend input.
- Build the correct create mutation per Ergonode attribute type.
- Build independent mutations for mutable common properties.
- Build independent mutations for type-specific parameters.
- Return `NOOP` when normalized desired and remote states match.

## 4. Implement option reconciliation

- Match options by stable code, not translated label.
- Add missing options before publishing product values.
- Update translated option names and order.
- Remove options only in explicit reconcile mode.
- Prevent removal when the remote schema reports a referenced-resource error.

## 5. Move existing runtime logic

- Move payload and gateway responsibility out of
  `Ergonode_AttributePublisherAdminUi`.
- Replace direct UI-side remote calls with this module's service contract.
- Remove duplicated REST-oriented creation logic after GraphQL parity is
  verified.

## 6. Tests

- Cover every supported attribute type.
- Cover global and local scope with multiple languages.
- Cover create, update, no-op and unsupported type plans.
- Cover option add, rename, reorder and guarded removal.
- Add schema-contract tests for every referenced mutation and input type.
- Verify idempotency by planning twice against the same desired state.

## Completion criteria

- Attribute definitions and options can be reconciled without UI code.
- No attribute-specific builder is placed in `Ergonode_Publisher`.
- A second synchronization produces an empty plan.
- The implementation writes no publisher state to Magento storage.

Completion evidence:

- all 12 create mutation families and both option families are contract-tested;
- planners cover create, update, no-op, unsupported immutable changes, option
  add/rename/order and reconcile-only removal;
- every execution stage is independently chunked and followed by a remote
  re-read before the next stage is planned;
- the Admin UI uses publisher contracts and contains no attribute REST gateway.
