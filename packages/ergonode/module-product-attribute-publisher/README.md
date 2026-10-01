# Ergonode_ProductAttributePublisher

[Product module responsibility map](../../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Magento-to-Ergonode product attribute/option definition preparation and publication,
plus mapped attribute values for published products.

ProductAttribute supplies neutral mappings and metadata; AttributePublisher reconciles
remote definitions; ProductPublisher owns product identity, orchestration and jobs.
Definition publication is explicit and separate from publishing a product.

## Data, configuration and removal

No owned tables, configuration paths, import cursor or uninstall handler. Removing the
adapter preserves shared mappings, Magento EAV and already published Ergonode
definitions; base product publication uses its empty attribute source.

## Dependencies and extension points

Implements ProductPublisher's `ProductAttributePublicationSourceInterface`; exposes
definition publisher/state-builder contracts. It checks the mapped SKU definition
through AttributePublisher without requiring ProductAttributeConsumer or a consumer
snapshot.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-attribute-publisher](../../../../app/code/Ergonode/AttributePublisher/README.md)
- [ergonode/module-product-attribute](../ProductAttribute/README.md)
- [packhauer/module-unit-attribute](../../../../app/code/PackHauer/UnitAttribute/README.md)
- [ergonode/module-product-publisher](../../../../packages/ergonode/module-product-publisher/README.md)

Ownership and wiring: [etc/di.xml](etc/di.xml).

## Operational details

Owns outbound adaptation of Magento product attributes to Ergonode: attribute
and option definitions, plus mapped values attached to published products.
It depends on ProductPublisher as an optional extension; the base product
publisher does not depend on this module or the attribute consumer.

`AttributeSourceStateBuilderInterface` prepares attribute definitions for the
existing AttributePublisher reconciliation contracts. Definition publication
is a separate operation and is not automatically run before product creation.

`ProductAttributePublicationSourceInterface`, owned by ProductPublisher, is the
extension point for product data. This module reads complete mappings and
option IDs through ProductAttribute, requests only mapped Magento fields, and
builds translated product values. ProductPublisher limits mappings to the
product attribute set before invoking the source. For each language, outbound values come from the first non-admin store view
in the active mapping order. Store 0 is never an independent publication source.
Magento loads each store product with native EAV inheritance: a store override
wins, while “Use Default” resolves to the value from store 0. An empty value in
the chosen store keeps its explicit translation-clear intent; later stores do
not supply a fallback. Option mapping failures retain their existing behavior.

This module owns no mapping tables, product identities, import cursors, inbound
snapshot refresh or Admin UI. Removing it restores ProductPublisher's empty
attribute source: products can be published with the base identity/template
payload, without mapped attribute values. Shared mappings remain owned by
ProductAttribute; disabling this adapter does not delete them or Magento EAV.

AttributeDefinitionPublisherInterface and OptionDefinitionPublisherInterface
prepare and publish definitions, individually or in batches. They use
AttributePublisher's synchronization contracts and apply rate-limit handling.
They do not refresh inbound snapshots or build UI payloads/notices.
ProductAttributePublisherAdminUi adds publication actions to the neutral
ProductAttributeAdminUi editor, independently of the product attribute consumer.

A saved Magento SKU mapping is included even after the mode for new bindings
changes, so existing assigned identities can still synchronize their SKU copy.
Its remote definition is checked through AttributePublisher's public read API:
it must remain a unique Global Text attribute. No consumer snapshot is required.

Creating a select or multiselect definition includes all Magento source options,
just as boolean definitions include both values. The single and batch Admin
actions use the same preparation contract. Publication uses non-destructive
`create_only` synchronization, so repeating it can complete missing options on a
compatible existing attribute. Publishing definitions does not save option mappings.

## System option translations

This adapter owns the publication dictionary for Magento boolean, product status
and visibility options. It is independent of the Admin user's locale and installed
Magento language packs. Both full attribute publication and individual/batch option
publication use `OptionSourceStateBuilder`; individual preparation receives the
Magento attribute code, Ergonode attribute code and Magento `option_<value>` identity.
Submitted UI labels do not determine the published system option name or code.

Boolean values 1/0 publish Yes/No labels. Product status values 1/2 also publish
Yes/No labels, retaining the canonical `enabled`/`disabled` option codes. Visibility
retains Magento's four meanings: Not Visible Individually, Catalog, Search and
Catalog, Search. The attribute value scope does not change with label translation.

`SystemOptionDictionary` contains six labels per language for: an, ast, az, be, bg,
br, bs, ca, co, cs, cy, da, de, dsb, el, en, es, et, eu, fi, fo, fr, fur, fy, ga,
gd, gl, hr, hsb, hu, hy, is, it, ka, kk, kw, lb, lt, lv, mk, mt, nl, no, nn, oc,
pl, pt, rm, ro, ru, sc, se, sk, sl, sq, sr, sv, tr and uk. Serbian additionally
supports Latin script (`sr_Latn`); its default is Cyrillic. Norwegian Bokmål (`nb`)
uses `no`. Montenegrin (`cnr`) shares the Serbian Latin labels, or Cyrillic
when explicitly requested with `cnr_Cyrl`. Region variants reuse their language's labels; hyphens and underscores
are accepted. Unlisted languages use English labels under the original target
language code. Only languages in active language/store mappings are published.

Explicit requested option codes take priority, followed by saved Magento/Ergonode
option mappings. New system options use fixed codes (`yes`, `no`, `enabled`,
`disabled`, `not_visible_individually`, `catalog`, `search`, `catalog_search`).
This preserves mapped historical codes when adding translations. It does not infer
ownership of existing unmapped remote options or migrate their codes. Previously
published options with other codes must be mapped before republishing to update them.

New codes generated from Magento labels are rejected when another option of the
same attribute has the same effective Ergonode code, including when the options
are published in separate batches. The adapter does not choose a winner or append
an ID automatically; existing explicit and saved codes remain unchanged.

Other source models retain their own option labels and provided store labels; the
system dictionary does not translate arbitrary merchant text. This change does not
add a database migration, automatic remote publication or new module dependencies.

## Original values for validation

The source passes original values from the selected non-admin store for each published
language to ProductPublisher's attribute validators. These are the same effective EAV
values used for conversion; admin values and later stores for the same language cannot
replace them. Explicitly cleared translations have no original value in this payload.

Attribute publication requests neutral complete mappings for the `publish` direction. Mappings with unavailable value adapters are omitted before resolving values and validators; omission does not generate an empty value or a clear operation.

## Required-field preflight data

The source supplies publication mappings plus diagnostic entries for unavailable
adapters (`publication_error`). Such entries never produce values or clear remote
fields. ProductPublisher uses them to distinguish missing mappings from unavailable
adapters for required Ergonode template attributes. Converted values and conversion
errors retain their existing ownership here; required policy belongs to ProductPublisher.

## Incomplete product option mapping on outbound publication

For each product, the source collects missing Magento option IDs from all mapped languages. If any ID of a mapped select or multi-select attribute lacks an Ergonode option code, the whole attribute is omitted from that product in every language, including clear intent. One warning per omitted attribute lists the SKU, Magento attribute code and distinct missing IDs. An unavailable publication adapter or other local value conversion or validation error also omits its attribute with a warning. Other attributes continue; ProductPublisher reports missing required template values separately and sends the available product state.
