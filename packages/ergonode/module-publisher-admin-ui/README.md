# Ergonode_PublisherAdminUi

The shared `manual-auth` component owns its login dialog lifecycle. Idempotent
`destroy()` clears the password and focus timer, removes the modal, resolves
pending authorization as cancelled and prevents late responses from reopening it.
Workspace adapters call this contract during their own cleanup.

This module owns the read-and-write credential configuration and admin batch
publication endpoint. `WriteReadinessProviderInterface` exposes configuration
readiness and an ACL-aware remediation URL to optional publishing admin modules.
It checks the active mode's write capability and presence of its key without exposing the
key or making remote requests. Readiness does not certify remote permissions.
Unavailable connections, modes without write access and a missing active write
key produce distinct guidance for Connection configuration. Credentials are checked
only after the selected connection allows writes; a read-only or disabled connection
does not imply a missing write key. Both Test and Production use Core's active selection.

Batch responses advertise a retry only when the transport contract allows a
safe retry. GraphQL authorization, request construction and ambiguous or
permanent transport failures terminate the request without a retry countdown.

Credential groups extend CoreAdminUi's Test and Production groups at native paths
`ergonode_connection/{test|production}/publisher/api_key`, visible only in write mode.
There is no independent write enable flag. Both queries and mutations use the same
active key. Test and Production key fields instruct operators to use a read-and-write
key without an assigned segment, so product publication can access the entire catalog. Validation and test controls reuse neutral CoreAdminUi primitives;
Publisher owns the mode, encrypted credentials, defaults and uninstall cleanup.
Removing this UI preserves the credentials. No old `config_path` aliases remain.

Credential-group visibility depends on the global connection status, active environment and operating
mode, so inactive keys remain excluded from form submission after either selector
changes. Connection status is owned by Core and rendered by CoreAdminUi.

## Shared REST account controls

Operations that require REST open the shared login dialog when the active profile
has no valid connection. Save environment and URL before starting such an operation. The
`Ergonode_Publisher::rest_connection` ACL controls POST login/status/disconnect actions.
The endpoints return status/email or sanitized errors, never access/refresh tokens.
Passwords have no configuration field name and are cleared after every request.

This module owns the shared operation-time login dialog and the admin-session storage
adapter. Products and categories consume the same AMD component, CSS and login endpoint.
Remember me is unchecked by default. When selected, login saves both tokens encrypted
through Publisher's existing persistent storage, shared by the active environment.
Otherwise both tokens are encrypted in the current administrator session and are
refreshed there. Passwords are never stored and tokens never reach browser responses.
Session credentials take precedence without replacing an existing persistent account.
Rejected session credentials leave an empty session override, preventing an implicit
switch to that shared account. Disconnect acts on the effective connection.
Publisher owns REST login, token validation, rotation, persistent storage and cleanup;
adminhtml DI supplies the session-aware implementation of its storage contract.
Status renews expired tokens, but does not certify resource permissions.
Storybook exercises the production dialog, cancellation, rejected login, unavailable
status and both checkbox values. CategoryPublisherAdminUi retains its checkpoints.

The visible API key is required when the global connection is enabled. Disabled
connections hide credential fields and retain their saved values.
