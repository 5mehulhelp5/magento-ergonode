# Ergonode_Template

This module owns Ergonode template identity, the local template snapshot and
the one-to-one mapping between an Ergonode template and a Magento product
attribute set.

It does not own Ergonode sections, template attributes, Magento attribute
groups or attribute placement. Those capabilities belong to
`Ergonode_TemplateAttribute*` modules.

## Data and contracts

- `ergonode_template`, including the optional `attribute_set_id` mapping;
- template-code normalization;
- template-to-attribute-set mapping lookup contracts;
- template administration ACL resources.

## Removal

The standard Magento uninstaller drops the local template snapshot and removes
saved role rules for the template administration ACL resources. Magento
attribute sets and products are deliberately preserved because a mapping does
not prove that this module created the attribute set.

The domain also owns local snapshot reads, product attribute-set lookup, mapping
persistence and recording successfully published template identities. These contracts
are available without Consumer. Mapping target resolvers optionally supply creation
capabilities; the domain validates product-set IDs and one-to-one assignments.
Existing ACL IDs retain their historical Consumer prefix to preserve role permissions;
the definitions and neutral read/save authorization belong to this module.
