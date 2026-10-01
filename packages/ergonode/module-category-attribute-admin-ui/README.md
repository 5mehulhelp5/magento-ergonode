# Ergonode_CategoryAttributeAdminUi

## Responsibility boundary

Neutral Magento Admin category attribute and option mapping editor: routes, blocks,
manual mapping, compatibility hints, option suggestions and the history sidebar.
CategoryAttribute owns validation, mapping persistence, visibility and Magento metadata.
Attribute supplies the same compatibility map used by server validation; CoreAdminUi
supplies the shared production templates and behavior. This fixes the missing
compatibility configuration that rejected `color` (select) → `test_category_color` (select).

This module owns no tables, source registry, synchronization settings, remote writes
or category value updates. Neither category-attribute consumer nor publisher is required.
The existing route, layout container and ACL identifiers remain stable. The base
CategoryAttribute module now defines those existing ACL resources; no role or mapping
records are migrated. Neutral saves reject unresolved creation requests on both sides.

## Extension contracts

- `Api/SourceMetadataProviderInterface` contributes source attributes and options through
  the `Model/Mapping/SourceMetadata` provider list. With no adapters, existing mappings
  remain editable using their persisted source types/codes and current Magento metadata.
- `Model/Mapping/SaveContext::execute` encloses preparation and persistence. The consumer
  adapter decorates it with the existing synchronization lock and snapshot backfill.
- Attribute and option savers expose `prepareMappings` inside that context. Optional
  direction adapters resolve pending definitions/options before neutral validation.
- The option saver resolves both submitted cards again from the current source and
  Magento providers. Labels, types and option membership supplied by the request are
  never treated as authoritative.
- `MappingCapabilities::getConfig` supplies optional URLs and creation capabilities.
- `ergonode.category.attribute.mapping.sidebar` hosts optional history contributions.

ConsumerAdminUi adds imported metadata, download/delete-snapshot endpoints, Magento
option creation, synchronization and Categories navigation integration. PublisherAdminUi
adds publication preparation and metadata using its own write-scope reader; it has no
category-attribute consumer dependency. Runtime publication remains in the publisher.

## Supported compositions

| Installed category-attribute adapters | Behavior |
| --- | --- |
| None | Neutral editor and saved mappings; no synchronization or publication |
| Consumer | Imported metadata and inbound actions; save then snapshot backfill |
| Publisher | Publication metadata/actions and neutral persistence; no inbound backfill |
| Both | Shared editor, independently contributed actions and inbound save context |

Publisher packages remain in `packages/backlog/ergonode`. Tests must cover their contracts
there without enabling them in the working installation or making real remote mutations.
Storybook stories belong here and load CoreAdminUi production AMD and CSS.

## Workspace connection requirement

Operational pages opt into CoreAdminUi's `ergonode_connection_required` layout
handle. Invalid active connection configuration replaces the entire content area
before workspace templates or extension initializers render. The shared notice
uses the workspace width and offers a short configuration prompt with an
ACL-aware configuration button. It omits individual validation details and retry controls. Both configured read and read-and-write modes permit
opening the workspace; operation-specific checks remain with their owners.
This also applies when editing locally stored mappings or browsing local products.
No data or remote permissions are changed by this presentation requirement.
