# Ergonode_CategoryAttributeConsumerHistory

## Responsibility boundary

Optional inbound adapter for CategoryAttributeHistory. Owns interception of category
mapping saves (including definition creation and backfill), category registry refresh
and removal, and shared attribute snapshot refresh/removal. The neutral history
capture contract groups nested calls into one outer operation, preserving failures,
results and partial mapping changes. It supplies fresh category source metadata for
before/after snapshots. It does not fetch remote data itself, change mappings or
values, render UI, own configuration, ACL resources or database tables.

Dependencies: CategoryAttributeConsumer operation APIs and existing category metadata
provider factory; AttributeConsumer snapshot APIs and fresh metadata provider factory;
CategoryAttributeHistory capture and source-provider contracts. No reverse dependency
is required. Disabling this adapter keeps neutral mapping-save history available.

The existing category metadata provider has no read interface, so this adapter uses
its factory as the narrow existing extension boundary; it does not duplicate SQL or
provider logic. A future public category source-read contract can replace that concrete
factory when introduced by its owner. Both metadata provider instances are fresh per
snapshot because the underlying providers cache within an instance.

Global DI attaches plugins and supplies the source metadata preference. No migration
or historical backfill is performed. Source snapshot refreshes may produce successful
operations with no category changes, matching the neutral history's no-op semantics.
Tests cover fresh metadata, removal, operation grouping and persisted changes when
backfill fails, plus preservation of input, output and original exceptions.

## Shared inbound save boundary

The history plugin now wraps MappingSynchronizationInterface::execute, shared by
the runtime updaters and the neutral Admin UI save adapter. Capture includes the
save callback and subsequent backfill, so a propagated backfill failure marks the
single outer save as failed while retaining the persisted mapping in its snapshot.
Nested writer/refresh capture is grouped by the existing neutral capture service.
The old updater-only plugin target is removed. Neutral writer capture remains
available without this adapter. No dependency on an Admin UI module is introduced.
