# Ergonode_Product

Registered source in `app/code` (enablement is controlled by `app/etc/config.php`).

[Product module responsibility map](../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Shared product identity: binding a Magento product ID to its immutable native Ergonode
SKU, identity lookup and import/publication markers.

The shared SKU-mode policy starts here. ProductConsumer and ProductPublisher execute
their direction-specific work; ProductAdminUi presents the identity. Attribute,
category, template, media and relation behavior belongs to their respective extensions.

## Data, configuration and removal

Owns `ergonode_product_mapping`, `ergonode_products/identity/sku_mode` and
`ergonode_products/identity/magento_attribute`. Uninstall removes that table and
those settings, not Magento products. Removing a direction
adapter does not remove the shared identities.

## Dependencies and extension points

`ProductIdentityServiceInterface`, `ProductIdentityModeProviderInterface` and
`MagentoIdentityAttributeInterface` serve both
directions. `AssignedIdentitySupportInterface` accepts optional support from
ProductAttribute. A new binding requires an explicit `assigned` or `mapped` mode.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-attribute](../module-attribute/composer.json)
- [ergonode/module-core](../module-core/README.md)

Ownership and wiring: [etc/di.xml](etc/di.xml), [etc/db_schema.xml](etc/db_schema.xml), [Setup/Uninstall.php](Setup/Uninstall.php).

## Operational details

Owns the immutable `Magento product ID <-> native Ergonode SKU` identity table
shared by product consumers and publishers. Each row records whether both
systems share the Magento SKU (`shared`), Ergonode assigned an independent
native SKU (`assigned`) or a selected Magento attribute supplies it (`mapped`).
The table holds the immutable confirmed binding; a mapped attribute supplies
the value for new bindings and is checked against existing bindings. A bulk
lookup verifies that each selected native SKU belongs to one Magento product;
an empty or duplicate value blocks synchronization. The product
extension attribute `ergonode_sku` is read-only.

Direction-specific modules remain optional; disabling a publisher does not
disable product import.

This module is category-neutral. Product-to-category assignments and special
product attributes that reference a category are supplied by optional modules;
the identity registry remains usable when none of them is installed.

## Optional category modules

| Module                                       | Responsibility                                                                            | Can be enabled independently            |
| -------------------------------------------- | ----------------------------------------------------------------------------------------- | --------------------------------------- |
| `Ergonode_ProductCategoryConsumer`           | Import product assignments from Ergonode category codes                                   | Yes, inbound only                       |
| `Ergonode_ProductCategoryPublisher`          | Publish Magento assignments as Ergonode category codes                                    | Yes, outbound only                      |
| `Ergonode_ProductCategoryAttribute`          | Configure one special product attribute as a category reference and constrain its mapping | Yes, shared by either direction adapter |
| `Ergonode_ProductCategoryAttributeConsumer`  | Import the special attribute from an Ergonode category code to a Magento category ID      | Yes, inbound only                       |
| `Ergonode_ProductCategoryAttributePublisher` | Publish the special attribute from a Magento category ID to an Ergonode category code     | Yes, outbound only                      |
| `Ergonode_ProductCategoryPublisherAdminUi`   | Present outbound category mode configuration                                              | Requires the outbound runtime           |
| `Ergonode_ProductCategoryAttributeAdminUi`   | Present special category-reference attribute configuration                                | Requires the attribute runtime          |

There is no general `Ergonode_ProductCategory` runtime: inbound assignments,
outbound assignments and the direction-specific category-reference adapters
have no shared category-specific service. They depend on the existing public
category mapping contract instead.

## Shared identity mode

`ProductIdentityModeProviderInterface` reads
`ergonode_products/identity/sku_mode` for new identities. `assigned` stores the SKU returned by Ergonode against
the immutable Magento product ID. `mapped` reads the remote SKU from the Magento
product attribute named by `ergonode_products/identity/magento_attribute`. An
empty or historical `shared` setting blocks new bindings until an administrator
selects one of those two modes. Existing `shared` rows remain readable and can
continue synchronizing with their persisted mode.

The selected attribute must be a global, unique text product attribute. Its
code is resolved from current Magento metadata on each operation, so one
setting supports `varchar`/`text` EAV storage and a registered `static` column.
Moving existing values from EAV to static storage is a separate migration.
There is no default attribute code; a project can configure `navireo_id` after
registering that product attribute. Missing, empty, duplicate or conflicting
values block synchronization.

The base module retains historical shared identities without attribute modules.
`AssignedIdentitySupportInterface` is the optional extension boundary: assigned
identity requires an installed support provider and a valid attribute mapping.
`assertModeAvailable()` also checks persisted modes. Disabling an extension
never rewrites an existing identity or silently changes an assigned setting to
shared; the affected synchronization is blocked with a diagnostic.

The provider owns no imports, outbound requests or Admin UI. Uninstall removes
its exact configuration path and identity table. There is no data migration or
automatic rebinding of existing products in this change.

## Shared catalog read model

`ProductCatalogInterface` supplies paginated Magento products, identity SKU, attribute-set
labels, filter options and selected IDs to ProductAdminUi. It depends only on Magento
catalog data and this module's identity table; Publisher result tables are not queried.
