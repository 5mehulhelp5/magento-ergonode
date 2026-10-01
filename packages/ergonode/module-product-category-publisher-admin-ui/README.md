# Ergonode_ProductCategoryPublisherAdminUi

[Product module responsibility map](../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Presents the optional product category publication mode selector.

ProductCategoryPublisher owns the keep/match policy and category operations.
ProductPublisherAdminUi supplies the product-publication UI context. This UI does not
own publication jobs or category synchronization.

## Data, configuration and removal

No owned table or uninstall handler. Edits
`ergonode_products/publication/category_mode`, owned by ProductCategoryPublisher;
removing the UI preserves the setting.

## Dependencies and extension points

Requires the category publisher runtime and product publisher UI. The runtime remains
usable without this configuration UI.

Magento Backend is supplied transitively by the category publisher dependency;
the manifest does not duplicate that requirement.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-product-category-publisher](../module-product-category-publisher/README.md)
- [ergonode/module-product-publisher-admin-ui](../module-product-publisher-admin-ui/README.md)

Ownership and wiring: [etc/adminhtml/system.xml](etc/adminhtml/system.xml).

## Operational details

Adds the category publication mode to **Stores > Configuration > Ergonode >
Products > Publication** when `Ergonode_ProductCategoryPublisher` is installed.

The field writes `ergonode_products/publication/category_mode` and exposes the
`keep` and `match` modes documented by the runtime module. This module contains
configuration presentation only; category state and GraphQL operations remain
owned by `Ergonode_ProductCategoryPublisher`.

Removing this Admin UI module does not disable category publication. The
runtime keeps the configured value, or uses `keep` when no value was saved.
