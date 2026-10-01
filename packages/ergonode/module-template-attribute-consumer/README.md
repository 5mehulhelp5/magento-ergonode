# Ergonode_TemplateAttributeConsumer

This module synchronizes Ergonode template sections and mapped attributes into
Magento product attribute sets. It owns the synchronization configuration and
the tables that record template ownership of Magento placements and groups.

It does not own Magento attributes, attribute placements or attribute groups.
Data uninstall removes only the module configuration, ownership mappings and
module tables; Magento EAV structures are deliberately preserved.

Owns ergonode_template_manual_placement, keyed by Magento attribute set and attribute, independent of remote snapshots. ManualPlacementSaverInterface validates the current template mapping and mapped, unprotected attribute before saving. Manual attributes keep their group/order and membership when absent from the source; sync never reinserts a manually removed placement. Existing Magento/system protection remains in force. Required attributes are also retained during cleanup. StructureMetadataContributor exposes ownership/protection/manual flags to the neutral view.

Group synchronization reuses only explicitly owned groups, preserves foreign names/order and creates its own prefixed groups even when an old section mapping points at a native group. No ownership is inferred or repaired from a matching prefix. No existing data migration or automatic bulk rename is performed. Uninstall removes the manual preferences while preserving Magento EAV.

## Import and deletion lifecycle

ImportedTemplateProcessor implements the snapshot-only import contributor. ImportedTemplateSynchronizer
implements TemplateConsumer's optional synchronization contributor using the existing dependency.
It runs only after complete source import, with sync_attributes enabled. Deleting a template removes
its owned placements and empty owned groups across mapped sets in a transaction per template.
Manual, system and Magento-required placements survive and ownership is released. Sets, products,
attribute definitions, values and neutral mappings survive. Repeating cleanup is idempotent.

Malformed/missing connection edges, node codes or pagination metadata fail import rather than
being interpreted as an empty structure. A complete empty structure remains a valid deletion signal.
Sections with no eligible mapped attributes do not create a group. Names and ordering come from
source section labels and API list order; no independent UI-position field is exposed by the
verified schema.

The verified Template/Section AttributeEdge exposes node and cursor only, with no template-scoped
required flag. No automatic Ergonode required-to-Magento conversion is implemented. Magento
is_required remains authoritative for cleanup protection; it is global to the attribute definition.
Existing attribute deletion streams reconcile attribute snapshots separately; full template refresh
then reconciles source membership. This module never deletes Magento attribute definitions or values.
