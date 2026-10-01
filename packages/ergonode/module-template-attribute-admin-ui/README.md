# Ergonode_TemplateAttributeAdminUi

Owns read-only presentation of Ergonode template sections and Magento attribute-set
structure. Adds a popup to saved pairs on TemplateAdminUi: Ergonode sections/attributes,
current Magento groups/attributes, and Magento attributes outside the selected set.
Uses TemplateAttribute StructureProviderInterface and CoreAdminUi visual primitives.
No tables, sync, publication, manual attribute mapping, drag-and-drop or structure writes.

The production renderer is shared with Storybook. Illustrative data exist only in
stories; the Admin endpoint reads local snapshots and EAV structures and checks the
saved template-to-set pair. Missing source snapshots render an explicit empty state.
The existing domain ACL identifiers are retained to preserve saved role permissions.

The middle column shows Ergonode codes and group ownership supplied through the neutral read model. Unknown/foreign group ownership is displayed as Magento preserved structure, never inferred from a name prefix. The renderer provides placement-action slots only for mapped attributes in the set. The popup dispatches ergonode:template-structure-rendered so optional Consumer UI can add controls; base presentation still performs no writes.
