# Ergonode_ConsumerAdminUi

Owns read-only credential fields, validation and connection-test controls inside
the shared Test and Production groups. Depends on Consumer for the mode and
CoreAdminUi for neutral form, encryption-validation and test-button primitives.
Fields use native paths `ergonode_connection/{test|production}/consumer/api_key`.
Only read mode exposes them. The other mode's saved keys are preserved.
The selected environment requires a key; the inactive environment may remain empty.

No URLs, quotas, global mode selection, synchronization, schema or stored data
are owned here. Consumer owns credential persistence and uninstall cleanup.

Credential-group visibility depends on the global connection status, active environment and operating
mode, so inactive keys remain excluded from form submission after either selector
changes. Connection status is owned by Core and rendered by CoreAdminUi.

The visible API key is required when the global connection is enabled. Disabled
connections hide credential fields and retain their saved values.
