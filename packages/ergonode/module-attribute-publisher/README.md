# Ergonode_AttributePublisher

This module reconciles attribute definitions supplied by product and category publishers and options to
Ergonode through GraphQL mutations.

It owns attribute-specific state loading, normalization, diff calculation and
mutation builders. Shared document composition, execution and retry behavior
come from `Ergonode_Publisher`.

## Scope

- create supported Ergonode attribute types;
- update translated names and metadata, and reject incompatible immutable scope;
- update type-specific parameters such as date format, currency, unit and rich
  text configuration;
- add, rename, reorder and remove select or multiselect options;
- leave option custom fields outside the initial contract until a source-state
  owner and reconciliation semantics are defined;
- report unsupported Magento attribute types explicitly.

The module does not publish attribute values assigned to products or
categories. Those belong to `Ergonode_ProductPublisher` and
`Ergonode_CategoryPublisher`.

## Synchronization behavior

The synchronizer loads the Magento definition and the matching Ergonode
attribute by code, calculates `CREATE`, `UPDATE`, `REMOVE` or `NOOP` operations
in memory, executes them and verifies the resulting remote state.

`create_only` is a non-destructive stateless ensure operation. When a compatible
attribute already exists, a new call resumes missing names, metadata and options
without pruning remote-only state. A type or scope collision is returned as an
explicit conflict. Each plan exposes only the next executable stage and every
successful stage is followed by a fresh remote read.

Unit verification reads `unit { name symbol }`. Missing requested parameters return
`unsupported` even in `create_only`; a successful create response alone does not
confirm readiness. A differing mutable unit is corrected and verified by a fresh read.

Full option-list ordering is emitted only in authoritative `reconcile` mode.
Update and create-only flows add or rename individual options but do not send a
replacement list whose omission semantics are not guaranteed by the schema.

Results expose a terminal status plus `present`, `absent` or `unknown` reference
verification so catalog dependencies never infer readiness from an empty result
list.

No additional mapping, queue or publisher status is persisted by this module.
Language selection is provided by `Ergonode_Language`.

Runtime attribute and option writes are owned here. The Admin UI module keeps
only Magento mapping adaptation, invocation and cache refresh behavior.

See [PLAN.md](PLAN.md) for the implementation sequence.

`AttributeStateLoaderInterface` exposes the remote definition read with publication
credentials. Outbound extensions can validate attribute constraints without
requiring inbound attribute snapshots.

## Read guarantees

Remote option pages are strictly validated and use Core's bounded cursor guard, including
multi-page cycles. An incomplete response is an error, never an empty remote definition.
A caller may supply freshly read initial state to AttributeSynchronizerInterface for the
first plan; each subsequent stage still performs a new read after mutations.
Rejected option mutations are verified together using one fresh attribute read. Existing
mismatched names are reconciled in one group while preserving untouched translations.
## Verification rounds

Attribute mutation verification shares one remote read and plan after each mutation
attempt. Publisher's optional round lifecycle discards both successful and failed read
observations before another document is sent, including retries and subsequent batches.
The domain owns attribute reads and plans; Publisher remains domain-neutral.

## Translation verification scope

The sync planner defines the language projection used by both stage reads and mutation
verification. Update and create-only read the union of requested attribute and option
languages, then compare each name set only in its own requested languages. Translations
outside that set remain untouched. An option may use a language without an attribute
name in that language. Reconcile keeps its authoritative, unfiltered reads and exact
name comparisons, including during ambiguous mutation verification.

## Batched definition reads

`AttributeBatchStateLoaderInterface::loadBatch()` loads complete write-scope definitions
and options keyed by requested code, returning null for missing definitions. It groups
at most 50 codes per GraphQL document, first reading definitions and then select/multiselect
options. Each option connection has its own cursor and guard; only unfinished connections
continue. Duplicate requested codes are read once, preserving first-occurrence order.
The language projection is shared by definitions and options; an empty language list
retains the existing all-languages behavior. Errors propagate without individual-read fallback.

`AttributeStateLoader` implements both read contracts. The single read delegates to the
same batch algorithm; parsing, pagination validation and state construction have one owner.
No state survives a call. CategoryAttributePublisherAdminUi consumes the batch contract
for mapping metadata. A set of 50 text definitions needs one request; 50 selects with
single-page options need two. Registry reads are owned and counted by the caller.

Product publication does not use a REST attribute catalog or template completeness
cache. The retired catalog, cache and invalidation plugin have been removed.
