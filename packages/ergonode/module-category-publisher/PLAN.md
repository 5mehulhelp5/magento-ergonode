# Ergonode_CategoryPublisher — archived implementation plan

This document preserves the historical Phase 2 plan from 2026-08-07. It is not
the current module boundary or an instruction for further implementation.
Use [README.md](README.md) and
[CategoryPublisherAdminUi/README.md](../module-category-publisher-admin-ui/README.md)
for the current responsibilities and runtime contracts.

CategoryPublisher owns stateless GraphQL category entity/name synchronization.
CategoryAttributePublisher owns attribute contributions. CategoryPublisherAdminUi
continues to own manual REST tree creation and hierarchy writes because the
verified GraphQL schema does not provide those mutations. The historical migration
directions and completion claims below do not describe the current REST tree runtime.

## Historical status: completed for the verified Phase 2 scope — 2026-08-07

## 1. Verify schema coverage

- Capture category definition and value mutations from target introspection.
- Record input types for every supported category attribute value type.
- Confirm create, rename, delete and allowed-attribute operations.
- Define minimum response selections used for verification.

Schema finding: `categoryAttributeAddAttribute` and
`categoryAttributeRemoveAttribute` mutate one global category-attribute
registry. They are not scoped by category code. Entity synchronization safely
adds prerequisites, but never derives global removals from one `CategoryStateDto`.
Registry pruning requires a separate registry-wide desired-state contract.

## 2. Define normalized category state

- Use a stable category code as remote identity.
- Represent translated names independently from Magento store identifiers.
- Represent allowed attribute codes and typed translated values.
- Keep tree parent, position and tree code outside this state object.

Proposed runtime structure:

```text
Api/CategorySynchronizerInterface.php
Model/Data/CategoryStateDto.php
Model/Data/CategoryAttributeValue.php
Model/GraphQl/CategoryCreateMutationBuilder.php
Model/GraphQl/CategoryNameMutationBuilder.php
Model/GraphQl/CategoryAttributeMutationBuilder.php
Model/GraphQl/CategoryAttributeValueMutationBuilder.php
Model/GraphQl/CategoryDeleteMutationBuilder.php
Model/Sync/CategoryStateLoader.php
Model/Sync/CategorySyncPlanner.php
Model/Sync/CategorySynchronizer.php
```

## 3. Implement reconciliation

- Create a category when the code is absent remotely.
- Update only changed language names.
- Add allowed attributes before publishing their values.
- Add or delete attribute translations by language and type.
- Remove category value translations or the category only in explicit
  reconcile mode. Do not remove entries from the global category-attribute
  registry from an entity-scoped desired state.
- Return a verified category reference for tree synchronization.

## 4. Move existing runtime logic

- Move category payload and remote gateway responsibilities out of
  `Ergonode_CategoryPublisherAdminUi`.
- Keep mapping UI plugins and presentation in the Admin UI module.
- Remove REST-specific paths after GraphQL operations are proven equivalent.

## 5. Tests

- Cover create, rename, no-op and optional delete plans.
- Cover every supported category attribute value type.
- Cover multiple languages and translation removal.
- Assert that hierarchy information never enters category mutations.
- Add schema-contract tests for referenced mutations and input types.
- Verify idempotent re-planning after successful synchronization.

## Completion criteria

- Category entities can be synchronized without tree or Admin UI code.
- Category operations are identified and verified by code.
- A second synchronization produces an empty plan.
- The implementation creates no publisher persistence.

Completion evidence:

- create, rename, optional delete and all 12 typed value families are
  contract-tested;
- create-only continues through attribute and value stages for an entity
  created in the same run;
- hierarchy fields never enter category mutation inputs;
- legacy REST category/tree writers were removed and tree-bound Admin UI
  creation is guarded by the schema gate.
