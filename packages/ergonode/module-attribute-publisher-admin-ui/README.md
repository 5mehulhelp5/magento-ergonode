# Ergonode_AttributePublisherAdminUi

## Responsibility boundary

Shared optional Admin UI components for publishing attributes and options from
mapping editors: the publication initializer block/template, JavaScript behavior,
pending-type picker styles, mapping payload construction and existing-entity notices.

Domain extensions supply their own layout placement, routes, controllers and save
plugins. This module does not own mapping screens, mappings, publication transport,
import cursors or the Magento/Ergonode synchronization workflow.
For failed option publication, the shared behavior leaves the Magento option in
an unpaired row, places that row first and shows its error using the mapping
screen's existing red status style. Successful items remain eligible for the
partial mapping save.

## Consumers and wiring

[ProductAttributePublisherAdminUi](../ProductAttributePublisherAdminUi/README.md)
places the shared initializer in product attribute/option editors and owns their
publication routes, controllers and mapping-save plugins.
[CategoryAttributePublisherAdminUi](../CategoryAttributePublisherAdminUi/README.md)
places it in the category attribute editor and supplies category publication actions.

The neutral mapping screens belong to
[ProductAttributeAdminUi](../ProductAttributeAdminUi/README.md) and
[CategoryAttributeAdminUi](../CategoryAttributeAdminUi/README.md).
This module's [global DI](etc/di.xml) registers no save plugins, and the module
has no layout files or controllers of its own. Consumers configure the shared
`AttributePublisherMapping` block through layout data and use the template
`Ergonode_AttributePublisherAdminUi::ergonode-attribute-publisher-init.phtml`.

`AttributeMappingPayloadBuilder` creates pending publication mapping data.
`ExistingSynchronizationNoticeResolver` interprets publication results for notices;
it does not execute publication or persist mappings.

## Data, configuration and removal

No owned tables, configuration paths, ACL definitions, setup patches or uninstall
handler. [composer.json](composer.json) declares AttributePublisher and
PublisherAdminUi as module dependencies. Domain publication Admin UI extensions
depend on this shared module and must be considered together when disabling modules.
Removing the publication extensions does not delete mappings or history; the neutral
mapping modules remain the owners of their screens and data.

The module contains shared UI assets, so changes to their behavior follow the
repository's [Ergonode Storybook contract](../../../dev/tools/ergonode-storybook/README.md).
