# Ergonode_CategoryAttributePublisher

Outbound category-attribute definition/registry and value publication to Ergonode.
Extends CategoryPublisher through CategorySynchronizationContributorInterface and uses
AttributePublisher definition contracts. Owns write-scope category registry readers,
state comparison and mutation verification. Owns no neutral mapping tables or inbound
snapshots and has no category-attribute consumer dependency.

Implements CategoryBatchSynchronizationContributorInterface with isolated state
per planning/verification round. Attribute values use GraphQL aliases in batches
of at most 50 categories, preserving each category's language scope and cursor.
Only unfinished connections are paginated. The global registry is read once per
round (plus its own pagination), not once per category. No cache survives a round.
Single-category loading delegates to the same batch reader.
Malformed category data falls back to individual reads to preserve per-category
failure isolation; this degraded path can use N+1 reads. Transport/rate-limit errors
propagate immediately and never trigger that fallback.

The optional CategoryAttributePublisherAdminUi adapter contributes outbound actions and
metadata to the neutral CategoryAttributeAdminUi editor. The runtime and adapter are
located in `app/code/Ergonode`. Disabling it must not disable the editor or inbound synchronization.

Registry synchronization preserves Publisher rate-limit exceptions and retry delays.
Category option publication reads all persisted EAV option labels for the attribute in
one query, merges labels supplied by the option objects, and uses Language projection
with the admin-store label as fallback. The internal MagentoOptionLabelReaderInterface
separates EAV persistence access from desired-state construction. No mapping data is owned here.
