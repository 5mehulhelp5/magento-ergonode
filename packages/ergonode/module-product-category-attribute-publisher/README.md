# Ergonode_ProductCategoryAttributePublisher

[Product module responsibility map](../../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Converts the special product attribute's Magento category ID into one Ergonode category
code for outbound values.

ProductCategoryAttribute owns selection/policy, Category owns mappings, and
ProductPublisher owns orchestration. ProductAttributePublisher supplies the mapped-value
source; this adapter does not add that source by itself or publish category
assignments/definitions.

## Data, configuration and removal

No owned tables, settings or uninstall handler. Removing the adapter preserves EAV
values, attribute/category mappings and remote values.

## Dependencies and extension points

Contributes to ProductPublisher's attribute value-resolver and source-validator pools.
It rejects missing mappings and references to categories not assigned to the Magento
product.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-category](../../../../app/code/Ergonode/Category/README.md)
- [ergonode/module-product-category-attribute](../module-product-category-attribute/README.md)
- [ergonode/module-product-publisher](../../../../packages/ergonode/module-product-publisher/README.md)

Ownership and wiring: [etc/di.xml](etc/di.xml).

## Operational details

Adds outbound product category-reference attribute conversion to
`Ergonode_ProductPublisher`.

For the attribute selected by `Ergonode_ProductCategoryAttribute`, the adapter
interprets the Magento value as one category ID, resolves it through the shared
category mapping and publishes the resulting Ergonode category code as a
`Text` value. Publication fails when the mapping is missing or the referenced
category is not assigned to the Magento product. The Magento required flag does not block outbound values. Required is checked
centrally by ProductPublisher against the selected Ergonode template via REST.

A missing required category-reference value blocks the entire product before
product data is sent to Ergonode. The publication result identifies the product
SKU and attribute code and asks the operator to complete the value and retry.
Other valid products in the batch can still be published. Ergonode completeness
or segment visibility does not replace this local validation (MAG-PCA-005).

The module owns no configuration or persistent data. It consumes the shared
category-reference configuration, the category mapping contract and the
product publisher's value-resolver and source-validator extension points. It
does not import product attributes or publish the product's category assignment
list.

## Exact category membership

Membership validation compares each original effective store category ID with Magento
product category assignments. Ergonode codes are not category identities: different
Magento roots may map different IDs to the same code. ProductAttributePublisher
supplies original values through ProductPublisher's source-validator contract. No
additional category-mapping query is needed for membership validation.

Unit tests can run from the backlog using `Test/bootstrap.php`; this registers PHP
namespaces only and does not enable modules or modify Magento configuration.

The publisher contributes publication availability to the base `CategoryReferenceValueAdapter` through its existing dependency. Removing it leaves the neutral `category_reference` mapping marker intact and blocks this field during publication. Import availability is independent.
