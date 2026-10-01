# Ergonode_TemplateAttribute

This module enriches `Ergonode_Template` with template sections and template
attributes. It owns their snapshot tables and the optional mapping from an
Ergonode section to a Magento attribute group.

It does not fetch templates, own the template-to-attribute-set mapping or
publish templates. Inbound behavior is supplied by
`Ergonode_TemplateAttributeConsumer`. Product publication does not consume
template completeness requirements.

Data uninstall removes the template-section and template-attribute snapshot
tables owned by this module. It does not modify Magento EAV structures.

StructureProviderInterface exposes a read-only comparison of the local template
snapshot and Magento product attribute-set contents. It validates the saved pair
and returns all product attributes with their placement in that selected set.
It does not fetch from Ergonode or modify Magento groups and placements.

StructureProvider also exposes saved section IDs and complete attribute mapping codes through ProductAttribute MappingReaderInterface. StructureMetadataContributorInterface allows optional sync modules to enrich group ownership and placement metadata without reverse dependencies.
