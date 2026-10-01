# Upgrade notes

This module supplies shared publication UI components. Product and category
publication Admin UI extensions own the layouts, routes and controllers that use
them; ProductAttributePublisherAdminUi also owns the product mapping-save plugins.
Disable dependent publication extensions together with this shared dependency.

ProductAttributeAdminUi and CategoryAttributeAdminUi own the neutral mapping
screens. Removing publication extensions does not remove their mappings or history.
See the [responsibility boundary and consumers](README.md).
