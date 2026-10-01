# Target Ergonode GraphQL capability matrix

This matrix records the authenticated introspection result used to gate the
publisher implementation. The captured contract hash is
`faeeedb576fed1212fb1214183996016b9fac021c160eaebeac3ac77869a9156`.

The snapshot deliberately omits the endpoint, API key, descriptions and entity
data. It contains the complete query and mutation name index plus the detailed
signatures and related types needed by the publisher domains.

## Capability summary

| Domain owner | Read contract | Create contract | Update/reconcile contract | Delete contract | Gate result |
| --- | --- | --- | --- | --- | --- |
| `Ergonode_AttributePublisher` | `attribute`, streams | 13 typed `attributeCreate*` mutations | name, metadata and type-specific settings | attribute and metadata delete | Pass |
| `Ergonode_AttributePublisher` options | `attributeOptionList` | select and multiselect add/set mutations | translated option names, sets and custom fields | option and custom-field delete | Pass |
| `Ergonode_CategoryPublisher` | category, attribute list and streams | `categoryCreate` | name, typed values and category attributes | category and value delete | Pass |
| Category trees (unsupported) | tree and streams | none | none | none | **Blocked** |
| `Ergonode_TemplatePublisher` sections | section and list | none | none | none | **Blocked** |
| `Ergonode_TemplatePublisher` templates | template and list | `templateCreate` | `templateSetName` only | `templateDelete` | **Blocked for structure** |
| `Ergonode_MultimediaPublisher` folders and metadata | multimedia and folder queries/streams | multimedia and folder create | move, replace, name, alt, title and typed values | multimedia and folder delete | Partial |
| `Ergonode_ProductPublisher` | product queries and streams | simple, variable and grouping create | typed values, status, template, variants and grouping children | product, values, variants and children | Pass |
| `Ergonode_ProductCategoryPublisher` | product category assignments | none | add product category assignments | remove product category assignments | Pass |

## Mandatory gate decisions

### Category trees

The target exposes `categoryTree`, `categoryTreeStream` and
`categoryTreeDeletedStream`, but no category-tree mutation among all 109
mutation fields. Root tree creation, rename, leaf insertion, moves, ordering
and removal therefore cannot be implemented with the target GraphQL contract.

No automatic category-tree publisher module or write runtime is registered. A
future schema refresh must show the required write operations before a
GraphQL-only automation can be introduced. The optional Admin UI adapter may
use the authenticated Ergonode REST tree endpoints, but only after an explicit
operation-time login; its session token is not available to cron, CLI or the
catalog publisher.

### Templates and sections

The target exposes only `templateCreate`, `templateSetName` and
`templateDelete`. Sections are read-only. There are no mutations for section
creation or update, attaching or detaching sections and attributes, or
reordering template structure.

Template definition lifecycle is technically writable, but the planned
Magento attribute-set reconciliation is not. `Ergonode_TemplatePublisher`
remains blocked until the complete structure can be written through GraphQL.

### Multimedia transfer

Folder lifecycle, resource lifecycle and translated metadata are present.
However, `MultimediaCreateInput` contains only `name` and optional
`folderPath`; `MultimediaReplaceInput` contains only `path`. The schema has no
upload or binary scalar/argument, and the current `Ergonode_Core` client sends
JSON requests only.

Consequently, folder and metadata planners may be designed, but binary create
and replace execution remains blocked until Ergonode documents and proves the
associated transfer protocol. The inspected `ergonode-automation` project has
no multimedia uploader that could establish this missing contract.

## Ready implementation scopes

- Attributes and options have complete create, change and guarded delete
  primitives for the planned reconciliation.
- Category entities have create, translated value, attribute and delete
  primitives. This does not include their placement in a category tree.
- Products have create and granular reconciliation primitives for values,
  status, template assignment, grouping children and variable variants. The
  optional `Ergonode_ProductCategoryPublisher` owns the separate category
  assignment primitives.
- Stable identifiers are code-based for attributes, categories, templates and
  sections, and SKU-based for products. These identifiers must remain immutable
  in publisher plans.

## Reproducing the audit

With the target connection configured in Magento and the environment running:

```bash
ddev exec php dev/tools/ergonode-publisher-schema-audit.php
```

The command performs one read-only authenticated introspection request and
regenerates
`Test/Contract/Fixture/ergonode-schema.json`. A changed `contractHash` requires
reviewing this matrix and all previously blocked gates.
