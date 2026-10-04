# Ergonode_ProductMediaConsumer

[Product module responsibility map](../../../docs/ergonode-product-modules.md).

## Responsibility boundary

Connects product import to media: request gallery metadata, defer mapped file values
to the media processor and apply desired gallery/file usages in the same batch.

ProductConsumer owns product scheduling and writes. Media owns transfer, usage records
and workers for independent multimedia updates. Without this bridge, the consumer's own synchronous file
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
defers mapped `file` attributes to the batch media processor and records desired
gallery and file usages after a product is written.

Disable this module together with `Ergonode_Media` to keep product imports
running without this media synchronization. Without the bridge,
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

File mappings to `file`, `text` and `textarea` all register media usages.
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

Selected product imports in Admin use the same media synchronizer as queued full
imports through ProductConsumer's selected-state extension pool. The configured
gallery follows the same selection rules; mapped file and Image attributes are
limited to the target product's allowed attribute codes. Usages outside that scope
are preserved. If no gallery update is selected, native Image usages and their
roles are preserved while ordinary file mappings can still be synchronized.


A new full product pass also calls the media bridge when the product payload hash
is unchanged and an earlier media work record is failed or interrupted. This
opt-in hook reuses the normal file/gallery scheduling services and current source
data; it does not rewrite ordinary product attributes. Completed media remain
skipped. It is triggered by the new import pass, with no automatic retry of the
old media task. A cursor reset must cover the relevant products to have this effect.


Invalid additional-image or image-position configuration is reported as a terminal
product-import error by the media hash provider. The queue logs its SKU and cause,
continues other products and does not retry that configuration error. Unused image
settings are not validated by this provider while gallery synchronization is disabled.

## Product batch pipeline

During full and selected-product imports, `ProductMediaSynchronizer` records the
media intention in the current batch context. `WriteProductMedia` executes it after
ordinary data processors and before postprocessing/cache finalization. No separate
media queue message is published by that product batch. The processor claims only
the current product's fresh work, executes it once, and retains terminal failure
information for a later explicitly started import. Other products continue.

Independent multimedia-stream work still uses the media worker. Product and media
workers acquire a common lock before claiming items; a worker waits for the current
execution, while a manual Admin import reports that synchronization is busy.
