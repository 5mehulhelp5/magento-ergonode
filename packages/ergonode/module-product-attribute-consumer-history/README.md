# Ergonode_ProductAttributeConsumerHistory

[Product module responsibility map](../../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Optional capture of the consumer's manual/additive saves, automatic mapping,
synchronization batches and complete synchronization runs.

Its responsibility starts and ends at the outer consumer operation boundary.
ProductAttributeConsumer performs the work; ProductAttributeHistory captures state,
groups nested calls and stores entries; ProductAttributeHistoryAdminUi renders them.

## Data, configuration and removal

No owned tables, settings, cursor, ACL or uninstall handler. Removing this bridge
removes its interception registrations and preserves all historical records and
underlying synchronization behavior.

## Dependencies and extension points

Plugins target ProductAttributeConsumer entrypoints and call ProductAttributeHistory's
`HistoryOperationCaptureInterface`. Neither dependency requires this bridge. Base
neutral mapping/snapshot capture remains available without it.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-product-attribute-consumer](../module-product-attribute-consumer/README.md)
- [ergonode/module-product-attribute-history](../module-product-attribute-history/README.md)

Ownership and wiring: [etc/di.xml](etc/di.xml).

## Operational details

Owns the optional connection between ProductAttributeConsumer operations and
ProductAttributeHistory. It captures inbound manual attribute creation/saves,
automatic mapping, synchronization batches and complete synchronization runs.
It does not execute or change the underlying operations.

Plugins wrap the consumer's existing entry points and delegate to
ProductAttributeHistory's `HistoryOperationCaptureInterface`. The same capture
instance groups nested consumer operations, snapshot changes and neutral mapping
saves into one history entry. Results, original exceptions, successful no-ops and
observable partial changes retain the base history contract.

Dependencies: ProductAttributeConsumer supplies the intercepted operations;
ProductAttributeHistory supplies operation capture and owns all persistence.
Neither dependency requires this optional module. There is no Admin UI, schema,
configuration, cursor, authorization resource or module-owned business data here.

Enable this module alongside the consumer and history to retain inbound operation
history. Disabling or removing it stops capturing the consumer operation boundary;
the base history still captures neutral mapping saves and shared snapshot changes.
Removing this module and ProductAttributeConsumer preserves existing history and
the history viewer. Removing history itself uses ProductAttributeHistory's own
uninstall lifecycle. No data migration or backfill is needed.
