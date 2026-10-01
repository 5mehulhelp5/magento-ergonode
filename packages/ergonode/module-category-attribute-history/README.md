# Ergonode_CategoryAttributeHistory

## Responsibility boundary

Direction-neutral, immutable category attribute mapping history. Owns operation
capture, full state snapshots, changes, persistence and read contracts. Depends
only on CategoryAttribute among project modules. It does not download attributes,
create definitions, write category values, restore mappings or render Admin UI.

Owns `ergonode_category_attribute_history_operation` and
`Ergonode_CategoryAttributeHistory::view`. Only this module's uninstall removes
that history table. Removing the consumer or viewer preserves recorded states.
History starts when enabled; there is no backfill, migration of existing data,
option/value history.

## Contracts and capture

`HistoryOperationCaptureInterface` groups nested operations. A plugin observes
the neutral `AttributeMappingWriterInterface::save` boundary. Validation alone
does not create records. Optional integrations may wrap their own operations;
the outer operation determines its code and outcome. Successful no-ops and
failures with observable partial changes are retained. Capture/storage errors
are logged without replacing the underlying result or exception.

`HistoryQueryInterface` returns keyset-paginated operations and their saved
states. Reads do not join live attribute tables. Labels, type, scope, visibility
and connections in stored states remain historical values.

`SourceSnapshotProviderInterface` allows an optional source adapter to supply
fresh existing metadata without a dependency on that adapter. The default is
empty; neutral mappings still appear with placeholders for unavailable source
attributes. The snapshot always reads fresh Magento metadata through
CategoryAttribute and preserves drafts and missing targets.

Each new attribute snapshot includes `is_draft`, distinguishing a saved one-sided
mapping from an attribute outside the mapping workspace. Adding or removing a
draft records `draft_added` or `draft_removed`; completing or disconnecting a pair
uses the existing connection actions. Re-saving an unchanged draft remains a no-op.
Older immutable snapshots lack this field and cannot reveal past draft changes;
they are not rewritten or inferred from current mappings.

`HistoryActorProviderInterface` lets Admin UI supply authenticated actor context.
Without an area adapter actor fields are null; execution origin is still recorded.

Tests cover immutable reads, pagination, neutral interception, fresh metadata,
nested operations, partial failure and preservation of original exceptions.

## Capture and retention configuration

Owns `ergonode_category_attributes/history/enabled`, `cleanup_enabled`, `retention_days` and `schedule`
under the same prefix. Capture and cleanup default to enabled; retention defaults
to 30 days and the independent cleanup cron defaults to `15 2 * * *` in Magento's
configured timezone. The matching HistoryAdminUi module presents these settings.
Disabling capture bypasses snapshot reads and writes in all areas; operations and
existing history queries remain available. Cleanup is independent of capture.

Cleanup permanently deletes complete operations finished strictly before the UTC
cutoff (now minus the configured number of 24-hour days), including existing old
records on its first scheduled run. Invalid retention values skip deletion.
Deletion is batched and repeatable. It never changes mappings or catalog data.
There is no migration or backfill. Deleted history can only be recovered from a
backup; disable cleanup to retain all records.

The retention cron intentionally remains independent of Ergonode connection
availability and operating mode. Tests exercise its entrypoint, invalid/disabled
retention, database failures and repeated execution. The project cron integration
contract verifies deletion of old fixture operations with the connection disabled.
