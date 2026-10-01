# Ergonode_ProductAttributeHistory

[Product module responsibility map](../../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Direction-neutral, immutable product attribute and option mapping history: fresh state snapshots,
change comparison, grouped operation capture, persistence and queries.

Reads ProductAttribute's neutral mapping state and AttributeConsumer's metadata. It does
not require ProductAttributeConsumer or restore historical data into live mappings. The
optional ProductAttributeConsumerHistory bridge observes inbound boundaries; the AdminUi
module supplies the viewer and authenticated actor.

## Data, configuration and removal

Owns `ergonode_product_attribute_history_operation` and
`Ergonode_ProductAttributeHistory::view`. Owns its capture and retention configuration (see below). Only this module's
uninstall removes that history table and permission; removing consumer/bridge/UI
preserves the records.

## Dependencies and extension points

`HistoryOperationCaptureInterface`, `HistoryQueryInterface` and
`HistoryActorProviderInterface` separate capture, read access and actor presentation.
This is attribute mapping history, not product value history or
publication-job history.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-attribute-consumer](../../../../app/code/Ergonode/AttributeConsumer/README.md)
- [ergonode/module-product-attribute](../../../../app/code/Ergonode/ProductAttribute/README.md)

Ownership and wiring: [etc/di.xml](etc/di.xml), [etc/db_schema.xml](etc/db_schema.xml), [Setup/Uninstall.php](Setup/Uninstall.php).

## Operational details

Owns grouped product attribute mapping operations, immutable states and changes
with before/after metadata. `HistoryQueryInterface` serves the history viewer;
`HistoryActorProviderInterface` lets the Admin UI supply the authenticated actor.
The internal writer port separates operation capture from SQL persistence.

Base capture observes neutral manual/additive mapping saves and attribute snapshot
refresh/removal, option mapping saves and option snapshot refresh/removal. Optional modules capture their own operation boundaries through
`HistoryOperationCaptureInterface`. Nested calls produce one
operation. Both successful no-op operations and failed operations with observable
partial changes are recorded. History failures are logged and do not replace the
underlying operation's result or exception.

The module owns `ergonode_product_attribute_history_operation` and the `view` ACL.
Each row stores the complete state after an operation and reversible changes.
Labels, types, scope, mapping and visibility are historical values. Reads never
join current attribute data. Snapshots trade storage for independent reads and
avoid replay/retention gaps; their size grows with the captured attribute metadata.
Automatic retention is configurable as described below.

History starts when enabled. It captures fresh states without inferred backfill.

Dependencies: ProductAttribute supplies the shared mapping save interception
points and fresh Magento metadata. AttributeConsumer supplies Ergonode metadata
and snapshot refresh/removal contracts. MappingReaderInterface and
MappingStateBuilder from ProductAttribute supply current mappings, including
drafts and placeholders for missing attributes. Snapshot reads create fresh
metadata providers for each state and do not use an inbound mapping provider.
There is no dependency on ProductAttributeConsumer, its UI or a publisher.

Manual and additive saves are observed at the shared owner, including saves
initiated by either optional UI extension. There is no new data migration.

Does not own live mappings, attribute import/synchronization,
other Ergonode families, shared Admin UI primitives or admin controllers.
No reverse dependency from a consumer.

ProductAttributeConsumerHistory optionally captures inbound manual creation,
automatic mapping and synchronization through the public operation capture API.
Its outer capture includes newly created Magento metadata; nested neutral
persistence still produces one operation. Without that extension the base capture
and viewer continue working. Disable/remove the consumer extension before removing
the consumer; neither action deletes these historical records. Only uninstalling
ProductAttributeHistory removes its history table and view permission.

## Option history

Each new state includes options grouped by side and parent attribute code. Option
identity is the side, parent code and option code, including boolean values reused
by different attributes. Snapshots retain option labels, scope, visibility and both
the mapped option and its parent code. Unmapped attributes retain their options.
Neutral OptionMappingSaver and AttributeConsumer option refresh/removal contracts
are captured with the same nested-operation behavior as attributes.

Existing saved operations without the options field remain readable and return
`options: null` (unavailable); an empty options collection means captured and empty.
There is no schema migration or inferred backfill. Creation, deletion and metadata
changes are recorded when they occur inside a captured operation. Optional consumer
bridges remain responsible for their own synchronization/creation boundaries.

## Capture and retention configuration

Owns `ergonode_products/history/enabled`, `cleanup_enabled`, `retention_days` and `schedule`
under the same prefix. Capture and cleanup default to enabled; retention defaults
to 30 days and the independent cleanup cron defaults to `0 2 * * *` in Magento's
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
