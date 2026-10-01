# Ergonode_ProductTemplatePublisher

[Product module responsibility map](../../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Selects a product's outbound Ergonode template from the active mapping of its Magento
attribute set.

Implements ProductPublisher's template-code provider. Template owns
mappings; TemplateConsumer optionally imports their remote metadata. Template-definition creation/publication belongs to the
template family, not this adapter.

## Data, configuration and removal

No owned tables, configuration paths or uninstall handler. Removing it restores
ProductPublisher's `default` template provider and leaves template mappings and
attribute sets untouched.

## Dependencies and extension points

Reads `TemplateAttributeSetMappingProviderInterface`; a missing/inactive mapping blocks
the affected product when this adapter is installed. Does not create prerequisites.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-product-publisher](../../../../packages/ergonode/module-product-publisher/README.md)

`Ergonode_Template` is installed through ProductPublisher's dependency graph and
provides the mapping reader used by this adapter.

Ownership and wiring: [etc/di.xml](etc/di.xml).

## Operational details

Optional outbound adapter between Magento product attribute sets and Ergonode
product templates. It replaces the `default` template code supplied by
`Ergonode_ProductPublisher` with active mappings imported by
`Ergonode_Template`. Without TemplateConsumer, existing active mappings remain readable,
but this adapter does not import new template metadata.

The module owns no persistence or configuration. It does not create, import or
modify templates, Magento attribute sets or products. A Magento attribute set
without an active Ergonode template mapping remains unavailable for product
publication.

## Contracts and consumers

The module implements
`Ergonode\ProductPublisher\Api\ProductTemplateCodeProviderInterface` and reads
`Ergonode\Template\Api\TemplateAttributeSetMappingProviderInterface`.
`Ergonode_ProductPublisher` is its only runtime consumer.
