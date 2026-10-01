# Ergonode_ProductConsumerAdminUi

## Responsibility boundary

Owns inbound action registration and admin POST request handling for the shared
product workspace. ProductAdminUi owns the grid, selection and browser process;
ProductConsumer owns reading Ergonode and writing Magento data. No Publisher dependency.

`ImportGridAction` requires `Ergonode_ProductConsumer::import`, an enabled connection,
a default language mapping and product attribute mappings. A lazy provider is invoked
only when the Consumer modules are enabled. Individual import actions are disabled
without an Ergonode SKU binding. Bulk selections report an individual error for each
unbound product. The endpoint validates form key and ACL and rechecks readiness.

## Dependencies and data

Depends on ProductAdminUi and ProductConsumer. No tables, persisted jobs or configuration.
Disabling this module removes its action without affecting browsing or publication.

## Verification

Shared ProductAdminUi stories cover Consumer-only and both-direction workflows.
ProductConsumer tests cover independent product failures and Magento store values.
