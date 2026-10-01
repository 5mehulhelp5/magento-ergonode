# Ergonode_ProductCategoryConsumer

[Product module responsibility map](../../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Reads a product's Ergonode category codes and replaces its Magento category assignment
list.

Category owns category mappings; category-family modules own category creation, names,
attributes and tree structure. ProductConsumer owns base product import; this adapter
supplies the extra query, import-hash contribution and assignment write.

## Data, configuration and removal

No owned schema, setting or uninstall handler. Assignment writes go to Magento catalog
data. Removing this adapter preserves the last assignments and removes its extra remote
reads.

## Dependencies and extension points

Implements ProductConsumer's hash/state extension contracts. Missing mappings use the
consumer's deferred-dependency path. Does not require ProductCategoryPublisher or the
special category-reference attribute modules.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-category](../../../../app/code/Ergonode/Category/README.md)
- [ergonode/module-product-consumer](../module-product-consumer/README.md)

Ownership and wiring: [etc/di.xml](etc/di.xml).

## Operational details

Adds optional inbound synchronization of product category assignments to
`Ergonode_ProductConsumer`.

For each imported product, the module reads all pages of Ergonode
`categoryList` and uses only `Category.code` as the integration value. Codes are
resolved through the category mapping contract, then the resulting Magento
category IDs replace the product's current assignments. Ergonode category
attributes, names and tree structure are outside this module.

The category-code list participates in the product import hash, so a
category-only change schedules a write even when the base product payload is
unchanged. Missing mappings are treated as unavailable dependencies and use the
product consumer's normal deferred processing path.

This module is independent of `Ergonode_ProductCategoryPublisher`. Disabling it
removes the extra GraphQL query and assignment synchronizer; base product import
continues and leaves Magento category assignments untouched.
