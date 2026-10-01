# Ergonode_ProductAttributeHistoryAdminUi

[Product module responsibility map](../../../../docs/ergonode-product-modules.md).

## Responsibility boundary

The read-only product attribute and option history screen, operation pagination, change
presentation, navigation and authenticated admin actor.

Consumes ProductAttributeHistory's query and actor contracts. Storage, differ and
capture belong to that runtime; neutral mapping edits belong to ProductAttributeAdminUi.
It does not depend on the product-attribute consumer or restore mappings.

## Data, configuration and removal

No owned schema or uninstall handler. Runtime settings belong to ProductAttributeHistory. Uses the history runtime's view
ACL. Removing the viewer leaves stored history intact.

## Dependencies and extension points

Reads saved historical states, not a reconstruction from current attributes. The
current-state link uses CoreAdminUi's available mapping destination and its
authorization.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-product-attribute-admin-ui](../ProductAttributeAdminUi/README.md)
- CoreAdminUi assets and navigation are provided transitively by ProductAttributeAdminUi.
- [ergonode/module-product-attribute-history](../ProductAttributeHistory/README.md)

Ownership and wiring: [etc/adminhtml/di.xml](etc/adminhtml/di.xml).

## Operational details

Owns the three-column product attribute mapping history screen, operation
pagination, change details, navigation and its Storybook contract. Uses
`ProductAttributeHistory` read/actor APIs and `CoreAdminUi` styles/navigation.
The base history module owns storage and ACL; this area module owns no schema.

Ergonode shows only unmapped or excluded attributes as plain muted cards, except
for parent cards that indicate option changes.
Magento shows historical mappings and change highlights. Disconnected rows keep
the orange color and use diagonal stripes. Only the former Ergonode name and code
are struck through on disconnected rows; whitespace, the arrow and Magento labels
remain undecorated. The disconnected indicator uses the same crossed-chain artwork
as category tree history, stored locally without a category-module dependency. The first two headers contain no
date. “Current state” links to the mapping destination supplied by CoreAdminUi's
attribute navigation, subject to that destination's existing authorization.

History is read-only. It cannot save/restore mappings or import attributes.

The production AMD modules render the screen in Magento and Storybook using
the same CSS. Story fixtures demonstrate historical states only; they are never
inserted into application data. All values in the view are rendered as text or
escaped HTML; the initial JSON escapes HTML-sensitive characters.

Configures the shared CoreAdminUi history panel below the Magento attribute list
in the existing right column of product attribute mapping. CoreAdminUi owns its
block, template, operation cards, styles and pagination; this module owns the routes,
ACL and layout contribution. Both the panel and full viewer use the shared operation
cards and lower blue selection accent. The panel loads summaries in pages of ten
and links to historical states. Saving or refreshing mapping reloads the page and
therefore its history. Storage and capture remain in ProductAttributeHistory.

Historical options appear directly below their parent attribute in each column, with
an indent and the same change cards. There is no separate options screen or Options
button. Unmapped/excluded source options keep their parent visible even when the
attribute itself is mapped. Search includes attributes and options, retaining the
parent when only an option matches. Operation details include parent codes,
before/after values and Show option navigation to the nested card. Old operations
explicitly report unavailable option history and never substitute live values.

Unchanged options are collapsed and omitted from the initial browser payload. Expanding
an attribute fetches its historical option pair through the existing authenticated state
route and caches it for the selected operation. Changed options remain visible when the
attribute is collapsed. Failed requests offer retry; late responses cannot replace a newer
operation. Search asks the same route for matching parent codes without loading option rows.
The history storage contract still reads its complete immutable JSON snapshot on the server;
this viewer optimization reduces browser transfer and rendering, not database snapshot reads.

Option changes also highlight the parent attribute, including its mapped counterpart.
A single option change style is inherited; mixed styles use the generic changed style.
Direct attribute changes take precedence. Child changes do not strike through an unchanged
parent mapping or mark the parent inactive. The change indicator opens the underlying option
change when the attribute itself did not change.

The workspace constrains the history grid to the available page height. Each attribute
column and the operation list scroll independently beneath fixed headers. Long option
lists do not expand the grid beyond the viewport. The option disclosure uses an inline
Options label, unchanged-option count and rotating SVG chevron, with keyboard focus
and reduced-motion support.

The Operations header owns the checked-by-default “Only changes” filter. The initial
state payload contains changed entities and the parents/mapping counterparts needed
to present them. Switching the filter requests the selected state through the existing
authenticated GET endpoint (`changes_only=0` restores the full viewer). The read
contracts and stored history remain unchanged: filtering reduces browser transfer
and rendering, while the server still reads/reconstructs the historical state.
Failed requests retain the displayed state and restore its checkbox value; newer
requests take precedence over late responses.

## History configuration form

Presents the `Attribute and option history` group in Ergonode's Products configuration:
recording, independent automatic cleanup, positive retention days and cron schedule.
`ProductAttributeHistory` owns defaults, config reads, scheduling and deletion; this module owns
only the fields and validation. The form uses the existing CoreAdminUi cron validator.
The cleanup comment explicitly describes permanent deletion of existing old records.

History is opened from the mapping screen's right sidebar. This module does not
register a main-menu or section-navigation destination; direct history URLs remain valid.
