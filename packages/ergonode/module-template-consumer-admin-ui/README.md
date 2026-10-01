# Ergonode_TemplateConsumerAdminUi

Optional import and synchronization actions extending Ergonode_TemplateAdminUi.
Owns refresh, Sync and cursor-reset controls, snapshot removal, import configuration,
and resolving the create-Magento-attribute-set marker through TemplateConsumer.
It contributes configuration via WorkspaceConfigProviderInterface and a layout child.
It does not own the Templates destination, mapping presentation or mapping persistence.
Publisher is not required. Disabling this module removes its actions while preserving
the base screen and any independent Publisher or structure-preview extension.

Refresh explicitly describes source-only loading; synchronization is the operation that changes
Magento sets and their contributed structure. Schedule and cursor-reset messages describe full
list scans rather than a remote templateStream, which the configured API does not expose.

## Automatic synchronization mode

Only the read connection mode permits scheduled synchronization. Core owns
AutomaticSynchronizationInterface; synchronization cron entrypoints consult it.
The read-and-write mode preserves manual operations and worker execution. The
restriction does not disable the whole cron group: manually requested media scans
and history housekeeping remain operational. Domain Admin modules register only
automation configuration paths with CoreAdminUi's visibility plugin. Saved values
are retained; manual import policies and settings remain visible.
