# Ergonode_ProductCategoryPublisher

[Product module responsibility map](../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Loads Magento product category assignments, resolves Ergonode codes and reconciles
remote product assignments after successful base publication.

Category owns mapping data; ProductPublisher owns base product mutation results. This
adapter owns category-state reads and add/remove decisions, not category creation, tree
publication or special category-reference attribute values.

## Data, configuration and removal

Owns `ergonode_products/publication/category_mode` (`keep`/`match`) but no tables.
Uninstall removes that exact setting; it does not reverse prior assignments or delete
category mappings.

## Dependencies and extension points

Decorates ProductPublisher source/prepared state and intercepts successful
synchronization. `match` requires complete source mappings before any removal.
ProductCategoryConsumer is independent.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-category](../module-category/README.md)
- [ergonode/module-product-publisher](../module-product-publisher/README.md)

Ownership and wiring: [etc/di.xml](etc/di.xml), [Setup/Uninstall.php](Setup/Uninstall.php).

## Operational details

Adds optional outbound publication of product category assignments to
`Ergonode_ProductPublisher`.

The Magento source reads product assignments, resolves category IDs through the
category mapping contract and decorates the base product state with Ergonode
category codes. Codes are the only category values sent over the Ergonode
product API; this module does not publish category attributes, names or trees.

After successful base product publication, the module reads the current remote
assignments and emits `productAddCategories` and, when allowed,
`productRemoveCategories` operations. Base publication failures prevent
category mutations for the affected SKU.

SKU indexes use `sku:` prefixes; query and mutation values come from the original
string SKU, never raw array keys. This preserves numeric identifiers and leading
zeros throughout category reads, add/remove operations and result correlation.
If the category-state read fails after the base stage, each eligible product gets
a failed category-stage result while retaining its base mutation results. No category
mutations are attempted, and existing failed/deleted/ineligible results are preserved.
The standard Magento log records the stage, affected SKUs, exception class and source
location; exception messages/traces and request credentials are not logged here.
The UI reports the completed base stage and unsent category assignments rather than
losing every product result to an unclassified process error. No automatic retry is added.

## Publication modes

`ergonode_products/publication/category_mode` supports:

- `keep` (default) — add Magento categories missing in Ergonode and retain
  remote-only assignments;
- `match` — add missing assignments and remove remote-only ones.

`match` is fail-closed. If any Magento category assigned to a product lacks a
usable Ergonode mapping, that product fails before base or category mutations;
the incomplete source cannot authorize removal.

The module is independent of `Ergonode_ProductCategoryConsumer`. Disabling it
removes category state decoration, remote category reads and category mutations
while base product publication continues normally.
