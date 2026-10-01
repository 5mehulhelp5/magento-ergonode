# Ergonode_ProductAttributePublisherAdminUi

[Product module responsibility map](../../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Outbound definition publication actions, batch controllers, pending remote-creation
preparation and verified metadata contribution after confirmed publication.

Extends ProductAttributeAdminUi and reuses AttributePublisherAdminUi behaviors.
ProductAttributePublisher prepares/publishes definitions; ProductAttribute owns the
final mapping save. The outbound verifier reads confirmed definitions with write scope
and contributes them to the neutral editor's request-local metadata catalog.

## Data, configuration and removal

No owned schema, runtime configuration or uninstall handler. Removing this UI preserves
mappings, visibility, Magento attributes and published Ergonode definitions.

## Dependencies and extension points

Uses outbound definition APIs and the neutral editor's save extension points. Does not
depend on ProductAttributeConsumerAdminUi and does not own product publication jobs.
On the attribute page, listens for CoreAdminUi's `vea:option-mapping-ready` event
and initializes the existing option publication behavior for each embedded modal.
CoreAdminUi is already required by AttributePublisherAdminUi, so this event uses
the existing dependency chain.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-attribute-publisher-admin-ui](../../../../app/code/Ergonode/AttributePublisherAdminUi/composer.json)
- [ergonode/module-product-attribute-publisher](../ProductAttributePublisher/README.md)
- [ergonode/module-product-attribute-admin-ui](../ProductAttributeAdminUi/README.md)

Ownership and wiring: [etc/di.xml](etc/di.xml).

## Operational details

Optional outbound extension of ProductAttributeAdminUi. Owns publication
controllers, request/result formatting, pending-creation save adapters and
write-scope verification after confirmed publication. ProductAttributePublisher owns
definition preparation and remote publication; shared publication UI behaviors
belong to AttributePublisherAdminUi.

Depends on the neutral mapping editor, not ProductAttributeConsumerAdminUi.
The ergonode_attribute_publish route resolves this module's batch controllers.
Neither removal nor disablement deletes shared mappings, visibility or Magento
attributes. Published Ergonode definitions are preserved.

Option publication passes both Magento and Ergonode attribute codes from the saved
attribute mapping to ProductAttributePublisher. Single and batch actions prepare
one option state and reuse its code and translated Admin-language label for the UI
mapping and remote write. The UI does not generate option codes from browser labels
or own the system option translation dictionary.
