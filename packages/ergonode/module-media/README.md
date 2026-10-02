# Ergonode media synchronization

The current delivery scope is one Magento installation and one media store.
`shared` below means reuse across products within that installation, not
coordination across Magento databases. The agreed configuration,
local index and future optional federation boundary are recorded in
[the single-instance plan](../../../docs/ergonode-media-single-instance.md).

`Ergonode_Media` keeps source identity (`Multimedia.path`) separate from the
Magento file materialization. The optional `Ergonode_ProductMediaConsumer`
bridge persists desired gallery and file-attribute references during product
imports; downloads run on `ergonode.media.gallery`.
Media gallery synchronization can be disabled independently from mapped file
attributes with `ergonode_products/media/synchronization_enabled`. When
disabled, product imports do not request the configured Ergonode Gallery
attribute and queued file-attribute work continues without changing Magento
media galleries.

- `shared` (default): reuse identical SHA-256 content across Ergonode paths and
  products. A known existing gallery file keeps its native gallery `value_id`.
  New shared filenames use the full SHA-256, independently of the source name.
- `seo`: one product-scoped gallery materialization named from the global
  product name, SKU, source name and a short SHA-256. File attributes are still
  shared.

The source path is not a content version. `multimediaStream` marks it dirty,
and one claimed download computes the local SHA-256 before all affected usages
are refreshed. The cursor is advanced only after idempotent database work has
been persisted. Download concurrency is limited to six and downloads to 250 per
minute.

An active asset with a matching stored content hash and revision reuses its
existing target file before source preparation. Removing the temporary source
cache under `var` does not download that asset again. A missing target, missing
content identity or a dirty/new revision still enters source preparation.

Each claimed consumer batch uses a shared `MaterializationCache`. The first
resolution checks the existing target; later uses of the same asset, revision,
content hash and scope return its saved path without another filesystem check.
The asset state is still read from the database, so a changed revision or dirty
asset bypasses the cached path. Actual content verified while finding or writing
a shared file can also be reused by another source identity in the same batch,
without another index lookup or hash calculation. Existence alone does not
certify content for a new source association.

Both maps are cleared in `finally` after the batch, including interrupted work.
The next batch checks the target again and can recover missing files. Files
must not be externally removed or overwritten during a batch. Direct calls
outside a batch retain their filesystem checks. Different unknown Ergonode
paths still require one initial download each before their content can be
compared; equal contents share the permanent media file, while source caches
under `var` remain source-specific.

The gallery mode is locked after the first successful non-empty gallery.
Changing or moving an Ergonode path creates a new source identity; because the
target API exposes no stable multimedia UUID, v1 requires a full product
resynchronization for such changes. There is intentionally no periodic binary
audit, so release requires a live contract check confirming that
`multimediaReplace` advances `multimediaStream` on the target Ergonode version.

## Local file index and configuration

`ergonode_products/media/gallery_attribute` is a required global selection of
one existing Ergonode `Gallery` attribute. The options API reads all attribute
pages and filters their types. There is no implicit `gallery` fallback.
`Ergonode_MediaAdminUi` owns the form; ProductMedia owns gallery configuration. The product bridge consumes `GalleryConfigurationInterface`.

Run `bin/magento ergonode:media:scan` before the first import to index existing
original files below `catalog/product`. The scanner skips cache, temporary and
archive directories, hidden files and symlinks. Repeated scans reuse hashes when
size and modification time match; this is a snapshot, not a continuous audit.
`bin/magento ergonode:media:list --limit=100 --after=catalog/product/example.jpg`
lists indexed paths, SHA-256 and sizes, with a path cursor.

The owned `ergonode_media_local_file` table stores path, binary path hash,
content hash, size and modification time. Its port is internal. A new
association verifies the candidate file's actual content; a known active
association bypasses this lookup. Newly imported files enter the index
immediately. Files added by other tools require another scan. An incomplete
index may miss an existing duplicate; the importer does not run a full scan
for every product.

Scanning only changes index rows. It does not repair historical duplicates,
move or delete binaries, or change product references. No dependency on
`PackHauer_ProductMedia` or federation is introduced. Storage must be locally
accessible through Magento's media directory; direct object-storage APIs are
outside this implementation. Existing asset materializations stay valid;
there is no data or file migration.

## Admin scan and shared-mode readiness

In **Ergonode → Products → Media**, the Local Media panel requests a scan and
shows persisted status, approximate scope, file count, elapsed/remaining time,
last successful completion and errors. Admin access is controlled by
`Ergonode_Media::scan`; starting uses a CSRF-protected POST. The scanner runs
through `ergonode_media_scan` in the existing `ergonode` cron group, independently
of the browser and of whether the Ergonode connection is enabled.

`ergonode_media_scan` stores one current run and the last full completion marker.
There is no migration or inference from existing index rows: after installing
this change, a full successful scan (Admin or CLI) is required in shared mode.
Requests are idempotent. CLI and cron use the same lock and lifecycle. After a
process interruption, the next cron invocation restarts the requested traversal;
previously indexed unchanged files reuse their hashes. A reported failure stays
visible and requires a new request. Scans do not modify product associations or
remove binary files.

The initial estimate counts distinct, case-sensitive native gallery image paths,
not product links. Files on disk can exceed this estimate. Progress is capped at
99% until directory traversal and missing-entry reconciliation both finish.
Remaining time is approximate, based on checked files per elapsed second; it is
withheld during warm-up and when the database estimate has been exceeded.

Before the first complete scan, the product media consumer does not claim work
in **shared** mode. Desired usages still accumulate during product imports;
waiting does not consume retry attempts. Recovery dispatches them after a full
successful scan. The synchronization switch retains the administrator's setting.
SEO mode is not gated by this prerequisite. A later scan or failed refresh
preserves the previous completion marker and does not re-block synchronization.

## Backlog and data removal

The media module and its optional product-consumer bridge must be moved to the
development backlog as one dependency set. Stop any running
`ergonode.media.gallery` consumer first:

```bash
ddev exec bin/magento developer:module:backlog --remove-data \
    Ergonode_ProductMediaConsumer Ergonode_Media
```

The standard `Ergonode_Media\Setup\Uninstall` removes module tables, saved
`ergonode_products/media/*` configuration, cron and database-queue records,
managed native gallery links, still-managed file-attribute values, downloaded
`shared` and `seo` files, and the `var/ergonode/media` source cache. It does not
remove `catalog/product/ergonode` or `catalog/product/files`, because those
locations are also used by synchronous imports and manually uploaded file
attributes. When an external AMQP or STOMP broker is configured, purge the
`ergonode.media.gallery` broker queue operationally before removing the module.
When `PackHauer_ProductMedia` is enabled, run
`ddev exec bin/magento product:media:scan`
afterwards to refresh its independent filesystem snapshot.

## Environment-specific automation settings

Global `etc/di.xml` registers the following owned settings as `environment`
with `Magento_Config` through `Magento\Config\Model\Config\TypePool`:

- `ergonode_products/media/synchronization_enabled`

Configuration export treats these switches and schedules as installation-specific.
Their defaults and runtime interpretation are unchanged; they are not sensitive.

## Product gallery boundary

ProductMedia now owns the global gallery selection/synchronization contract,
additional Image positions, native gallery writes and roles. Media retains
transfer/index/queue tables. MediaAdminUi consumes both public contracts;
ProductMediaConsumer requests the selected Gallery and configured Image sources
and defers mapped Images to gallery processing. No table migration is performed.
See [ProductMedia](../module-product-media/README.md) for the current contract.

A completed Admin/CLI scan explicitly dispatches waiting media work. This also
resumes manually imported products in read-and-write mode, where automatic
recovery cron is disabled. An idle scanner does not publish work.

## Unavailable connection in scheduled work

Automatic synchronization and recovery use Core's fresh connection probe before
starting domain work. Missing or invalid configuration and unsuccessful probes
skip the run without changing cursors, enqueueing work or logging connection
errors. A rejected connection discovered during execution is also skipped;
unexpected failures remain visible. Existing work is retained for the next run.
The local media scan and history retention remain independent of this policy.
Unit tests cover repeated rejection and recovery; the project integration cron
contract exercises Magento scheduling with existing work and stored checkpoints.
