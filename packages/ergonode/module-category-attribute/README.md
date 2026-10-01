# Ergonode_CategoryAttribute

## Responsibility boundary

Direction-neutral category attribute and option mappings: validated persistence,
drafts, ordering, visibility, mapping read models and Magento metadata.
Callers supply resolved source metadata and existing Magento attribute/option
identifiers. This module never downloads source data, creates Magento definitions
or writes category values. CategoryAttributeConsumer performs those operations
and uses this module's API to read and save mappings.

## Data and lifecycle

Owns `ergonode_category_attribute_mapping`, `ergonode_category_option_mapping`,
and `category_attribute`/`category_option` visibility in Core's shared storage.
The existing table names, columns, constraints and identifiers are retained when
ownership moves from CategoryAttributeConsumer. No data patch, rename, copy,
backfill or configuration-path migration is introduced.

Uninstall removes these mapping tables, their visibility rows and the three
attribute mapping ACL rules (including their stable `Ergonode_CategoryConsumer`
identifiers), preserving
category entities, EAV attributes/options, imported snapshots and synchronization
settings. CategoryAttributeConsumer owns `ergonode_category_attribute` and
`ergonode_category_entity_snapshot`; removing that consumer preserves mappings.

## Contracts and dependencies

- `MappingReaderInterface`: persisted attribute rows, option rows and batched counts.
- `AttributeMappingWriterInterface`: validate resolved metadata and save attribute
  mappings plus visibility. Validation allows the consumer to reject an invalid
  request before creating pending Magento attributes.
- `OptionMappingWriterInterface`: save resolved option mappings and visibility;
  rejects non-option attribute pairs and Magento option identifiers that do not
  belong to the mapped target attribute. Callers remain responsible for resolving
  source option codes because this neutral module owns no source registry.
- `MappingStateBuilderInterface`: compose mapping rows with caller-supplied
  metadata, retaining drafts and placeholders for unavailable attributes.
- `MagentoAttributeProviderInterface` and `MagentoOptionProviderInterface`: target
  metadata and visibility, without source snapshots.
- `MappingPolicyInterface`: mapping eligibility/requirements. The default policy
  uses neutral exclusions; the optional consumer supplies its synchronization
  rules through DI without a reverse dependency.

Direct project dependencies: Attribute supplies type compatibility/resolution;
Core supplies shared mapping persistence and visibility. There is no dependency
on CategoryConsumer, CategoryAttributeConsumer, Product modules, history or UI.
The unused Category dependency from the backlog skeleton is removed.

Consumer orchestration retains its synchronization lock, definition creation and
immediate snapshot backfill. Its optional Admin adapter decorates the neutral editor save context with that orchestration.
Neutral saves do not trigger inbound backfill themselves. Synchronization policy
paths and manual creation defaults remain owned by CategoryAttributeConsumer.

## Validation

Unit tests cover mapping validation, transaction behavior, missing metadata and
lifecycle ownership. Integration covers existing mapping identifiers, option
relations, visibility and Magento DI. The category-family boundary test verifies
that neutral mappings and inbound snapshots have different owners.

## History extension

[CategoryAttributeHistory](../CategoryAttributeHistory/README.md) observes neutral
attribute saves through the writer API. It owns its own immutable records and
never adds a dependency from this module to history or inbound synchronization.

## Automatic option mapping

`OptionAutoMatcherInterface` suggests category option pairs from caller-supplied
metadata for a saved, complete option-compatible attribute mapping. Source codes
match target labels before source-label fallback; normalization and native boolean
values follow the product mapping behavior without product visibility rules.
Each candidate is used once and results retain source order. Suggestions do not
persist mappings, create definitions, download snapshots or update category values.

## Neutral editor and ACL

CategoryAttributeAdminUi owns the optional neutral editor. This runtime owns the existing
`Ergonode_CategoryConsumer::category_attribute_mapping`, `category_attribute_save` and
`category_attribute_refresh` ACL definitions, preserving identifiers and existing grants.
The ACL ownership move changes no persisted data. Consumer and publisher extend the
editor independently; neither is required by this runtime or its Admin UI.
