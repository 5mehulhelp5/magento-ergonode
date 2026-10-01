# Ergonode_ProductConsumer

[Product module responsibility map](../../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Durable Ergonode product import: changed/deleted stream scheduling, queue coalescing,
Magento product writes, retries, deferred dependencies and deletion-as-disable.

Product owns identity records and SKU mode. This consumer writes mapped product values;
ProductAttributeConsumer owns attribute/option definitions and inbound mapping
enrichment. Type, category and media adapters supply their own post-write behavior.

## Data, configuration and removal

Owns `ergonode_product_import_item`, `ergonode_products/import/*`, changed/deleted
stream cursors and `ergonode.product.import` queue/cron records. Its uninstaller removes
those runtime records, preserving Magento products and Product identities.

## Dependencies and extension points

Exposes type, requested-attribute, deferrer, hash and state-synchronizer extension
contracts. ProductAttributeConsumer supplies the inbound mapping contract and is declared in
composer.json. The attribute-free base-import profile is not provided by this package.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-product-attribute-consumer](../ProductAttributeConsumer/README.md)
- [ergonode/module-template](../Template/README.md)
- [packhauer/module-file-attribute](../../PackHauer/FileAttribute/README.md)

Ownership and wiring: [etc/di.xml](etc/di.xml), [etc/db_schema.xml](etc/db_schema.xml), [Setup/Uninstall.php](Setup/Uninstall.php).

## Operational details

Imports Ergonode product streams into Magento through a durable, coalescing
work queue. The changed and deleted streams keep independent persistent
cursors. A cursor advances only after the corresponding work item has been
persisted, so replay after interruption is safe.

The base product query and `RemoteProduct` contain no category assignment
state. Installing `Ergonode_ProductCategoryConsumer` adds a separate
`categoryList.code` query and synchronizes the resolved Magento assignments.
Without that module, products are still imported and existing Magento category
assignments are left untouched.

## Product types

- Ergonode `SimpleProduct` defaults to Magento `simple`; existing `virtual`
  and `downloadable` products keep their type when their adapter is enabled.
- Ergonode `VariableProduct` maps to Magento `configurable`.
- Ergonode `GroupingProduct` defaults to Magento `grouped`; an existing or
  explicitly classified `bundle` uses the optional bundle adapter.

The optional Ergonode attribute configured as
`ergonode_products/import/type_attribute_code` (default
`magento_product_type`) may explicitly select a compatible Magento type. The
import never changes an existing Magento product type implicitly.

## Safety and scale

Each SKU has at most one pending row. A newer event replaces its payload and
event token, preventing an older worker from acknowledging newer data. Workers
claim bounded batches with expiring leases, use exponential retry for failures,
and defer missing variants, children, mappings or templates without exhausting
the normal retry budget. The scheduler stops reading streams at the configured
backlog threshold.

Remote deletion disables only a product already owned by the immutable
`ergonode_product_mapping`; it never deletes a Magento product or claims an
unmapped SKU. Successful payload hashes skip repeated content writes; mapped
identity attribute validation and repair still run before this check.

Optional import modules may contribute state through two ordered pools:

- `ProductImportHashProviderInterface` adds external state to the durable
  no-op hash, so a category-only or media-only change is not skipped;
- `ProductStateSynchronizerInterface` applies that state after the base product
  write and receives both the Magento product ID and its current Magento SKU.

The category consumer uses both contracts. This keeps its remote query,
category-code mapping and assignment writes outside the base consumer.

In `assigned` identity mode import resolves a product by an existing mapping,
then by the unique Ergonode attribute mapped to Magento `sku`, and only then
creates a Magento product. A fallback match is bound atomically to the native
Ergonode SKU. Magento SKU changes received through that mapped attribute are
validated for emptiness, uniqueness and identity conflicts, and are persisted
only after the remaining product synchronization succeeds.

In `mapped` mode import resolves an existing binding first, then an existing
Magento product whose configured identity attribute equals native `product.sku`.
Queued and selected-product imports write the native SKU to that attribute,
including when the queued payload hash is unchanged. A remote-only product
without a Magento match is reported as unresolved; import does not invent a
separate Magento SKU. EAV and registered static attributes use the same code.
The identity attribute cannot also be an ordinary product value mapping;
such a mapping fails the item before value writes.
When the configured mode is `mapped`, both stream and selected-product import
check an existing binding against the configured identity attribute. A different
value or an attribute owner on another Magento product stops the item for explicit
identity reconciliation; historical `assigned` and `shared` rows are not changed.

Enable `ergonode_products/import/enabled`, run the
`ergonode.product.import` queue consumer, or schedule work manually with:

```bash
bin/magento ergonode:products:import --max-pages=100
```

## SKU identity configuration

New bindings use `Ergonode_Product`'s
`ergonode_products/identity/sku_mode` setting, also used by publication. An
administrator must select `assigned` or `mapped` before creating new bindings.
An unsupported assigned mode is rejected instead of becoming shared implicitly.
Existing assigned bindings resolve the saved SKU attribute mapping independently
of the mode selected for new bindings.

## Synchronous selected-product import

`ProductImportBatchInterface` imports up to 50 existing bound Magento products directly
from the admin request. It does not dispatch queue messages or depend on Publisher.
ProductConsumerAdminUi contributes the action to ProductAdminUi's neutral workspace.
`ProductImportReadinessInterface` accepts active read or write credentials, requires a
default language mapping and configured attribute mappings.

The selected importer reuses remote loading, language/option value mapping and SKU
identity synchronization. It restricts values to mapped attributes in the target set.
It preserves product type, attribute set and structural relationships. It does not create
remote-only products or import category relations, media-gallery structures or tier prices.
Assigned SKU uses the configured identity mapping; historical shared bindings use
their persisted native Ergonode SKU.

Magento repository writes apply the normal attribute backend and scope behavior. The
default language writes store 0; mapped store views receive their language values and
missing translations clear overrides to Use Default. Global attributes use store 0.
Each product failure includes product ID, native SKU and a log reference. A failed
product may have partial store writes; successful products are not automatically replayed.
Missing remote products produce an error and are not deleted or deactivated.

The legacy optional stream/cron pipeline remains separate and disabled by default.
ProductAttributeConsumer supplies complete inbound mappings. FileAttribute handles mapped
file attributes; Template supports the separate legacy creation/type pipeline.

## Source definition preparation

Selected-product batches prepare attribute definitions before readiness checks.
RemoteProductLoader also prepares definitions before each product load, covering
selected imports and queued/stream imports in long-running processes. Preparation
uses ProductAttributeConsumer's API over the existing dependency. Unavailable
source mappings are excluded before value mapping, so deleting an Ergonode
definition cannot by itself become a Magento value-clear instruction.

## Attribute resolution within an import

The preliminary special-attribute result is reused by the product writer, including
explicit store-value clear intents. Already resolved attributes are not resolved again.
Selected-attribute imports still limit both fresh and supplied values to their requested
attribute codes.

Resolvers may implement `ProductAttributeResolutionScopeInterface` to release their
operation-local lookup results. The mapper resets that scope before mapping and in a
`finally` block after mapping, including failures. Resolver pools and workers may be
shared across products; lookup results must not survive one mapping operation.

## Environment-specific automation settings

Global `etc/di.xml` registers the following owned settings as `environment`
with `Magento_Config` through `Magento\Config\Model\Config\TypePool`:

- `ergonode_products/import/enabled`

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
