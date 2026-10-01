# Ergonode_ProductPublisher

Registered source in `packages/ergonode/module-product-publisher` (enablement is controlled by `app/etc/config.php`).

[Product module responsibility map](../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Magento product loading, product-specific mutation planning/reconciliation, dependency
ordering within explicit selections, identity preparation, synchronous publication batches and the last per-product publication result.

Product owns identities and SKU mode; Publisher handles generic mutation execution;
optional adapters provide attribute values, templates, categories and Magento-specific
composite relations. This runtime never publishes missing prerequisite definitions
automatically.

## Data, configuration and removal

Owns publication contracts, ACLs and `ergonode_product_publication_result`. Product identities remain owned by Product.
No queue, consumer, recovery cron or publication-job tables remain in the current runtime.

The `RemoveQueuedProductPublication` schema patch removes the retired job/item tables,
only `ergonode.product.publish` queue messages/statuses/queue records and only
`ergonode_product_publication_recovery` cron records. It is idempotent and preserves
products, identity mappings, other queues and configuration. Take a database backup
before deployment. Retired history is deleted; rollback requires the old code and
that backup. Pending legacy work is not transferred or automatically published.
The old cleaner remains solely for this migration and uninstall compatibility.
The schema declares the last-result table. Historical whitelist entries remain
for Magento declarative removal of the retired tables. Magento intentionally preserves historical whitelist entries on regeneration.

## Dependencies and extension points

The base type map accepts Magento simple/virtual products. Variable/grouping mutation
support is generic infrastructure; configurable/grouped/bundle Magento loading requires
the corresponding type adapter. The base attribute source is empty and the base template
code is `default`.

Direct dependencies ([composer.json](composer.json)) declare the existing product identity, mutation execution and language contracts:

- [ergonode/module-product](../module-product/README.md)
- [ergonode/module-publisher](../module-publisher/README.md)
- [ergonode/module-language](../module-language/composer.json)

Ownership and wiring: [etc/di.xml](etc/di.xml), [etc/db_schema.xml](etc/db_schema.xml), [Setup/Uninstall.php](Setup/Uninstall.php).

## Publication catalog filters

The catalog page includes distinct attribute sets and product types used across the
entire Magento product catalog. These options remain available during pagination,
search and empty filtered results. Attribute sets expose their IDs and names;
attribute-set ID and product-type filters use exact matching for both grid pages
and all-results publication snapshots. Options are read without loading product models.

## Operational details

This module publishes Magento product commands to Ergonode through GraphQL
mutations. Magento and the local mapping tables are authoritative for outbound
publication. It owns the Magento product source, dependency ordering, identity
preparation and synchronous product publication batches. The base publisher does not
read or publish product category assignments.

Product-specific planners and builders live here. Ergonode_Publisher composes,
capacity-plans and executes the generic mutation operations.

## Scope

- plan simple, variable and grouping product mutations; Magento type support
  beyond simple/virtual comes from the optional type adapters;
- create products with the `default` template and set localized statuses;
- publish translated attribute values supplied by the optional attribute source;
- apply explicit value-clear intents;
- set variable bindings and add variants;
- add grouping children and update their quantities after an already-existing
  child response;
- delete a locally mapped product only in explicit reconcile mode.

The base Magento source reads product identity, type and attribute-set IDs. It
uses the `default` template and does not load mapped EAV values or localized
product copies when no attribute source is installed.

`ProductAttributePublicationSourceInterface` is implemented by the empty base
source. `Ergonode_ProductAttributePublisher` replaces it to contribute mappings
and translated values. Only mapped Magento attributes are added to the product
collection, and only attributes assigned to each product's attribute set are
published. Product publication never invokes another domain publisher to
create remote prerequisites. The template and any referenced attributes must
already exist in Ergonode.

Install `Ergonode_ProductTemplatePublisher` to replace the `default` code with
the active Ergonode template mapped to each Magento attribute set. With that
optional adapter enabled, a missing mapping blocks the affected product before
publication.

## Runtime contract

- A single local identity lookup for the Magento product IDs decides create
  versus update. ergonode_product_mapping is the source of that decision.
- The shared `Ergonode_Product` contract owns the configured identity mode.
  New bindings require `assigned` or `mapped`. Historical `shared` bindings
  continue using their persisted Magento SKU. In assigned mode a
  successful batched create returns the native Ergonode SKU, which is persisted
  before subsequent product mutations.
- In mapped mode the configured Magento product attribute supplies the remote
  SKU for product mutations. A value that differs from an existing immutable
  binding blocks publication; successful new publications persist that binding.
- In assigned mode the selected Ergonode template must include the complete
  Magento `sku` mapping, and the source must produce that exact SKU value before
  any product create. Remote Global Text scope and uniqueness are checked when
  attribute mappings load; an invalid SKU mapping is marked unavailable for
  publication. A missing publisher adapter, template placement or value skips
  the assigned product locally without blocking mapped products.
- Missing local identity causes create. Existing local identity causes update.
  A not-found result from an update operation starts the recovery path and
  allows the product to be recreated safely.
- If create returns an explicit already-existing conflict, the conflict is
  accepted for that SKU and the missing base update commands are sent. Other
  products in the same response continue independently.
- Add operations accept only their narrow already-existing conflict as
  idempotent. An existing grouped child triggers a quantity-update mutation.
  Other validation, authorization and transport failures keep their normal
  failed or unresolved classification.
- Template, status, value and relation operations are built directly from
  Magento state. Without the optional template adapter, template operations use
  the `default` code. Explicit value clear and delete commands remain separate
  intents.
- Multimedia values are sent like other mapped values. Missing remote media is
  reported from the corresponding mutation result instead of a preflight query.

Product creates precede the remaining operations. Subsequent operations keep each
product together (template, statuses, values and relations) before the next product.
The initial plan is capacity-validated before any create. Creates are dispatched
in a separate stage, followed by a batched comparison read for confirmed new
products and the remaining attribute/relation operations. Each stage uses the
shared mutation planner's operation-count and byte-size limits (currently at most
50 mutations), preserving per-operation SKU metadata for result correlation.
Error-driven fallbacks retain their existing behavior.

Internal correlation uses prefixed string keys, so a numeric native Ergonode SKU
cannot be coerced into an integer PHP array key. No product publication cursor,
remote snapshot or generated mutation is persisted.

## Optional category publication

Install `Ergonode_ProductCategoryPublisher` to decorate base product state with
Ergonode category codes and append `productAddCategories` and
`productRemoveCategories` operations after successful base publication. That
module owns the remote category-state lookup, mapping completeness checks and
`ergonode_products/publication/category_mode` behavior.

`ProductStateDecoratorInterface` lets optional modules retain their additional
state while the product workflow adds identity or dependency decorators. With
the category publisher disabled, the same product source and synchronizer work
without category codes, category reads or category mutations.

Optional outbound domains extend product loading and identity preparation via
`ProductSourceDecoratorInterface` and `ProductPreparedStateDecoratorInterface`.
Product-attribute value types extend the source through
`ProductAttributeValueResolverInterface` and
`ProductAttributeSourceValidatorInterface`; the base module contains no
category-reference implementation.

See [PLAN.md](PLAN.md) for implementation history.

## Module independence

This module has no code, DI or Composer dependency on `ProductAttribute` or
`ProductAttributeConsumer`. Outbound attribute support depends on this module,
not the reverse. Existing assigned identities are checked through Product's
shared mode-support contract, even when their remote SKU is already known.
An ambiguous assigned-SKU create remains an attention result and is not retried
automatically. The create mutation omits both the native SKU and the separately
mapped Magento SKU attribute, which is written only after a native SKU was
returned and bound. An ambiguous create therefore cannot reliably be found by
Magento SKU; its message requires independent remote verification and native SKU
binding reconciliation before another attempt. This module does not assume an
importer is installed.

Uninstall removes leftover legacy job/queue/recovery records and current authorization rules. It owns no configuration paths: Product's
identity mode and extension settings such as `publication/category_mode` remain
untouched.

## Admin-driven distribution

The neutral Product module supplies catalog pages and selection snapshots to
ProductAdminUi. This runtime owns `ProductPublicationBatchInterface`, which
accepts 1–50 product IDs, validates immutable identities, resolves current SKUs and
returns one correlated result per requested product. The Admin UI owns orchestration;
this module performs synchronous work only. Mutation requests retain the separate
50-operation and byte-size limits.

Only explicitly requested products are published. References outside the batch use
existing identity mappings; absent remote prerequisites are reported without implicit
publication. Selected dependencies inside a batch retain dependency-first ordering.
There is no automatic cross-batch prerequisite scheduling or request replay.

Failed product mutations retain their operation, SKU, attribute, language, result
status and API error messages/codes/paths in the publication message. Unspecified
remote errors explicitly state that the API did not provide the cause. Each
reported failure includes a log reference correlated with an error entry in the
Magento log, including alias and attempt count. Reports omit mutation inputs,
response data and arbitrary debug extensions. The same reporter handles failed
assigned-SKU creation and subsequent product mutations; it does not change retry
or failure classification. A successful retry alone does not establish the cause
of an earlier unspecified remote error.


## Visibility recovery after creation

Successful creation still persists assigned SKU mappings before attribute writes.
For `mapped` identities, publication first checks the selected native SKUs with
batched `product(sku)` reads using the write credential. A visible SKU is updated
even without a local mapping row; an explicit null result creates the product
under the configured identity-attribute SKU, including when a matching local
binding already exists. That binding records identity and synchronization history;
it does not prove current remote existence. This also allows publication to recreate
a product deliberately deleted in Ergonode. Failed, missing or malformed read results
stop the product without a create. A confirmed create binds the Magento product and native
SKU before later attribute results are evaluated; a failed attribute leaves the
binding and the publication result reports the failure. On the next manual
publication, an unconfirmed result without a binding goes through the same
`product(sku)` read: a visible product is updated, and an absent product is
created only after a complete read confirms the absence. Historical `assigned`
and `shared` bindings keep their persisted mode; conflicts with the selected
identity attribute require explicit reconciliation.

For the existing non-mapped creation recovery, a rejected validation
mutation for a product confirmed created in the current synchronization triggers
an immediate batched `product(sku)` read with the publication credential. Only
failed operations for such new products participate. Successful writes, ambiguous
transport failures, create/delete operations and existing products are not replayed.
The existing narrow conflict fallbacks retain their semantics.

For absent products, `NewProductMutationRecovery` has `maxIterations = 5` in global
`etc/di.xml`: five additional visibility rounds after the immediate diagnostic
read, with delays of 100, 200, 300, 500 and 800 milliseconds (1.9 seconds plus
request time). Both settings can be overridden through DI; a longer iteration
limit reuses the last delay. Each round queries only still-pending SKUs, at most
50 per read. As soon as one or more products are visible, only their rejected
operations are retried, respecting mutation count/byte limits, before checking
the remaining products. Successful results retain their original correlation;
retried operations retain their alias and accumulate their actual write attempts.
A second rejected write is reported normally, without another visibility loop
for that operation. Availability alone does not prove why an earlier write failed.

Exhaustion or a read failure returns attention with the original mutation error,
visibility details and the retained mapping; it skips remaining writes for the
affected product. A not-found response for a product just created must not enter
the existing-product recreation path. Other products continue normally. This is
synchronous backend work within the current Admin batch; pause/stop is applied
between Admin batch requests.

The policy and visibility query belong to ProductPublisher. Mapping persistence
remains under the existing identity contract; generic execution stays in Publisher.
The visibility reader uses Core's `GraphQlWriteScopeQueryClientInterface`. Core
was already directly consumed by this module's readiness and error handling, even
though the current Composer list does not declare that existing dependency. This
change adds no new module edge or mapping schema.


## Last publication result

`ProductPublicationBatchInterface` persists one result per existing Magento product
through `ProductPublicationResultWriterInterface`. The table holds status, message
and UTC `recorded_at`; the grid contract exposes them as `publication_status`,
`publication_message` and `publication_at`. Batch responses also return
`publication_at`. No previous results are backfilled from mappings or logs.

Before dispatch, the previous result is replaced by `unconfirmed`. Thus an
interrupted PHP request cannot leave an old success presented as its outcome.
Completion replaces it with success/warning/failed or unconfirmed; request exceptions retain
an unconfirmed result and a safe diagnostic message, then propagate normally.
A result requiring attention is also stored as `unconfirmed`, because assigned
creation or later writes may have succeeded without a confirmed local outcome.
For a new product in `assigned` mode, an existing unconfirmed result with no
identity binding blocks another create before any remote request. Other products
in the manual batch continue. The block persists until the native SKU is bound
after independent remote verification, or until an operator verifies that no
remote product exists and clears that product's last-result row. The guard reuses
the existing result table and does not change the mapping schema.
A failed result write does not replay remote operations. The UI animates only
its active request; a saved unconfirmed result is a warning, not an indefinitely
running job. The result describes the last recorded attempt, not synchronization
of later Magento edits. There is no job queue, run history or retry scheduler.

Results do not own identity mappings. Missing/deleted product IDs are skipped by
the writer, product deletion cascades to its result, and uninstall drops this
module's result table without changing product mappings. Deployment adds an empty
table through declarative schema; existing products and data are not migrated.

## Attribute source validation

`ProductAttributeSourceValidatorInterface` receives the original effective Magento
store values alongside converted translations, keyed by the same language codes.
Only languages with published translations are included. Validators can check source
identity before conversion loses it, without loading additional product models.

## Product publication access

Use a GraphQL read-and-write API key without an assigned segment. Both product
existence queries and mutations use that active write key against the global catalog.
Segment membership and template completeness are not publication prerequisites.

Product preparation does not load REST template/section requirements or the REST
attribute catalog. No REST session is needed and no skipped-validation warning is
added. Available mapped values are sent; actual GraphQL errors retain their normal
failed result. Local identity, mapping and value-conversion checks remain in place.
The optional template adapter still determines the target template from Magento's
attribute-set mapping. Consumption retains its own validation.

The existing conditional visibility recovery after a confirmed create remains:
there is no mandatory sleep after a successful mutation. Global read-after-write
visibility has not been verified live and is not assumed to be synchronous.

## Missing outbound option mappings

An attribute with a Magento option ID that has no Ergonode option code is omitted as a whole for that product. No language value or clear operation is planned for that attribute. Other mapped attributes continue. The attribute source returns distinct per-attribute warnings with the Magento SKU, attribute code and all missing IDs. Only a confirmed successful remote result carries the warnings to the saved publication result and Admin progress popup, one warning per line. The internal success/noop status remains successful for identity recording and dependent products; failed and attention results retain their original status and message. Products that cannot be prepared locally use a separate unsuccessful local-warning status.

## Publication comparison

Before planning updates, ProductPublisher reads the template and attribute translation
languages of existing products with the publication credential. The reader batches at
most 50 selected SKUs, loads attribute pages of 100 entries and validates the returned
SKU, template, translation languages and pagination. It uses the shared GraphQL
`AttributeValue.translations { language }` contract for all attribute types and scopes;
no comparison of value contents is performed. Snapshots live only within one planning
call and are fetched again for the next publication.

When the complete remote template matches the requested template, the planner omits
`productSetTemplate` and clear operations for translations confirmed absent. Existing
translations still receive the requested deletes. Value writes, status changes and
relations keep their existing behavior. A product can finish successfully with no
mutations; normal result recording and durable identity binding still apply.

Missing products, malformed responses, timeouts, incomplete pagination, repeated
cursors or a template changing between pages cannot prove translation absence.
A failed comparison read discards the whole read chunk and logs only exception class
and product count; its products retain the original write plan. Other chunks may still
use complete reads. The required mapped-SKU existence check remains independent and
still blocks creation when existence is uncertain.

After confirmed creation, the synchronizer retains the native SKU across stages
and saves its durable identity binding before comparison and attribute writes.
This includes assigned products created by the identity coordinator. A complete
post-create read with a matching template omits only clears for absent translations;
present defaults still receive the requested deletes. Failed/ambiguous creates are
excluded. An unavailable, missing or incomplete snapshot preserves all clears,
without a new fixed delay or automatic CREATE retry. Existing conditional recovery
still receives the confirmed-created SKU context across stages.

Creation conflicts, recovery after not-found and template changes retain the
original clear operations. A pre-create snapshot cannot establish defaults after
creation. Comparison is not a transaction or lock against concurrent remote edits.
It assumes the completed query describes the state used for publication; it does
not promise atomicity with independent writers or asynchronously applied remote
rules. Read-after-create visibility and timing of defaults must be checked on the
target tenant before treating this as a verified live performance improvement.

A synthetic two-by-50 product test reproduces 100 creates, 376 value writes and
11,856 clear intents: the complete empty post-create snapshot reduces 12,332
mutations / 248 batches to 476 / 10. Repeat publication remains 376 / 8. These
counts exclude categories and recovery; the test uses a simulated remote endpoint
and does not measure live HTTP latency.

The change introduces no persistence, migration, new module dependency or UI behavior.
ProductAttributePublisher continues to own source values and explicit clear intent;
ProductPublisher owns comparison and mutation selection.
