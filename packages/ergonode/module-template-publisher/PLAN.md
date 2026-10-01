# Ergonode_TemplatePublisher implementation plan

## Target schema gate result: blocked for structure

Authenticated introspection captured in `Ergonode_Publisher` exposes
`templateCreate`, `templateSetName` and `templateDelete`, but no section write
operations and no template structure attach, detach or ordering mutations.
Runtime implementation remains paused because an attribute-set structure cannot
be reconciled through the target GraphQL schema; no REST fallback is assumed.

Checkpoint status: `DEFERRED_BY_SCHEMA_GATE` (2026-08-07).

The sanitized contract snapshot contains exactly `templateCreate`,
`templateSetName` and `templateDelete` for templates and no section mutations.
`Test/Unit/Contract/SchemaGateTest.php` pins that negative capability decision;
any added template or section mutation requires reviewing and explicitly
reopening this plan.

While deferred, this module exposes no structure synchronizer, builder or
product DTO. It may create an empty template through the verified
`templateCreate` mutation for manual Admin UI mapping. Product publication must
not depend on template-structure internals. Phase 4 owns the decision whether it
may consume an independently verified, pre-existing template code as normalized
desired state.

## 1. Mandatory schema gate

- Inspect target introspection for template and section create/update/delete
  mutations.
- Confirm mutations for attaching, detaching and ordering sections and
  attributes.
- Record exact input types and whether operations identify resources by code.
- Confirm whether unsectioned template attributes are supported.
- Stop implementation if the required structure cannot be written through
  GraphQL; do not add an implicit fallback transport.

## 2. Define normalized structure

- Map a Magento attribute set to a stable template code.
- Map Magento attribute groups to stable section codes.
- Represent direct attributes and section attributes separately.
- Track ordering as part of desired state only when the API supports it.
- Distinguish publisher-managed and unmanaged remote structure in the plan.

Proposed runtime structure:

```text
Api/TemplateSynchronizerInterface.php
Model/Data/TemplateState.php
Model/Data/TemplateSectionState.php
Model/GraphQl/SectionMutationBuilder.php
Model/GraphQl/TemplateMutationBuilder.php
Model/GraphQl/TemplateSectionMutationBuilder.php
Model/GraphQl/TemplateAttributeMutationBuilder.php
Model/Sync/TemplateStateLoader.php
Model/Sync/TemplateSyncPlanner.php
Model/Sync/TemplateSynchronizer.php
```

## 3. Build dependency-aware plans

- Ensure required attributes before referencing them.
- Create sections before attaching them to templates.
- Create a template before adding structure.
- Add missing structure before applying order.
- Detach obsolete structure before optional destructive deletion.
- Keep deletes disabled outside explicit reconcile mode.

## 4. Implement reconciliation

- Support create, update and no-op for section definitions.
- Support create, update and no-op for templates.
- Reconcile direct template attributes.
- Reconcile sections and their attributes.
- Preserve unmanaged structure in update mode.
- Re-read the complete template after execution and verify order and contents.

## 5. Tests

- Cover empty, direct-only, section-only and mixed templates.
- Cover moved attributes and changed section order.
- Cover missing attribute prerequisites.
- Cover preservation versus removal of unmanaged structure.
- Add schema-contract tests for every template and section mutation.
- Verify that a second synchronization produces no operations.

## Completion criteria

- Template and section structure can be fully reconciled using confirmed
  GraphQL mutations.
- Attribute creation remains owned by `Ergonode_AttributePublisher`.
- Publisher-managed ownership does not require new Magento persistence.

These runtime criteria remain open. The Phase 3 catalog checkpoint is complete
because the blocked status and reopening trigger are verified, not because
template reconciliation has been implemented.
