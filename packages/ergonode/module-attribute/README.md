# Ergonode_Attribute

## Responsibility boundary

Shared Ergonode and Magento attribute types, type compatibility and normalization
of attribute definitions, translated labels and values. The module provides neutral
contracts reused by product, category, template, inbound and publication modules.

Its responsibility ends at normalized data and compatibility decisions. It does not
perform remote requests, store snapshots or mapping rows, create Magento EAV entities,
download files, advance import cursors or own product/category synchronization policy.

## Public contracts

- `ErgonodeAttributeTypeInterface` names supported Ergonode types.
- `ErgonodeAttributeTypeResolverInterface` and `MagentoAttributeTypeResolverInterface`
  translate source and target metadata into attribute types.
- `AttributeTypeCompatibilityInterface` defines generic attribute/option compatibility
  and exposes the attribute compatibility map.
- `AttributeDataNormalizerInterface` normalizes definition labels and type parameters.
- `AttributeValueNormalizerInterface` normalizes labels and type-dependent values.
- `TranslationNormalizerInterface` exposes the narrower language-to-label operation.

[Global DI](etc/di.xml) binds these contracts. AttributeValueNormalizer implements
both the value and translation contracts; callers needing only labels can depend
on TranslationNormalizerInterface. Domain-specific assignment restrictions remain
in the consuming modules.

File and gallery values normalize to ordered, deduplicated lists of non-empty paths.
Image values normalize to a single path. Consumers targeting a single Magento file
field own the selection of the first file; the shared normalizer preserves the full list.

## Data, configuration and dependencies

No owned tables, configuration paths, import checkpoints, ACL resources or setup
patches. Removing this module does not perform data cleanup, but its dependent
modules require the contracts it supplies.

[composer.json](composer.json) declares Magento Catalog as its module dependency.
Consumers include [AttributeConsumer](../AttributeConsumer/README.md),
[AttributePublisher](../AttributePublisher/README.md), ProductAttribute,
CategoryAttribute and their import/publication extensions.

Tests under [Test/Unit/Model](Test/Unit/Model) cover normalization, type resolution
and compatibility. These tests verify the shared rules; they do not exercise remote
transport or domain persistence.
