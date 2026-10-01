# Ergonode_ProductAttribute

[Product module responsibility map](../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Neutral attribute/option mapping rows, drafts, ordering, visibility, mapping/placement
policy, Magento metadata and assigned-SKU mapping support.

Starts with metadata supplied by callers and ends with validated persisted mappings or a
neutral read model. AttributeConsumer owns remote snapshots; ProductAttributeConsumer
creates/synchronizes Magento definitions; ProductAttributePublisher prepares outbound
definitions and product values.

## Data, configuration and removal

Owns `ergonode_product_attribute_mapping`, `ergonode_product_option_mapping`, product
attribute/option visibility in Core's shared visibility storage, and the policy paths
listed in Setup/Uninstall.php. Uninstall removes those mappings/settings/visibility,
preserving Magento EAV and Product's identity mode. It also defines the existing
mapping/save ACL resources under their unchanged `Ergonode_AttributeConsumer::*` IDs;
uninstall removes these four resources.

## Dependencies and extension points

`MappingReaderInterface`, `CompleteMappingProviderInterface` and
`SkuIdentityMappingProviderInterface` serve optional consumers. Metadata, placement and
option-change extension contracts belong here. Policy reads
`ergonode_products/attributes/*`; current default values are contributed by
ProductAttributeConsumer's config.xml, so ownership and the location of defaults are
distinct.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-product](../module-product/README.md)

Ownership and wiring: [etc/di.xml](etc/di.xml), [etc/db_schema.xml](etc/db_schema.xml), [Setup/Uninstall.php](Setup/Uninstall.php).

## Operational details

Owns direction-neutral product attribute/option mappings, validation, ordering,
visibility, Magento metadata, placement and mapping policy.
It does not define category-reference attributes.

Its persistent data is explicitly product-scoped:

- `ergonode_product_attribute_mapping`
- `ergonode_product_option_mapping`

The uninstall lifecycle removes these mappings and the module configuration,
including shared attribute/option visibility, while preserving Magento EAV
attributes and attribute options.
It preserves Product's shared identity-mode configuration and tolerates missing
tables when uninstall is repeated or follows a partial cleanup.

Optional modules may extend the placement and type rules through plugins. In
particular, `Ergonode_ProductCategoryAttribute` protects its configured special
attribute and restricts the matching Ergonode attribute to `Text`. Without that
module, all category-specific policy is absent.

`CompleteMappingProviderInterface` exposes complete attribute mappings and
mapped option IDs in a direction-neutral shape. It reads only this module's
mapping tables, normalizes Ergonode type names and applies placement/mapping
policy. It does not read a remote snapshot, import data, or load product values.
ProductAttributeConsumer enriches this result for inbound mapping; the optional
ProductAttributePublisher uses it to select outbound product attributes.

The dependency on `Ergonode_Product` lets this module register optional assigned
identity support and read the shared SKU mode. This module validates that one
complete Text attribute mapping targets Magento SKU; it does not own SKU-mode
configuration. `ProductAttributeConsumer` additionally checks the imported
attribute's active, global and unique metadata before import readiness.

`SkuIdentityMappingProviderInterface` reads the saved complete SKU mapping
independently of the mode selected for new bindings. Existing assigned identities
therefore remain resolvable after the shared configuration changes.

MappingReaderInterface exposes saved rows, drafts and batched option counts.
The shared savers accept resolved metadata, enforce mapping policy and preserve
mapping identifiers for unchanged pairs. Removing or changing an attribute
mapping also removes its obsolete option mappings and visibility.

Magento metadata providers and MagentoAttributeMetadataContributorInterface
belong here. Optional contributors add product-specific metadata without a
reverse dependency. Core supplies shared mapping persistence and visibility.
This module has no dependency on AttributeConsumer, ProductAttributeConsumer,
Publisher or Admin UI. Snapshot metadata is supplied by callers.

Option persistence writes neutral mapping rows without a history observer or history service.

Option matching uses one direction-neutral pair planner. It compares the Ergonode
code with the Magento store-0 label first, then checks explicit Magento store
labels against Ergonode names in the language assigned to each store ID. Matching
normalization trims, lowercases and replaces consecutive whitespace with `_`;
punctuation and diacritics remain significant. Ambiguous keys are left for manual
selection, while independent unambiguous pairs remain available. This comparison
rule does not generate new Ergonode codes.
For ordinary EAV options, Magento metadata reads the actual store-0 and explicit
store-label rows in one bulk query; a current-store API label is not used as
evidence of a store-0 label. Custom-source labels retain their existing read path.

## Value adapters

`ergonode_product_attribute_mapping.value_adapter` stores a stable adapter code. `ValueAdapterRegistry` receives descriptors implementing `ValueAdapterInterface` through DI. Saving derives the code from the configured Magento attribute and preserves an existing code even when its module or configuration disappears; client payload cannot clear it. Explicit removal of the mapping removes this requirement together with the mapping. The declarative schema defines the current format; no data patch or legacy format conversion is provided.

`CompleteMappingProviderInterface::getMappings('import'|'publish')` excludes mappings whose saved adapter is absent, does not support the configured attribute, or is unavailable in that direction. A configured adapter without a saved marker also requires saving the mapping first. `getMappings()` without a direction is the neutral administrative read. Exclusion happens before option resolution and must not be interpreted as an instruction to clear a product value. This module owns marker persistence and availability; domain adapters own conversion, configuration and direction support.

## Product media capability

Media type compatibility is direction-neutral policy. Gallery is never available
as an ordinary product mapping, regardless of its attribute code. Image requires
an optional mediaTypes contribution from ProductMedia, and maps only to Magento
Image attributes. The same policy drives Admin compatibility, save validation and
complete mapping reads. ProductMedia registers a persistent product_image adapter;
its import bridge enables the supported direction. No reverse module dependency
or migration of existing mapping rows is added. Existing unmarked mappings require
a regular save before they can use the new adapter.

## Reserved identity attribute

In `mapped` SKU mode, the Magento attribute selected by
`ergonode_products/identity/magento_attribute` is reserved for product identity.
ProductAttribute reads the mode and attribute through Product's existing public
contracts. The code is configuration-driven; no project-specific attribute is
hardcoded.

The reserved attribute is omitted from ordinary mapping metadata, saved mapping
presentation, automatic matching/creation, complete import/publication mappings
and template code resolution. Both full mapping saves and automatic additions
reject attempts to map it. Internal metadata reads with `includeExcluded=true`
still expose its existence, so automatic creation cannot recreate it.

Existing reserved mapping rows are preserved during ordinary saves and excluded
from execution. Their Ergonode side remains occupied until the reservation is
removed by changing identity configuration; attempting to reuse that side is
rejected. This requires no migration or deletion of mappings, option rows or
product identities. Leaving `mapped` mode or selecting another identity attribute
releases the previous attribute to the existing mapping rules. Assigned SKU
mapping support and `ergonode_product_mapping` retain their existing contracts.
