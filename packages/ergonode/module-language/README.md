# Ergonode_Language

## Responsibility boundary

Owns the Ergonode language snapshot and language-to-Magento-store-view mappings.
Provides language codes, explicit snapshot refresh/removal and validated mapping
persistence to synchronization and publication modules. Admin controls belong to
LanguageAdminUi. Domain operations return their results without recording audit history.

## Data and dependencies

Owns `ergonode_language` and `ergonode_language_store_mapping`. Language visibility
uses Core's shared mapping visibility storage. The module depends on Core for
GraphQL queries and visibility contracts, and on Magento store infrastructure.
It does not own product values, category synchronization or history storage.

Public API interfaces in `Api` serve existing consumers. Removing the history
extension does not change the language and store-view mapping contracts.

## Existing cross-module contracts

The current public surface also includes two concrete types under `Model`:

- `Model/Data/MappingStateDto` is the return type of
  `Api/LanguageMappingStateProviderInterface::getState()`. LanguageAdminUi reads
  its language codes, ordered mapping rows, Store View map, visibility maps and
  revision. The readonly DTO describes the editing snapshot; it does not persist
  changes or resolve runtime mappings itself.
- `Model/LocalizedStoreProjection::project()` projects caller-supplied strings
  from Store Views to Ergonode languages. ProductAttributePublisher and
  CategoryAttributePublisher use it for attribute and option labels. The callers
  own loading and publishing those values. Language owns only the shared
  projection through its resolved language map.

Projection visits the resolved map in mapping order (`sort_order`, then
`mapping_id`). For each language, the first mapped Store View wins. Scope `0`
uses the supplied default value; another Store View uses its supplied override
or falls back to the default when the override is absent or null. Values are
trimmed. An empty selected value omits that language, without trying a later
Store View mapped to the same language. The resulting keys are sorted by
language code. For example, with `2 -> en_GB` before `1 -> en_GB`, a missing
override for Store View 2 selects the default even if Store View 1 has a value.

These descriptions document existing code and consumers. They do not move
classes, introduce new interfaces or change the projection's selection rule.

## Mapping consistency and cache

`LanguageMappingStateProviderInterface` returns a coherent, uncached editing
snapshot and its revision. `LanguageStoreMappingSaverInterface::save` requires
that revision and returns `stats` and the new `revision`. Comparison and write
share a Magento lock with snapshot replacement/removal and cache population.
A stale editor is rejected before any write. No data migration is required.

Each submitted mapping row is validated before the transaction. Invalid row or
side types and rows with no language or Store View reject the entire save;
they are not silently discarded. An explicitly empty mapping list still means
removing the mappings. Language-only and Store-View-only drafts remain supported.

Magento Default Values (scope `0`) always participates in mapping. The editing
state reports it as active regardless of any historical visibility flag, and
the saver rejects requests to exclude it before writing any mappings or
visibility. Its assigned language remains selectable and its language visibility
is still respected. Missing or inactive language assignments continue to block
operations requiring a default language. Existing visibility records are not
migrated or removed.

Runtime consumers use the resolved map through Magento's cache backend (Redis
when configured), with `MappingCache` constructor arguments in `etc/di.xml`:
`cacheLifetime=900` seconds and `localCacheLifetime=30` seconds. Local expiry
never exceeds the shared entry's original deadline. Empty maps are cached too.
No background polling occurs: a busy process checks shared cache at most once
per local lifetime; idle processes do nothing. Successful mapping/visibility
writes and snapshot refresh/removal invalidate the entry under the same lock.
Other processes observe invalidation on their next use after local expiry.
Store configuration changes outside these operations become visible when the
shared map is rebuilt, subject to Magento's own store/config cache lifecycle.

Admin editing and conflict detection bypass this cache. The editing block holds
one snapshot only for that page render, so the revision describes displayed data.
Refresh validates all edges before replacing the snapshot; malformed or partial
edge structures preserve previous data. A valid empty edge list clears it.

Refresh holds a separate Magento lock (`ergonode_language_refresh`) from before
its first remote page until snapshot replacement and cache invalidation finish.
Another concurrent refresh fails immediately with a localized retry message,
before making any remote request. The lock is released on success and failure.
The mapping lock is held only for the existing short persistence phase; mapping
reads and writes remain available while the remote response is pending. Retry
after the first refresh finishes fetches a fresh list. This orders refresh calls;
it does not freeze remote data or serialize snapshot removal during HTTP.

Regression coverage includes lock contention, pagination, persistence/cache order
and release after transport, validation, mapping-lock, storage and cache failures.
Integration uses two independent database sessions and a suspended fake remote
response to verify exclusion, retry, persisted snapshots and availability of the
mapping lock during HTTP. It uses only the isolated integration database.


## Failure-path test coverage

The first test-hardening stage covers domain persistence and visibility:

- Unit tests reject partial second pages, transport failures and invalid/repeated
  pagination cursors without replacing the snapshot or invalidating its cache.
- Unit tests filter inactive languages and optional store scopes, preserve the
  required scope zero and reject operations when their required active language
  mappings disappear.
- Integration tests inject a mapping insert failure after existing rows change,
  and a failure after real visibility writes. They verify that the database rows,
  visibility and editing revision return to the pre-save state without cache
  invalidation. Visibility coverage includes both updating and inserting rows.
- A snapshot integration test fails insertion after deleting the previous list,
  then verifies rollback through the real database and no cache invalidation.
- Visibility integration tests warm the runtime cache, disable the admin language
  and the last remaining language or Store View, then reactivate them. They verify
  required-scope errors, cache invalidation and preservation of persisted mappings.

Rollback tests use `DbIsolation(false)` so the service owns the outer transaction;
otherwise Magento's surrounding test transaction would hide the actual rollback
boundary. They run only through the Magento integration test database and restore
their fixtures in `finally`. Visibility tests use normal database isolation.

The approved mapping is failure-path tests -> `Ergonode_Language/Test` (user:
"zatwierdzam"). This stage changes tests and documentation only. Runtime classes,
public contracts, module boundaries and dependencies remain unchanged; no data
migration or diagnostic suppression is introduced. `MAG-SOLID-001..008` therefore
have no changed runtime class to assess. Existing Core visibility contracts and
Magento persistence/store infrastructure are exercised by the integration tests.

Validation entrypoints from the backend:

    make -f .agents/backend/Makefile test-unit RUNTIME='ddev exec' args='packages/ergonode/module-language/Test/Unit'
    make -f dev/Makefile test-integration RUNTIME='ddev exec' args='/var/www/html/packages/ergonode/module-language/Test/Integration'

The explicit runtime uses the current parent workspace workflow, which no longer
provides the archived lease script expected by the backend's default host wrapper.

Controller/ACL validation, autosave concurrency, multi-process races and browser
E2E execution remain separate stages; these domain tests do not prove those flows.
