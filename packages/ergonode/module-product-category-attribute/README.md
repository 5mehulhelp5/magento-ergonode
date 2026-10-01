# Ergonode_ProductCategoryAttribute

[Product module responsibility map](../../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Shared configuration and mapping policy for one optional Magento product attribute that
references a category.

Owns the selected attribute and Text-only mapping constraint. It does not resolve
IDs/codes or update product category assignments. The Consumer/Publisher adapters
perform direction-specific value conversion; Category owns category mappings.

## Data, configuration and removal

Owns `ergonode_products/attributes/default_category_attribute`; empty disables the
feature. Uninstall removes that exact setting, preserving Magento EAV, shared attribute
mappings and category mappings.

## Dependencies and extension points

Exposes `CategoryReferenceAttributeConfigInterface` and contributes to
ProductAttribute's neutral placement/metadata policies. Requires neither product
direction runtime nor Category.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-product-attribute](../../../../app/code/Ergonode/ProductAttribute/README.md)

Ownership and wiring: [etc/di.xml](etc/di.xml), [Setup/Uninstall.php](Setup/Uninstall.php).

## Operational details

Owns the shared configuration and product-attribute mapping policy for one
optional Magento product attribute whose value references a category, for
example a customer's `default_category` attribute.

The configured attribute is protected by the generic product-attribute
placement policy, is exposed to product-attribute mapping even when it would
normally be hidden, and may map only to an Ergonode `Text` attribute. The
attribute code is read from
`ergonode_products/attributes/default_category_attribute`; an empty value
disables the feature.

This base module does not resolve category codes, import product values,
publish product values or validate category assignments. Install the optional
direction adapter that is needed by the deployment:

- `Ergonode_ProductCategoryAttributeConsumer` converts an Ergonode category
  code into a Magento category ID during product import;
- `Ergonode_ProductCategoryAttributePublisher` converts a Magento category ID
  into an Ergonode category code during product publication.

Both adapters consume the configuration contract owned here. The base module
depends on generic product-attribute mapping contracts but has no dependency on
`Ergonode_ProductConsumer`, `Ergonode_ProductPublisher` or
`Ergonode_Category`.

Metadata is contributed through ProductAttribute's neutral metadata contract.
ProductAttributeConsumer is not required by this configuration module.

## Adapter lifecycle

The stable adapter code is `category_reference`; its descriptor implements the public `ProductAttribute` value adapter contract. Consumer and Publisher independently contribute availability through DI. The base module alone enables neither direction. Configuration identifies the Magento attribute supported by the descriptor. Save its mapping after selecting the attribute.

Uninstall removes this module's configuration only. It deliberately retains the marker owned by `ProductAttribute`: removing the base or a direction module must leave the affected mapping blocked, rather than convert category codes as ordinary text. There is no data patch or support for a legacy mapping format. Removing a mapping explicitly removes its marker.

## Lifecycle integration coverage

`Test/Integration/ValueAdapterLifecycleTest.php` uses Magento's DI XML reader and the test database to verify direction removal/restoration, preservation of the saved adapter requirement without the base module, and uninstall cleanup of configuration without deleting the neutral marker. It resolves base and direction module paths through Magento's component registry and does not change local module enablement. Run through `make -f .agents/backend/Makefile test-integration` with `args='packages/ergonode/module-product-category-attribute/Test/Integration'` and the workspace runtime.
