# Ergonode_ProductCategoryAttributeAdminUi

[Product module responsibility map](../../../../docs/ergonode-product-modules.md).

## Responsibility boundary

The special category-reference attribute selector and its mapping-screen presentation.

ProductCategoryAttribute owns configuration semantics and policy; the direction adapters
convert values. This module only presents selection/type constraints and does not own
product-category assignments.

## Data, configuration and removal

No owned table or uninstall handler. Writes ProductCategoryAttribute's
`ergonode_products/attributes/default_category_attribute`; removing the UI preserves the
saved setting and runtime policy.

## Dependencies and extension points

The UI extends the direction-neutral ProductAttributeAdminUi module. The adminhtml DI target owns the intercepted block; the label decorator consumes only the returned arrays. The runtime configuration module and its direction
adapters do not require this UI.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-product-category-attribute](../module-product-category-attribute/README.md)
- [ergonode/module-product-attribute-admin-ui](../../../../app/code/Ergonode/ProductAttributeAdminUi/README.md)

Ownership and wiring: [etc/adminhtml/di.xml](etc/adminhtml/di.xml), [etc/adminhtml/system.xml](etc/adminhtml/system.xml).

## Operational details

Adds the optional **Default Category** product-attribute selector to **Stores >
Configuration > Ergonode > Products > Attributes** and adapts the product
attribute mapping screen for that special attribute.

The selected code is saved as
`ergonode_products/attributes/default_category_attribute`. The mapping UI marks
it as a category reference and limits its compatible Ergonode type to `Text`.
Runtime category ID/code conversion belongs to the optional
`Ergonode_ProductCategoryAttributeConsumer` and
`Ergonode_ProductCategoryAttributePublisher` direction adapters.

Removing this Admin UI module does not remove the saved configuration or the
runtime module. Removing the runtime module removes its configuration and decoration policy.
The neutral mapping UI retains the adapter marker and reports unavailable directions. Each direction adapter remains independently installable and does not
require this Admin UI module.
