# Ergonode_ProductAttributeConsumer

[Product module responsibility map](../../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Ergonode-to-Magento attribute/option definition creation, metadata synchronization,
automatic mapping, inbound mapping enrichment and import readiness.

Ends at synchronized Magento definitions and mappings persisted through
ProductAttribute. It does not import product entity values, which is ProductConsumer's
job, publish definitions, own remote snapshot storage or own grouped history storage.

## Data, configuration and removal

Owns the inbound `ergonode_attributes/mapping`, `options` and `cron` settings enumerated
by its uninstaller, attributeStream execution cursor, cron records and synchronization
ACL resources. Cleanup preserves neutral mappings, visibility, shared product policy,
snapshots, Magento EAV and history; it also removes legacy snapshot columns.

## Dependencies and extension points

Synchronization/auto-mapper APIs are inbound entrypoints.
ProductAttributeConsumerHistory optionally observes them. AttributeConsumer owns stream
registration and snapshot refresh; this module supplies the executable product-attribute
synchronization.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-attribute-consumer](../../../../app/code/Ergonode/AttributeConsumer/README.md)
- [ergonode/module-product-attribute](../../../../app/code/Ergonode/ProductAttribute/README.md)
- [packhauer/module-unit-attribute](../../../../app/code/PackHauer/UnitAttribute/README.md)

Ownership and wiring: [etc/di.xml](etc/di.xml), [etc/db_schema.xml](etc/db_schema.xml), [Setup/Uninstall.php](Setup/Uninstall.php).

## Operational details

Owns Ergonode-to-Magento attribute/option creation and synchronization,
inbound metadata resolution and configured automatic mapping. Category-reference semantics are not part of this
base module.

The consumer registers the product option synchronizer as an
`Ergonode_AttributeConsumer` pool participant. It decides which saved product
attribute mappings are option-mappable and writes only to the product mapping
tables. The shared `ergonode_attribute_option` table remains a target-neutral
Ergonode snapshot.

The batch coordinator imports source pages through AttributeConsumer's
`AttributeBatchImporterInterface` and dispatches options through its
`OptionSynchronizationInterface`. The `product` participant is registered on that
public pool contract in [global DI](etc/di.xml). This module consumes the shared
snapshot APIs and retains ownership of the import run, saved cursor, EAV creation
and product mapping workflow.

Snapshot refresh and Magento synchronization are separate operations. Attribute
refresh imports only `ergonode_attribute`; option refresh imports only the
selected attribute's `ergonode_attribute_option` snapshot. Product attribute
synchronization may create and automatically map Magento attributes and then
synchronize mapped options. Standalone option synchronization may create,
label, order and map Magento options. The attribute synchronization process
owns the persisted `attributeStream` cursor and exposes its reset operation;
option synchronization has no persisted cursor of its own.

CLI, cron and the panel Sync action call the same option synchronization process.
For table-backed select and multiselect attributes it plans pairs across the
whole current option set before writing: code against Magento store 0, then
explicit Magento store labels against matching Ergonode language names in store
ID order. The EAV labels are loaded in at most two bulk queries per attribute. Existing
complete mappings keep their IDs; ambiguous matches are reported and skipped
without suppressing independent valid pairs. Unmatched remote options continue
through the existing Magento option creation path.
If that path finds multiple Magento options with the same normalized label,
it reports this option for manual selection and continues with other pairs.

During option synchronization, `ergonode_attributes/options/delete_missing_magento_options`
controls removal of options missing from a successfully refreshed full source
snapshot. When enabled, the consumer removes complete mappings for the current
product attribute pair and their table-source Magento EAV options in one
transaction. Active and unmapped options are retained; custom-source attributes
are skipped. The neutral snapshot Refresh action never removes Magento options.
Enabling this setting can make existing product values display as empty. An
isolated product test confirmed that deleting its selected option leaves the
raw option ID in `catalog_product_entity_int`, while Magento can no longer
resolve its label. Reverting code alone does not restore the deleted option;
restoring the displayed value requires recovering that option or correcting
the product value from a backup.

The module contributes the executable `attributeStream` synchronization and
cursor-reset operations to `Ergonode_Core`. The stream identity and status
registration remain owned by `Ergonode_AttributeConsumer`.

Uninstall removes its configuration, pending cron rows, the persisted
`attributeStream` cursor, and
legacy columns it previously added to the shared option snapshot. It never
removes Magento EAV attributes or options. It also removes its `attribute_sync`
and `option_sync` authorization rules, preserving the parent snapshot module's
permissions. Cleanup tolerates missing tables and repeated execution.

`MagentoAttributeMetadataContributorInterface` belongs to ProductAttribute; optional modules add metadata through that neutral contract. The
`Ergonode_ProductCategoryAttribute` contributor uses that contract to expose
its configured special attribute and category-reference marker. With the
optional module disabled, the provider returns only generic Magento attribute
metadata.

`SkuIdentityMappingResolverInterface` is the public read contract for the one
validated mapping used by the existing SKU mapping readiness check. It does not
publish attributes. The lookup is independent of the current mode so existing
assigned identities remain resolvable after switching new bindings to shared SKU.

Complete mapping rows and option IDs are read through ProductAttribute's
`CompleteMappingProviderInterface`. This consumer retains only its inbound
metadata, option-label and Magento-source enrichment. ProductPublisher no
longer calls the consumer's mapping or SKU resolver APIs.

Manual mapping persistence is delegated to ProductAttribute. Consumer savers are
inbound adapters: they resolve source metadata and create explicitly requested
Magento entities before invoking the shared savers. AttributeAutoMapper
coordinates suggestions and IdenticalCodeAttributeCreator only during configured
import. Removing this module preserves mapping rows, shared configuration and
attribute/option visibility; neutral mapping screens remain usable.

ProductAttributeConsumerHistory optionally records grouped inbound operations in
ProductAttributeHistory. Enable it alongside those modules to include manual
attribute creation, automatic mapping and synchronization in that history. The
consumer has no dependency on either history module; removing the optional
extension does not change import or delete historical records.

## Deleted source definitions

Definition synchronization reconciles the shared snapshot before automatic
Magento synchronization. The process requests reconciliation once per
`executeUntilComplete()` invocation, before its first page, and on every standalone
`executeBatch()` invocation, including resumed imports. Subsequent pages still
import the incremental remote stream, map attributes and synchronize their options
under the existing batch lock. The process does not retain preparation state between
invocations, and retries reconcile again before continuing from the saved cursor.
Source deletions occurring during a multi-page invocation are reconciled at the
next invocation. The remote execution cursor and snapshot checkpoint formats are
unchanged; there is no migration or new dependency. Shared reconciliation and
snapshot ownership remain in AttributeConsumer.

An absent or reset product import cursor does not force a full shared snapshot
download. Reconciliation checks its own checkpoint and downloads definitions when
the snapshot is missing, the source changes or a source stream reports changes.
Explicit snapshot refresh remains a separate operation that can force a download.

ProductAttributeSourcePreparationInterface prepares
current definitions and resets cached inbound mappings before product import.
Product mappings whose source definition is absent from the reconciled snapshot
are skipped with a change-report message. Saved neutral mappings and Magento
values are retained; empty values of an available source retain their existing
clearing semantics. This module does not own shared reconciliation checkpoints.

## Environment-specific automation settings

Global `etc/di.xml` registers the following owned settings as `environment`
with `Magento_Config` through `Magento\Config\Model\Config\TypePool`:

- `ergonode_attributes/cron/status`
- `ergonode_attributes/cron/schedule`

Configuration export treats these switches and schedules as installation-specific.
Their defaults and runtime interpretation are unchanged; they are not sensitive.

## Automatic synchronization mode

Only the read connection mode permits scheduled synchronization. Core owns
AutomaticSynchronizationInterface; synchronization cron entrypoints consult it.
The read-and-write mode preserves manual operations and worker execution. The
restriction does not disable the whole cron group: manually requested media scans
and history housekeeping remain operational. Domain Admin modules register only
automation configuration paths with CoreAdminUi's visibility plugin. Saved values
are retained; manual import policies and settings remain visible.

## Unavailable connection in scheduled work

Automatic synchronization and recovery use Core's fresh connection probe before
starting domain work. Missing or invalid configuration and unsuccessful probes
skip the run without changing cursors, enqueueing work or logging connection
errors. A rejected connection discovered during execution is also skipped;
unexpected failures remain visible. Existing work is retained for the next run.
The local media scan and history retention remain independent of this policy.
Unit tests cover repeated rejection and recovery; the project integration cron
contract exercises Magento scheduling with existing work and stored checkpoints.
