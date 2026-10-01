# Ergonode_ProductPublisher implementation plan — implemented, gate pending

## Optional category split — 2026-09-01

This section supersedes category ownership recorded in the historical plan
below. `Ergonode_ProductPublisher` now owns only the base product lifecycle,
templates, statuses, values and product-type relations. It neither carries
category codes in `ProductStateInterface` nor builds category queries and
mutations.

`Ergonode_ProductCategoryPublisher` is the optional outbound adapter. It
decorates base product states, resolves Magento category IDs to Ergonode category
codes, reads current remote assignments and publishes the add/remove diff.
`Ergonode_ProductCategoryConsumer` independently owns inbound assignment sync.
There is no general `Ergonode_ProductCategory` module because these directions
share no runtime logic. `Ergonode_ProductCategoryAttribute` owns the shared
configuration and mapping policy for a special attribute such as a default
category. Its optional consumer and publisher adapters own the direction-specific
category-code conversion.

## Outbound contract correction — 2026-08-29

This section supersedes the remote-state reconciliation design recorded below.

- Magento state plus local product identity mappings decide create or update.
- Product publication performs no Ergonode product, category, attribute,
  template or multimedia preflight query and no post-mutation read-back.
- Ordered commands from all products are packed by the shared planner up to 50
  mutations and the configured byte limit per GraphQL request.
- Explicit create-already-exists responses continue through the update path.
  Grouped-child-already-exists responses continue with quantity update.
- Assigned-SKU creation remains a true correlated batch and persists every
  returned product ID to native Ergonode SKU mapping in one local write.
- Remote-only state is not inferred from omission. Only explicit clear, delete
  and error-driven fallback operations can remove or replace target state.

The loader, remote diff planner, multimedia preflight validator and ambiguous
read-back verifier were removed from the active module because their request
cost and inbound-style semantics do not belong to outbound publication.

## 1. Verify schema and source mapping

- Capture all product mutation fields and input types from target
  introspection.
- Confirm supported attribute value types and response selections.
- Define Magento product type mapping for simple, configurable and grouped
  products.
- Confirm localized status mapping and desired default behavior.
- Confirm create-and-update batching rules for segment-scoped API keys.

## 2. Define product state and contracts

- Identify products by SKU.
- Represent type, template code, category codes and localized statuses.
- Represent typed attribute translations without GraphQL-specific objects.
- Represent configurable bindings, variants and grouped children separately.
- Expose single-product and batch synchronization contracts.

Proposed runtime structure:

```text
Api/ProductSynchronizerInterface.php
Model/Data/ProductState.php
Model/Data/ProductAttributeValue.php
Model/Data/ProductRelationState.php
Model/GraphQl/ProductCreateMutationBuilder.php
Model/GraphQl/ProductAttributeMutationBuilder.php
Model/GraphQl/ProductCategoryMutationBuilder.php
Model/GraphQl/ProductTemplateMutationBuilder.php
Model/GraphQl/ProductStatusMutationBuilder.php
Model/GraphQl/VariableProductMutationBuilder.php
Model/GraphQl/GroupingProductMutationBuilder.php
Model/GraphQl/ProductDeleteMutationBuilder.php
Model/Sync/ProductStateLoader.php
Model/Sync/ProductSyncPlanner.php
Model/Sync/ProductSynchronizer.php
```

## 3. Port proven product behavior

- Port type-to-mutation resolution from `ergonode-automation` without its
  `gmostafa/php-graphql-client` dependency.
- Port mutation aliasing and typed response-selection concepts.
- Port changed translation add/delete semantics.
- Replace raw GraphQL input serialization with variables.
- Attach operation metadata directly instead of extracting it with regex.
- Keep every builder stateless and focused on one operation family.

## 4. Complete missing reconciliation

- Create products with template and initial categories.
- Add and remove categories, not only additions.
- Set a changed template and statuses.
- Add and delete translations by language and attribute type.
- Reconcile variable bindings and variants.
- Reconcile grouped children and quantities.
- Keep product deletion disabled outside explicit reconcile mode.

## 5. Batch execution

- Combine dependent create and update operations for one product when needed.
- Use unique aliases and variables per operation.
- Attribute partial failures to SKU, attribute code and language.
- After an ambiguous result, reload only affected products.
- Retry reconciliation safely without local cursors or an outbox.

## 6. Tests

- Cover simple, configurable and grouped products.
- Cover create, update, no-op and guarded delete plans.
- Cover add and remove category diffs.
- Cover every supported attribute type, empty values and multiple languages.
- Cover variants, bindings, grouped children and quantities.
- Cover partially successful mixed batches.
- Add schema-contract tests for every referenced mutation and input type.
- Verify that a second synchronization produces no operations.

## Completion criteria

- Product publication requires no builder from another domain module.
- Existing `ergonode-automation` behavior has been ported without the
  conflicting GraphQL client package or raw input interpolation.
- All product relationships support both addition and removal where the API
  permits it.
- Publication remains stateless and repeatable.

## Phase 4 implementation record — 2026-08-08

Implemented deterministic scope:

- [x] Pin all 27 product mutation signatures, input fields, payload selections,
  product query paths, typed translation inputs and verified-reference queries
  to the sanitized schema snapshot.
- [x] Map Magento `simple`, `configurable` and `grouped` products through an
  explicit API contract; reject unsupported Magento types.
- [x] Represent immutable product, typed value and relation state independently
  from GraphQL input objects. Require explicit localized statuses and no
  guessed default.
- [x] Read product core state, categories, all 12 value families, bindings,
  variants and grouped children with guarded pagination through the update
  credential scope. Reject a partial snapshot if the product disappears
  between pages.
- [x] Accept only pre-existing templates whose complete attribute list is
  compatible with the desired values and bindings. Verify every referenced
  multimedia path directly through the read API without depending on deferred
  publisher internals.
- [x] Build variables-only create, template, status, category, value, variable,
  grouping and guarded-delete operations with immutable SKU, attribute,
  language and related-SKU metadata.
- [x] Return only the next executable stage. Create is followed by a verified
  re-read before dependent work is planned, so stale stages cannot escape.
- [x] Add category removal, translation deletion, binding replacement,
  variant removal, grouped-child removal and grouped quantity updates.
- [x] Keep update and interruption-safe create-only non-destructive. Merge
  remote-only bindings before using the replacement mutation; reserve removals
  and product deletion for explicit reconcile mode.
- [x] Batch independent stages from multiple products through the shared batch
  planner and executor, correlate failures by SKU/attribute/language, verify
  ambiguous results by a targeted re-read and allow unrelated SKUs to finish.
- [x] Cover DTO validation, all product families and value types, add/remove
  diffs, guarded deletion, reference gates, loader normalization, mixed partial
  batches, ambiguous create verification and second-run no-op behavior.
- [x] Canonicalize GraphQL `Float` values and set-like multiselect/product
  relations before strict comparison so equivalent response representations do
  not create endless updates.

Verification status:

- [x] XML formatter, XML style check, XSD validation, JSON/XML parsing and raw
  diff whitespace checks pass for the changed module files.
- [x] The repository `module-done` gates passed through a locked agent-worktree
  lease at commit `6dfac46`: Core completed 45 tests and 214 assertions;
  ProductPublisher completed 43 tests and 532 assertions. Composer metadata,
  XML formatting/schema, area boundaries, both PHPCS rulesets, PHPMD, PHPStan
  and PHPArkitect passed for both modules.
- [ ] The opt-in live segment-scoped create/update/no-op/cleanup proof remains a
  Phase 7 sandbox gate. The deterministic tests prove create/update staging and
  mixed batching, but cannot prove target credential visibility. A readiness
  check on 2026-08-08 confirmed that integration/update flags, the GraphQL URL
  and import credential are configured, but the dedicated update credential is
  absent; no remote mutation was attempted.

Known schema limitation:

- There is no mutation that removes a localized product status. Reconcile can
  set desired statuses but cannot prune a remote-only language. Reopen when a
  refreshed schema exposes status deletion or authoritative replacement.

## Phase 4 post-implementation audit — 2026-08-08

Verdict: the schema-supported product runtime is internally staged, stateless
and independent from deferred writer modules. The first audit found and the
implementation resolved three correctness defects: verification now uses the
same update credential as mutation execution, a disappearing entity cannot be
turned into a partial remote snapshot, and GraphQL float/set representations
are canonical before idempotence comparison. The following plan gaps remain:

- [x] `P4-GAP-001` — explicit translation-deletion intent is separate from
  partial omission and empty scalar/list values. Desired state carries
  collection-level completeness, including per-attribute value completeness;
  update may execute an explicit language clear, while reconcile prunes an
  omission only for an authoritative collection. The contract is type-neutral,
  so all 12 value families use the same guarded delete mutation path.
- [x] `P4-GAP-002` — acknowledge the unavoidable same-type create collision.
  Stateless `create_only` resumes desired fields on any existing product of the
  same type; it cannot prove that the product is a partial create from this
  workflow. Before Admin UI or external single-product use, decide whether a
  caller-supplied ownership/precondition token or a stricter conflict policy is
  required. The family decision is to retain stateless same-type ensure for
  batch publication and not expose a single-product/Admin UI create contract
  requiring origin proof until it supplies an explicit precondition token.
- [ ] `P4-GAP-003` — source ownership remains open in Phase 5. Runtime verifies
  deferred template/media references; orchestration validates categories and
  options and now consumes terminal dependency layers for related products,
  variants and grouped children. The remaining work is the authoritative
  Magento source plus operation-granular continuation for attributes,
  categories and media.
- [ ] `P4-GAP-004` — prove the new update-scope query contract against a real
  segment-scoped credential. Unit coverage can prove credential routing, not
  whether the target grants read queries and exposes a just-created product to
  that key with sufficient consistency. The local readiness check is blocked
  specifically by the missing dedicated update API key; it did not expose
  secret values and did not execute a remote mutation.
- [x] `P4-GAP-005` — the upstream runtime prerequisites are closed:
  `P2-GAP-001`, `P2-GAP-002`, `P2-GAP-005` and the runtime portion of
  `P2-GAP-006`. The remaining exact schema evidence from `P2-GAP-006` is
  explicitly routed to the release-evidence package rather than blocking the
  product runtime contract.
- [x] `P4-GAP-006` — `module-done` passed for both `Ergonode_Core` and
  `Ergonode_ProductPublisher` through locked worktree snapshot
  `90e464baa08885e961109f71d59950d24b47e59890822a84138e0de3f8208bfb`.
  All mandatory module gates passed; Core reported four non-blocking PHPUnit
  notices, while ProductPublisher completed without notices.
- [x] `P4-GAP-007` — every planned capacity batch is filtered and rebuilt
  immediately before dispatch. A terminal SKU from an earlier batch is absent
  from later batches while independent SKU operations continue.
- [x] `P4-GAP-008` — product-scoped `LocalizedException` failures from
  reference validation, state loading or planning become structured failed SKU
  results. Typed `GraphQlRequestException` authorization, rate-limit and
  transport failures remain global and abort the call explicitly.
- [x] `P4-GAP-009` — loader and planner exceptions during ambiguous read-back
  are translated to `MutationVerificationException`; the shared executor owns
  unresolved mutation classification and retry behavior.
- [x] `P4-GAP-010` — product connections use a cursor guard that tracks every
  cursor and limits each 100-entry connection to 100 pages. Repeated,
  multi-cursor cycles and over-limit sequences are covered. Since the
  2026-08-29 source-contract correction, template structure is read from the
  local normalized cache and is no longer queried by this module.
- [x] `P4-GAP-011` — schema tests compare exact mutation input maps and exact
  payload maps, then validate the input type, required keys and response path of
  every operation emitted by `ProductMutationFactory`.
- [x] `P4-GAP-012` — `twoWayRelation` is a one-shot directive emitted only
  with a changed product-relation translation. It is not independently
  reconciled or considered by no-op planning because the read schema does not
  expose it. Changing a relation value is the precondition for sending it again.

Product batch hardening record (2026-08-08):

- Numeric native SKUs are values only; batch result contracts return a list and
  internal maps use prefixed string keys so PHP cannot coerce a numeric SKU to
  an integer key.
- Capacity-split terminal filtering, per-SKU read isolation, global GraphQL
  failure propagation, verifier exception translation, cursor cycles/page
  bounds, exact schema/factory contracts and one-shot relation semantics have
  deterministic regression coverage.
- The ProductPublisher unit suite passed with 43 tests and 532 assertions and
  no PHPUnit notices. Project PHPCS, Magento PHPCS, PHPStan, PHPMD,
  PHPArkitect, Composer metadata, XML formatting/schema and area boundaries
  passed. The aggregate leased `module-done` is closed by `P4-GAP-006`.

Deliberate structure variance: the proposed list of one builder class per
operation family was collapsed into one stateless `ProductMutationFactory`,
matching the Attribute and Category publisher pattern. It owns only conversion
from typed domain state to immutable operations; loaders, planning, reference
policy and execution remain separate, so this is not currently an SRP gap.
