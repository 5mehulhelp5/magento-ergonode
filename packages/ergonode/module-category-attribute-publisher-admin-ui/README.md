# Ergonode_CategoryAttributePublisherAdminUi

Optional outbound adapter for CategoryAttributeAdminUi, located in `app/code/Ergonode`. Owns Admin
publication actions, pending source-definition preparation and source metadata obtained
through AttributePublisher and CategoryAttributePublisher write-scope reader APIs.
It owns no consumer snapshots, mapping tables or inbound synchronization.

Depends on CategoryAttributeAdminUi instead of CategoryAttributeConsumerAdminUi. The
pending-definition hook targets the neutral editor's `prepareMappings` inside its save
context. A successful creation invalidates request-local publisher metadata, not consumer
storage. Eligibility uses the editor's current Magento metadata; the runtime neutral
policy can be independently extended by an installed consumer.

Without a category-attribute consumer, publication and neutral saves remain available.
Without this adapter the editor rejects unresolved source creations and exposes no
publication actions. With both adapters, the consumer wraps the same save context with
its lock/backfill and publisher preparation runs before neutral persistence.

No tables or configuration paths change. No remote publication is run by the extraction
tests. Optionality is tested through
DI composition and focused tests without changing local module enablement.

ErgonodeCategoryAttributeCreator returns the successfully synchronized desired state.
The batch response takes its canonical scope from that state; pending mapping preparation
uses the same creation flow and ignores the returned state. Registry rate limits propagate
to the shared batch controller, preserving retry timing and stopping the remaining batch.

Category metadata uses AttributePublisher's batch read contract for all registered codes,
including select/multiselect options. It retains the request-local cache and resets it
after publication; incomplete reads never populate the cache. With a single-page registry,
50 text attributes need two requests in total, and 50 selects with single-page options
need three. Additional option pages continue only their unfinished connections.
The adapter owns presentation metadata, while GraphQL reading and validation remain in
AttributePublisher. Disabled publication performs no reads.
