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
files. Media's uninstaller removes only its own tables.

## Dependencies and extension points

Contributes to ProductConsumer's attribute-code, mapping-deferrer, state-synchronizer
and import-hash pools. There is no reverse dependency from ProductConsumer to this bridge.

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

## Refresh and attribute scope

File mappings to `file`, `text` and `textarea` all register asynchronous usages.
Text targets receive a media URL; File targets retain their relative path. A changed
multimedia cursor schedules every registered product using that source. New content
gets a new shared path; the old file remains available to other references.

Global values use store 0. Store-view values use the configured language for each
store. Website values select one language per website: the website default store
when mapped, otherwise the first mapped store by ID. Magento propagates that value
to all store views in the website. Gallery membership remains global.

Gallery selection, enablement, additional image positions and the additional role
contribute to the product import hash. Changing these settings takes effect when
the product is next processed. Saving configuration does not enqueue the catalog.
