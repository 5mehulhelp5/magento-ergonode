# Ergonode_ProductAttributeAdminUi

[Product module responsibility map](../../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Product attribute and option mapping screens, existing-entity selections, suggestions,
neutral saves, ordering and shared attribute-policy fields.

Owns HTTP/UI composition, not mapping persistence or definition creation.
ProductAttribute validates and saves mappings. ProductAttributeConsumerAdminUi and
ProductAttributePublisherAdminUi add independent inbound and outbound actions.
CoreAdminUi owns reusable mapping widgets/styles.

The neutral attribute screen can fetch remote definitions with the active Core
read credential in both read and read-and-write modes. A completed fetch updates
a one-hour Magento cache scoped by connection configuration and credential;
incomplete reads leave the previous catalog untouched. This UI cache is not the
AttributeConsumer import snapshot, and it stores no import cursor or Magento EAV.
The neutral option editor fetches all options for a saved attribute mapping through
the same read connection. It caches each attribute's option list for one hour and
replaces it only after every remote page succeeds. With ConsumerAdminUi installed,
Refresh continues to update the inbound snapshot instead.
The neutral remote catalog displays attribute and option names in the active
Ergonode language mapped to Magento Default Values (store ID `0`). If that
translation is empty, it tries other active languages mapped to Magento store
views in store ID order, then displays the entity code. Without a language
mapped to store ID `0`, it displays the code. Its cache key includes the ordered
language preference, so a language-mapping change cannot reuse labels selected
for the previous mapping; Refresh loads labels for the new preference. Magento
option labels on the other side of the editor remain the default values from
store ID `0`.

Auto Match reads verified option metadata on the server and returns editable
proposals. It never saves them; the editor's Save action persists the selected
pairs. Only option codes still available in the editor are considered, so
unsaved manual pairs are respected without trusting browser-supplied labels.
The remote option cache retains every returned language name alongside
the display label, and later snapshot metadata does not discard those names.
Only when store-0 matching leaves unresolved options does Auto Match use
language names. If the option cache predates language names or is absent then,
it completes a paginated remote refresh before suggesting language pairs.

## Data, configuration and removal

No owned table, cursor or uninstall handler. Shared policy fields edit
ProductAttribute's settings; remote snapshots remain AttributeConsumer's data. Removing
the screen does not remove mappings or Magento attributes.

## Dependencies and extension points

Consumes ProductAttribute's neutral savers/readers. Its metadata catalog combines
saved mapping identities with optional verified metadata sources and published
definitions recorded during the request. It retains the existing mapping ACL IDs,
whose definitions belong to ProductAttribute. Without direction extensions,
pending entity creation is rejected.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-product-attribute](../ProductAttribute/README.md)
- [ergonode/module-core-admin-ui](../../../../app/code/Ergonode/CoreAdminUi/README.md)
- [ergonode/module-language](../module-language/README.md)

The fetch implementation consumes Core's read-scope GraphQL and pagination
contracts and Attribute's canonical type resolver. Both are installed by the
existing ProductAttribute/CoreAdminUi Composer chains; the module-composer gate
rejects duplicate direct requirements for those two packages.

Ownership and wiring: [etc/di.xml](etc/di.xml), [etc/adminhtml/system.xml](etc/adminhtml/system.xml).

## Operational details

Owns the neutral product attribute and option mapping editor: navigation,
embedded option-mapping modal, existing-entity selection, matching suggestions,
manual save, ordering and shared mapping configuration. Optional inbound UI supplies
Ergonode snapshots; Magento metadata comes from ProductAttribute. Saved mappings remain
visible when no snapshot source is installed. New remote codes require verified metadata.
ProductAttribute owns persisted mappings, validation and visibility; this UI owns
no tables or import cursor.

Routes and persisted configuration/ACL identifiers are preserved. Existing roles
continue to use the same attribute/option mapping permissions. No data
migration or copying of mappings is needed.

ProductAttributeConsumerAdminUi contributes explicit Magento creation and
synchronization capabilities, actions and inbound configuration. Without it,
creation controls and synchronization actions are unavailable; forged pending
creation requests are rejected by the server.
With it, the existing Refresh action continues updating its owned snapshot.
Without it, Refresh fetches attribute definitions or options into the neutral
screen cache. This does not synchronize definitions into Magento or write option
snapshots.

ProductAttributePublisherAdminUi contributes Ergonode publication actions and
prepares pending remote creations before neutral save. Either extension can be
installed independently. Definition publication remains in the publisher runtime.

Shared templates, CSS and generic mapping behaviors belong to CoreAdminUi and
continue serving category mapping screens. This module owns product screen
composition and its Storybook examples.

Owns the `ergonode.attribute.mapping.sidebar` layout container, rendered below the Magento attribute list through CoreAdminUi’s optional sidebar slot in the existing right column. ProductAttributeHistoryAdminUi contributes its independently authorized read-only history panel.

Contributes only Attributes to the Product section navigation. Options are opened
from attribute mapping in the embedded modal; the direct option route remains available.

Mapping records display the persistent value adapter code and separate import/publication availability supplied by `ProductAttribute`. An unavailable direction is an error on the record; the field is skipped in that direction. Presentation uses the generic persistent validation message contract in `CoreAdminUi`.

## Workspace connection requirement

Operational pages opt into CoreAdminUi's `ergonode_connection_required` layout
handle. Invalid active connection configuration replaces the entire content area
before workspace templates or extension initializers render. The shared notice
uses the workspace width and offers a short configuration prompt with an
ACL-aware configuration button. It omits individual validation details and retry controls. Both configured read and read-and-write modes permit
opening the workspace; operation-specific checks remain with their owners.
This also applies when editing locally stored mappings or browsing local products.
No data or remote permissions are changed by this presentation requirement.
