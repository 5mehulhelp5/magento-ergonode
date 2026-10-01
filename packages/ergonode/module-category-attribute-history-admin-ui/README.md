# Ergonode_CategoryAttributeHistoryAdminUi

## Responsibility boundary

Read-only Magento Admin viewer for CategoryAttributeHistory. Owns the standalone
`ergonode/category_attribute_history/index` screen, operation/state GET endpoints,
authenticated actor adapter and history sidebar contribution. It consumes the history
read and actor contracts and the existing CategoryConsumerAdminUi navigation extension.
CoreAdminUi supplies workspace primitives, icons and section navigation.

The screen offers operation pagination, historical attribute states, change details,
search and a link to current category attribute mappings when that navigation entry
is authorized and installed. It does not embed into category tree history, mutate
mappings, import source data, restore old states, own history storage or define ACL.
All three controllers require `Ergonode_CategoryAttributeHistory::view`, owned by the
neutral history module. The source capture adapter is optional and is not a dependency.

The attribute columns show the state after the selected operation, including
unmapped attributes. Saved one-sided mappings have a draft label and their changes
appear in the operation details. Older entries without recorded draft status are
explicitly identified; a zero change count describes what was recorded and does
not establish that no draft mapping changed.

Attributes added by the selected operation show a New badge, including newly
downloaded unmapped Ergonode attributes in the left column, with access to their
change details. Draft mappings have a persistent violet background on either side;
the background does not depend on that operation changing the draft. Removal and
exclusion styling retains priority over the draft background.

The viewer uses the category group's current
mapping item for its current-state link. Its optional sidebar contribution depends
on CategoryAttributeAdminUi through Composer, module sequence and the
`ergonode.category.attribute.mapping.sidebar` layout container.
The actor preference is Admin-only. Runtime has no dependency on product modules.

The production CSS and AMD scripts are exercised by the module Storybook stories,
including empty, failed, unchanged and mixed change states, keyboard navigation,
request failures, pagination and accessibility. Unit tests cover authorized navigation,
historical deep links, script-safe JSON and absence of an unauthorized mapping link.

The category mapping screen embeds CoreAdminUi's shared history panel below the
Magento attribute list. This module supplies category history routes and the view
ACL; CoreAdminUi supplies the same template, CSS, cards and pagination as products.
The full category history remains separate and read-only. Loading the mapping page
refreshes its operation summaries; no additional state snapshots are fetched there.

The Operations header owns the checked-by-default “Only changes” filter. The initial
state payload contains changed entities and the parents/mapping counterparts needed
to present them. Switching the filter requests the selected state through the existing
authenticated GET endpoint (`changes_only=0` restores the full viewer). The read
contracts and stored history remain unchanged: filtering reduces browser transfer
and rendering, while the server still reads/reconstructs the historical state.
Failed requests retain the displayed state and restore its checkbox value; newer
requests take precedence over late responses.

## History configuration form

Presents the `Attribute history` group in Ergonode's Categories configuration:
recording, independent automatic cleanup, positive retention days and cron schedule.
`CategoryAttributeHistory` owns defaults, config reads, scheduling and deletion; this module owns
only the fields and validation. The form uses the existing CoreAdminUi cron validator.
The cleanup comment explicitly describes permanent deletion of existing old records.

History is opened from the mapping screen's right sidebar. This module does not
register a main-menu or section-navigation destination; direct history URLs remain valid.
