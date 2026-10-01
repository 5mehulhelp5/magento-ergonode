# Ergonode_TemplateAdminUi

Owns the independent Templates destination, three-column template-to-attribute-set
mapping workspace, names, selection, autosave, routes and presentation.
Reads local templates through Template contracts; the screen requires neither
Consumer nor Publisher. It owns no tables and performs no remote import/publication.
The mapping autosave endpoint coordinates Template mapping and Core visibility writes
in one database transaction. The underlying domain contracts remain independently usable.

WorkspaceConfigProviderInterface contributes optional UI capabilities. Layout children
contribute actions; the workspace-ready event exposes the lifecycle, config and autosave
context for independent Consumer, Publisher and structure-preview adapters.
The structure action uses the same CoreAdminUi button primitive as attribute options.

## Workspace connection requirement

Operational pages opt into CoreAdminUi's `ergonode_connection_required` layout
handle. Invalid active connection configuration replaces the entire content area
before workspace templates or extension initializers render. The shared notice
uses the workspace width and offers a short configuration prompt with an
ACL-aware configuration button. It omits individual validation details and retry controls. Both configured read and read-and-write modes permit
opening the workspace; operation-specific checks remain with their owners.
This also applies when editing locally stored mappings or browsing local products.
No data or remote permissions are changed by this presentation requirement.
