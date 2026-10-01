# Ergonode_Consumer

Owns the shared read-only connection mode, encrypted environment credentials and
the default `read` mode contribution. It depends on Core through the connection-mode
contract and common encrypted credential reader. It has no tables, Admin area,
domain queries, mappings, cursors, scheduling or synchronization workflows.
Those remain in the existing domain modules. Read mode forbids Ergonode writes;
it does not forbid consumers from persisting imported data in Magento.

## Connection mode and credentials

Owns the `read` (read-only) implementation of Core's `ConnectionModeInterface`,
registered through global DI. Credentials are encrypted at
`ergonode_connection/test/consumer/api_key` and
`ergonode_connection/production/consumer/api_key`; both are marked sensitive and
environment-specific.
Core selects one mode and environment for all requests. This module never selects
another profile after an error and does not own URLs, quotas or the form layout.
Uninstall removes only these two credential paths. No legacy paths or migration
fallbacks are supported. UI validation and test controls belong to ConsumerAdminUi.

Global DI also classifies the retired `ergonode_connection/read_operations/api_key`
as sensitive and environment-specific while old rows remain in `core_config_data`.
This export classification does not read, migrate or reactivate the retired key.
It can be removed after those rows have been removed through a separately approved
data cleanup on all installations that retain them.
