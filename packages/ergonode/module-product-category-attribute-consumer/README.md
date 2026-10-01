# Ergonode_ProductCategoryAttributeConsumer

[Product module responsibility map](../../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Converts the configured special attribute's Ergonode category code into one Magento
category ID during product import.

ProductCategoryAttribute owns selection/policy, Category owns code/ID mappings and
ProductConsumer owns product writes. This adapter does not synchronize the product's
category assignment list or import categories.

## Data, configuration and removal

No owned tables, settings or uninstall handler. Writes the resolved value through
ProductConsumer into Magento product EAV; disabling the adapter does not remove existing
values or shared mappings.

## Dependencies and extension points

Contributes to the consumer's value-resolver pool and validation. Missing/ambiguous
mappings and missing required admin-store values fail explicitly.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-category](../../../../app/code/Ergonode/Category/README.md)
- [ergonode/module-product-category-attribute](../module-product-category-attribute/README.md)
- [ergonode/module-product-consumer](../module-product-consumer/README.md)

Ownership and wiring: [etc/di.xml](etc/di.xml).

## Operational details

Adds inbound product category-reference attribute conversion to
`Ergonode_ProductConsumer`.

For the attribute selected by `Ergonode_ProductCategoryAttribute`, an Ergonode
`Text` value is interpreted as one category code. The adapter resolves that
code to exactly one Magento category ID below the target store's category root
and writes the ID as the product attribute value. Missing or ambiguous category
mappings fail explicitly. A missing admin-store value also fails when the
Magento attribute is required.

The module owns no configuration or persistent data. It consumes the shared
category-reference configuration, the category mapping contract and the
product consumer's value-resolver extension point. It does not publish product
attributes or synchronize the product's category assignment list.

## Import scope and lookup lifetime

Required-value validation respects the selected Magento attribute codes. A selected
product import whose attribute set excludes the category-reference attribute does not
require its mapping or value. Full imports and selections including the attribute
retain required admin-store validation.

Category lookups are reused by `(root category ID, Ergonode category code)` within one
mapping operation. ProductConsumer resets resolver scope on entry and in `finally`,
so success and failure both release the lookup results. Its writer reuses the special
attribute result, including clear intents, without a second category lookup.

Unit tests can run from the backlog using `Test/bootstrap.php`; this registers PHP
namespaces only and does not enable modules or modify Magento configuration.

The consumer contributes import availability to the base `CategoryReferenceValueAdapter` through its existing dependency. Removing it leaves the neutral `category_reference` mapping marker intact and blocks this field during import. Publication availability is independent.
