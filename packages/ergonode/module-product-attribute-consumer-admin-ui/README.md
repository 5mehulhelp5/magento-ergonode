# Ergonode_ProductAttributeConsumerAdminUi

[Product module responsibility map](../../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Inbound synchronization and snapshot controls, explicit Magento attribute/option
creation requests, inbound settings and snapshot metadata for the neutral editor.

Extends ProductAttributeAdminUi; runtime creation and synchronization go to
ProductAttributeConsumer. It does not own the neutral editor or publication actions.

## Data, configuration and removal

No owned table or uninstall handler. Presents ProductAttributeConsumer's
`ergonode_attributes` settings and synchronization ACLs. Removing this UI preserves
settings, mappings, EAV and headless runtime operations.

## Dependencies and extension points

Adds capabilities, snapshot metadata and save preparation to the neutral mapping
screen. It delegates to the consumer's synchronization/saver contracts. Publication UI
can remain without this inbound UI.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-product-attribute-consumer](../module-product-attribute-consumer/README.md)
- [ergonode/module-product-attribute-admin-ui](../../../../app/code/Ergonode/ProductAttributeAdminUi/README.md)

Ownership and wiring: [etc/di.xml](etc/di.xml), [etc/adminhtml/system.xml](etc/adminhtml/system.xml).

## Operational details

Optional inbound extension of ProductAttributeAdminUi. Owns synchronization
controllers, snapshot refresh/deletion actions, inbound import configuration and the
contribution enabling explicit Magento attribute/option creation. Pending creation is delegated to
ProductAttributeConsumer; the neutral editor and persisted mapping state belong
to ProductAttributeAdminUi and ProductAttribute respectively.

The module contributes synchronization routes and ACL resources as block data.
Without it, the neutral screens expose existing entities and optional publication
actions. Removing the UI does not delete mappings, configuration or Magento EAV.

## Automatic synchronization mode

Only the read connection mode permits scheduled synchronization. Core owns
AutomaticSynchronizationInterface; synchronization cron entrypoints consult it.
The read-and-write mode preserves manual operations and worker execution. The
restriction does not disable the whole cron group: manually requested media scans
and history housekeeping remain operational. Domain Admin modules register only
automation configuration paths with CoreAdminUi's visibility plugin. Saved values
are retained; manual import policies and settings remain visible.
