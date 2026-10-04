# Ergonode media synchronization

The current delivery scope is one Magento installation and one media store.
`shared` below means reuse across products within that installation, not
coordination across Magento databases. The agreed configuration,
local index and future optional federation boundary are recorded in
[the single-instance plan](../../../docs/ergonode-media-single-instance.md).

`Ergonode_Media` keeps source identity (`Multimedia.path`) separate from the
Magento file materialization. The optional `Ergonode_ProductMediaConsumer`
bridge persists desired gallery and file-attribute references during product
imports. Product imports execute downloads, gallery writes and image roles inline
in the common product batch pipeline. Independent multimedia-stream updates retain
`ergonode.media.gallery`; both workers share the same product synchronization lock
and selective cache finalizer.
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

Downloads use a fresh HTTP client without the GraphQL API key. The existing
Ergonode origin policy validates the URL and redirects are disabled. Source
metadata and activation use the expected local revision to reject stale downloads.

Workers prepare files before starting the product-write transaction. The transaction
locks the work row and verifies its current lease token and expiry before updating
gallery, roles, file attributes and attachment records. A rescheduled worker cannot
write; an expired lease prevents the write and is not retried. Gallery path locks remain held until commit or
rollback so simultaneous products reuse the same native gallery entry.

Each queued product records whether a gallery update was requested. File-only work
does not rewrite the gallery or native Image roles. An explicit empty gallery still
clears the managed gallery and its roles; a missing gallery attribute leaves them
untouched. Gallery writes complete before Image roles are written. File-only
scheduling preserves an already pending gallery request.
Multimedia changes request gallery processing only for products with desired
gallery usages; other registered file usages remain file-only work.

The multimedia scheduler holds one Magento lock across cursor reads and page
processing. Each page and its cursor checkpoint commit in the same database
transaction. Concurrent CLI/cron runs skip while that lock is held; failed pages
roll back and release the lock.

After deploying this change, stop media consumers and run `bin/magento setup:upgrade`
before resuming them. This adds nullable `synchronize_gallery` to the work table.
Legacy queued work infers gallery intent from existing managed gallery usages;
new work always stores its intent explicitly. A legacy empty-gallery request with
no usage history cannot be distinguished from file-only work and must be requeued
by importing the product again.

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
waiting does not start an attempt. The normal scanner dispatches this fresh waiting work after a full
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

The standard `Ergonode_Media\Setup\Uninstall` removes only the eight tables
owned by this module. It preserves product attribute values, native gallery
entries, image roles, all media files and the source cache. It also preserves
configuration, cron and queue records in Magento-owned tables. Stop consumers
and disable schedules before uninstalling. Broker queue maintenance is a separate
operational action and is not performed by the uninstaller.

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

## Manual integrity verification

Admin's **Verify file integrity** requests a background scan. The existing scan
worker executes that explicit request; it never creates a recurring verification.
The panel shows waiting/running/completed status, progress, a concise findings
summary and the persisted time of the last completed verification. This date
survives subsequent index refreshes and requests. Deployment requires Magento's
standard declarative schema upgrade for `verification_completed_at`.

CLI users can explicitly run `ergonode:media:scan --verify-content`. This reads
all original files under `catalog/product` (excluding generated cache, temporary
and archive directories), recomputes their hashes regardless of size/mtime, and
refreshes the index of actual files. Published materialization paths are checked
against their existing expected hashes. Files without a known mapping are
indexed but cannot be certified against expected source contents. This verifies
byte integrity, not image decoding or the correctness of the original source.

Missing, unreadable and mismatched files are reported with paths and source/asset
context in logs, with duplicate findings for the same path/expected hash collapsed.
An individual unreadable file does not stop the remaining checks. A fatal traversal
or database failure ends the request and is logged. An interrupted verification
is reported as failed by the worker; a new scan requires another explicit request.
Verification never downloads
or repairs a file, changes expected hashes, edits galleries/roles, dispatches
media work, or sets the first-full-scan readiness marker. Normal synchronization
and the existing fast index scan do not gain content verification.

## Gallery replacement and retained image roles

The current gallery selection is authoritative for managed photos and image
roles. If a previously recorded Image usage points to a source absent from the
new gallery, gallery processing clears that role instead of rejecting the product.
This also applies to references retained outside a selected import's attribute
scope. The obsolete reference is removed by product, role, store and source hash
only after gallery and role writes succeed, inside the existing guarded product
transaction. Current positional roles take precedence over a removed mapping.

An explicit empty gallery clears its old roles and references; missing gallery
data does not request a replacement. Download, gallery or role-write failures
leave cleanup unperformed. Ordinary file attributes keep their existing scope
and processing. The ProductMedia setting for additional Magento images defaults to
preserving unmanaged images, with optional per-product hiding or one-to-one unlinking.
The next explicit pass covering a product also clears native image roles pointing
outside its completed visible gallery, including a previous partial state.

Native unlinking preserves other product links and removes an unused gallery row
on the last association. Physical deletion runs after commit under the path lock,
only when no gallery link, native image attribute or desired integration usage
still references the file. It also removes obsolete materialization/index entries;
the prepared source cache remains available. Rollback preserves physical files.
Cleanup errors are logged once with product, path, stage and exception. No repair
queue or automatic resubmission is added; failed physical cleanup requires manual
investigation using its log entry.


## Failed media and a new import pass

A product media failure is logged with its product ID and exception and leaves a
terminal `failed` work record with the error. The consumer continues with the other
products in the batch. There is no delay, maximum-attempts setting or recovery cron.
Only fresh `pending` work with zero previous attempts can be claimed or dispatched.
The retained attempt counter is diagnostic, not a retry policy.

When another consumer invocation or new scheduling encounters an expired processing
lease or a legacy delayed retry, it marks that record failed and logs its reason;
it never executes that old attempt. Previously scheduled recovery cron rows are
compatible no-ops. A stopped process cannot log its interruption at that instant;
the next invocation/scheduling detects the expired lease. No periodic repair job
is added.

A new product import or a replay after an operator resets the appropriate product
cursor can schedule current media data again. If the ordinary product payload hash
is unchanged, the media bridge checks the existing work record. Failed/interrupted
media are synchronized through the normal gallery/file scheduling services using
the source read in this new pass; successfully completed media remain skipped.
Ordinary product attributes and other state synchronizers are not rewritten by this
hook. A newer cursor that does not include the product cannot repair its previous
failure. Resetting only the multimedia cursor is not equivalent to replaying all
product gallery selections.

This policy applies to the media consumer. Retry policies of ordinary product,
category or other import consumers are outside this change. Content corruption is
also separate: manual integrity verification remains reporting-only and preserves
expected hashes. Replaying an import does not guarantee detection/repair of an
existing corrupted mapped file; replace that file manually before replaying.


## Retired ordinary file attributes

After an obsolete ordinary file attribute is successfully cleared, its undesired
usage row is purged inside the guarded product-write transaction. This also runs
when gallery synchronization is disabled. Image-role usage rows are preserved
whenever this work does not update the gallery. A failed clear does not purge the
reference. Once the obsolete ordinary-file reference is gone, a later unrelated
media pass cannot clear a new manual value using that old reference. This cleanup
adds no download, physical file deletion or retry process.
