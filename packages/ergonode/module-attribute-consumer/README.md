# Ergonode_AttributeConsumer

Owns the shared Ergonode attribute and option snapshots and the orchestration
of option synchronization participants. Concrete consumers register an
`OptionSynchronizationParticipantInterface` implementation in the pool and
remain responsible for deciding which of their mappings participate and for
persisting target-specific mappings.

The module does not own product or category mapping tables and does not decide
how an Ergonode attribute is assigned in Magento.

## Public import and synchronization contracts

`AttributeBatchImporterInterface::import()` imports one read-scope remote page into
the shared attribute snapshot and returns statistics, attribute codes and the remote
cursor. It does not persist a consumer's execution cursor, reconcile deleted definitions
or create Magento attributes and mappings. Complete reconciliation remains a separate
operation through `AttributeDefinitionSynchronizationInterface`.

`OptionSynchronizationInterface::executeForAttributeCodes()` normalizes the selected
codes and aggregates the registered participants' results. An empty selection invokes
no participant. Failures propagate; successful writes from earlier participants may
remain. Each `OptionSynchronizationParticipantInterface` implementation owns its target
mapping decisions and persistence. The pool owns dispatch and aggregation only.

Register named participant objects in the global DI `participants` argument of
`Ergonode\AttributeConsumer\Api\OptionSynchronizationInterface`. Consumers inject
the public contract, not `Model\Sync\OptionSynchronizationPool`.
ProductAttributeConsumer supplies the `product` participant and owns its execution
cursor. [Global DI](etc/di.xml) binds both public contracts to their implementations.

The module registers the shared `attributeStream` capability in
`Ergonode_Core`. Enabled concrete consumers may participate in processing the
import, but they do not own the stream identity or its Admin status entry.

ProductAttributeAdminUi reads this module's snapshots without requiring
ProductAttributeConsumer. AttributeSnapshotRefreshInterface reconciles definitions
and returns the requested snapshot page under the shared attribute synchronization
lock; it does not advance the caller's import cursor or create Magento attributes/options.
ErgonodeOptionProviderInterface supplies grouped snapshot option counts.

The module defines only the two snapshot-refresh ACL resources beneath the existing
mapping ACL IDs. ProductAttribute owns the mapping/save ACL definitions so the
neutral editor can operate without the consumer. Existing role IDs are unchanged.

## Complete definition reconciliation

AttributeDefinitionSynchronizationInterface checks attributeStream and
attributeDeletedStream with independent positions and downloads a complete
definition snapshot when either changes, on first use or on forced refresh.
AttributeDefinitionSnapshot owns the combined checkpoint in Core cursor storage
under attribute_definition_snapshot; the product definition-execution cursor
remains owned by ProductAttributeConsumer. Checkpoint and snapshot replacement
commit together only after complete, validated pagination. The read/write scope
and connection identity are included in the checkpoint.

Missing definitions and their option snapshots are removed from this module's
storage. Neutral mappings, visibility and Magento EAV are preserved.
AttributeAvailabilityInterface exposes current snapshot membership to inbound
consumers without applying UI visibility or attribute-type filters.
Snapshot-page refresh reconciles once on the initial browser call, then reads local
pages without further GraphQL requests. Browser cursors are opaque, are not import
checkpoints and must be restarted when the definition checkpoint changes.
No data migration or additional configuration is required.

## Monitoring kontroli definicji

`attributeDefinitionCheck` jest informacyjną rejestracją kontroli `attributeStream` i `attributeDeletedStream`. Moduł zapisuje najnowszy stan w należącej do niego fladze `ergonode_attribute_definition_check` (standardowe storage Magento). Nie wymaga zmiany schematu ani migracji danych. Rozpoczęcie, wynik, zakończenie i ostatnie wykrycie zmiany są niezależne od checkpointu snapshotu. Pierwsze pobranie ustanawia stan początkowy; wymuszone pobranie bez zmian nie zmienia daty wykrycia. Wykrycie zostaje zapisane przed pełnym pobraniem, więc pozostaje widoczne również po błędzie. Konflikt blokady nie nadpisuje statusu właściciela. Błąd monitoringu trafia do logu i nie zmienia wyniku synchronizacji.

Rejestracja nie dostarcza operacji uruchomienia/resetu. Kontrola wykonuje się w istniejących wejściach importów zależnych; monitoring nie wprowadza schedulerów ani subskrypcji. Stan rozpoczęty oznacza brak zapisanego zakończenia, również po przerwaniu procesu. To ostatni stan, nie historia audytowa. Dane są usuwane przy uninstall modułu.

## Storage and memory boundaries

Complete definitions are validated into temporary storage before any replacement
transaction begins. The buffer spills to disk beyond 2 MiB; replacement uses batches
of at most 200 definitions while retaining one atomic checkpoint and snapshot commit.
No schema or existing-data migration is needed.

Option pages must contain valid unique codes; repeated codes across pages abort the
refresh before pruning. Earlier successfully imported pages may remain after a later
failure, but missing options are never pruned on a failed refresh. Snapshot writers and
removers invalidate the shared in-process option cache, including on replacement with
an empty definition set. The cache retains only the current set of requested definitions
and one UI option list, rather than accumulating every previously requested attribute.
`getOptionDefinitionsByCodes` batches reads (200 attribute codes per query) and includes
empty lists for requested codes without options.

## Attribute-value files

This module owns materialization of file sources referenced by attribute values through
ErgonodeFileDownloaderInterface, shared by product and category consumers. It does not
own gallery synchronization, asset lifecycle, product/category mappings or media queues.
Core owns source authorization; every HTTP hop must match the configured Ergonode
origin and uses a fresh client. External URLs and redirects are rejected before HTTP.
Files are streamed to a temporary sibling and published only after a successful nonempty
response. Redirect bodies and failed downloads never become the target file. File paths
and already materialized files retain their existing semantics; there is no file migration.

## Snapshot writer coordination and bounded option reads

The shared snapshot write lock covers complete option refreshes (download, writes and
pruning), definition replacement, direct writers and snapshot removal. Nested writes
reuse the same lock; all entry points, including category and publication adapters,
participate through global DI. Existing product process locks retain their own scope.
A failed refresh releases the lock and never prunes missing options.

Definition identity includes the sorted distinct language set. Changing active language
mappings refreshes the snapshot on the next synchronization even when remote streams
have no changes. Existing checkpoints are refreshed through the regular import flow;
no migration is performed.

`iterateOptionDefinitions` reads at most 200 rows per SQL page with an entity-ID cursor
and retains no complete option set. CategoryAttributeConsumer consumes this stream
when building its target-specific lookup. The array-returning provider methods remain
for mapping editors and single-attribute consumers; their requested result necessarily
occupies memory. Snapshot caches retain at most 500 options and 1 MiB of text per list
set; larger results are returned without retention. The storage-order stream does not
promise display ordering; the array definition reader preserves option sort order.

Definition pages use only `AttributeBatchImporterInterface::import()` (read scope).
Full write-scope definition refresh stays in `AttributeCacheRefresher` and the
existing definition synchronization service; there is no separate page importer.
Option reconciliation preserves non-empty numeric codes, including `"0"`.

Definition writers invalidate the shared attribute provider after changed rows are
saved; empty and unchanged pages retain its cached reads. Snapshot replacement and
removal invalidate it after their transaction commits, including empty replacement.
This responsibility belongs to snapshot persistence, so direct page imports and other
callers observe the same updated lists, maps and relation definitions. The full-definition
orchestrator does not perform a separate provider reset.
