# Ergonode_ProductAdminUi

## Responsibility boundary

Owns the direction-neutral Ergonode > Products > List workspace, catalog filters,
sorting, pagination, selection and browser-controlled batches. Also owns presentation
of the shared identity field and SKU-mode configuration. Product owns the catalog
read contract, identity records and SKU policy. This module owns no synchronization
operation, table or stored job.

`GridActionProviderInterface` supplies an action configuration. Directional AdminUi
providers may supply `button_label` and `icon_class` for compact row buttons; `label`
remains the full accessible name, tooltip and bulk action label. Providers without
these presentation fields retain their full labels. A provider can mark its action
`primary` to make it the split button default; otherwise the first permitted action
is used. The menu contains all permitted actions and the product link. With no
directional action, only the product link is rendered. Popovers keep menus visible
outside the scrolling table and support keyboard navigation and Escape.
Directional AdminUi modules register providers in `GridActionPool` using lazy proxies and required module
names. The pool checks enablement before resolving the provider; providers check ACL
and readiness. The neutral grid has no dependency on Consumer or Publisher and no
query against their tables. With neither direction enabled it still supports browsing,
filters, selection and product links. Grid access requires `Ergonode_Product::products`.

`ProductSelectionInterface` validates explicit IDs or snapshots all matching products
with exclusions. Applying filters clears selection; pagination and sorting retain it.
The header checkbox supports page selection, all results and clearing the selection.
Attribute-set and product-type filters use catalog-wide options; sets show labels.

## Browser process

An action sends sequential POST batches of at most 50 IDs to its own endpoint.
An optional `preflight` action setting names an AMD factory and its configuration.
The factory supplies asynchronous `ensure(): Promise<boolean>`, run before opening
progress and before each batch. A false result before starting preserves selection
and restores controls without sending products. Directional modules own the check;
the grid has no dependency on their authentication implementation.
Pause and stop finish the in-flight request; resume never repeats completed batches.
Closing the popup stops after the current batch. Closing the tab ends orchestration.
Product results and previous batches appear only in the popup. Transport failures
report an unconfirmed, possibly partial batch; there is no automatic replay.
Each direction supplies its own translatable title, labels and readiness notice.

## Dependencies and data

Product provides identity and catalog APIs. CoreAdminUi provides styles and progress
presentation. Directional modules depend on this module, never the reverse.
No owned database table. `ergonode_products/identity/sku_mode` and
`ergonode_products/identity/magento_attribute` edit Product's settings. The
identity form lists eligible global, unique text product attributes from Magento
metadata for mapped mode, including EAV and registered static columns. The
selection is validated against the same policy when saved. A blank or historical
shared mode requires an explicit selection before a new binding is created.
Disabling this UI retains identities and configuration.

Mapped mode shows the selected attribute only while that mode is selected. The
Magento product form previews its value as the native Ergonode SKU for an unbound
product, separately from the saved identity. Assigned mode hides the mapped SKU
selector and shows local completeness of the required Magento `sku` attribute
mapping; its note points administrators to Ergonode > Products > Attributes. The
check uses Product's identity-mode contract and does not query Ergonode remotely.
Ergonode settings, template placement and the product value are checked by the
publication source.

## Verification

`Test/Storybook/ProductPublication.stories.js` uses production templates, CSS and AMD
with a stubbed transport. It covers independent directions, no directions, pagination,
filtering, selection, progress, pause/resume, stop and failure details. Unit tests cover
selection validation and provider isolation. External synchronization is exercised only
with an explicitly selected test product; normal tests use fixtures.

Contributes the ACL-protected List destination to the shared Product navigation group
through global DI. The route and permission stay owned by ProductAdminUi and Product.

## Workspace connection requirement

Operational pages opt into CoreAdminUi's `ergonode_connection_required` layout
handle. Invalid active connection configuration replaces the entire content area
before workspace templates or extension initializers render. The shared notice
uses the workspace width and offers a short configuration prompt with an
ACL-aware configuration button. It omits individual validation details and retry controls. Both configured read and read-and-write modes permit
opening the workspace; operation-specific checks remain with their owners.
This also applies when editing locally stored mappings or browsing local products.
No data or remote permissions are changed by this presentation requirement.
