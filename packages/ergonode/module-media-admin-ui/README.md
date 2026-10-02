# Ergonode_MediaAdminUi

Owns global Magento Admin configuration for Ergonode media: synchronization,
the required Gallery attribute selection, existing file strategy, and local
media scan panel.
Consumes `Ergonode_Media` configuration/options and mode-lock contracts.
It owns the scan start/status HTTP endpoints and presentation, but no media
tables, background scanner, downloads, repair or federation operations.
`ScanRequesterInterface` and `ScanStatusProviderInterface` belong to Media.
The Gallery options come from Ergonode and are checked again on save. A failed
connection is shown as an unavailable list without crashing the config page.

`Test/Storybook/GalleryConfiguration.stories.js` reads production field metadata
from `system.xml`; Gallery choices are fixtures. Runtime save validation is
covered separately. `MediaScan.stories.js` renders the production AMD module and
HTML template, including pending, running, completed, failed and estimate-overrun
states. Full Magento Admin rendering still requires activation of
the backlog module in a dedicated environment.

## Product gallery boundary

ProductMedia now owns the global gallery selection/synchronization contract,
additional Image positions, native gallery writes and roles. Media retains
transfer/index/queue tables. MediaAdminUi consumes both public contracts;
ProductMediaConsumer requests the selected Gallery and configured Image sources
and defers mapped Images to gallery processing. No table migration is performed.
See [ProductMedia](../module-product-media/README.md) for the current contract.

The first-image mechanics are explained directly below Gallery selection.
The additional-role selector accepts one existing non-primary, unmapped role.
The additional-image editor uses production AMD/template assets in Storybook.
The media synchronization switch, gallery selection, roles, insertion rules and
the manually requested scan remain available in read-and-write mode. This switch
also controls manual product imports; only automatic cron entry points and their
scheduling configuration are disabled or hidden in that mode.
