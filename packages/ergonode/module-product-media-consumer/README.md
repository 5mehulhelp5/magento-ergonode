# Ergonode_ProductMediaConsumer

[Product module responsibility map](../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Connects product import to asynchronous media: request gallery metadata, defer mapped
file values and submit desired gallery/file usages after product writes.

ProductConsumer owns product scheduling and writes. Media owns asynchronous transfer,
usage records and workers. Without this bridge, the consumer's own synchronous file
downloader handles mapped files and gallery metadata is not requested.

## Data, configuration and removal

No owned tables, configuration paths or uninstall handler. Desired usages are persisted
by Media. Removing this bridge does not itself clean Media's persisted data or Magento
files; Media's lifecycle owns that cleanup.

## Dependencies and extension points

Contributes to ProductConsumer's attribute-code, mapping-deferrer and state-synchronizer
pools. There is no reverse dependency from ProductConsumer to this bridge.

Declared project-module dependencies ([composer.json](composer.json)):

- [ergonode/module-media](../module-media/README.md)
- [ergonode/module-product-consumer](../../../../packages/ergonode/module-product-consumer/README.md)

Ownership and wiring: [etc/di.xml](etc/di.xml).

## Operational details

`Ergonode_ProductMediaConsumer` connects product imports to the optional
`Ergonode_Media` runtime. It requests the globally selected Gallery attribute,
defers mapped `file` attributes to the asynchronous media worker and records
desired gallery and file usages after a product is written.

Disable this module together with `Ergonode_Media` to keep product imports
running without asynchronous media synchronization. Without the bridge,
mapped `file` attributes use the synchronous downloader owned by
`Ergonode_ProductConsumer`; gallery attributes are not requested.

Because this bridge depends on `Ergonode_Media`, move both modules to the
development backlog in one command:

```bash
ddev exec bin/magento developer:module:backlog --remove-data \
    Ergonode_ProductMediaConsumer Ergonode_Media
```

## Product gallery boundary

ProductMedia now owns the global gallery selection/synchronization contract,
additional Image positions, native gallery writes and roles. Media retains
transfer/index/queue tables. MediaAdminUi consumes both public contracts;
ProductMediaConsumer requests the selected Gallery and configured Image sources
and defers mapped Images to gallery processing. No table migration is performed.
See [ProductMedia](../module-product-media/README.md) for the current contract.
