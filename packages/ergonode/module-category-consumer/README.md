# Ergonode_CategoryConsumer

## Granica modułu i zasady rozbudowy

Właściciel synchronizacji Ergonode → Magento: wykonania porównania kompletnego drzewa,
tworzenia, przenoszenia, porządkowania i bezpiecznego
zachowania brakujących kategorii Magento oraz niezależnych procesów struktury i danych kategorii.
[Mapa rodziny i zasady zmiany granic](../Category/README.md#granica-modułu-i-zasady-rozbudowy).

### Dane, konfiguracja i kontrakty

- Moduł nie deklaruje własnych tabel. Korzysta z danych drzew, snapshotów i mapowań
  należących do Category; odpowiada za biznesowe reguły ich użycia podczas synchronizacji.
  Katalog i EAV pozostają danymi Magento. Globalny snapshot wartości encji należy do
  [CategoryAttributeConsumer](../CategoryAttributeConsumer/README.md).
- Właścicielem procesów `category_tree_stream` i `category_stream`, zasad zapisu/resetu
  ich kursorów i uruchamiania z CLI/Cron jest ten moduł.
  Infrastrukturę przechowywania kursorów i ogólnego statusu dostarcza Core.
- Tutaj są domyślne ustawienia `ergonode_categories/cron/{status,schedule}` i
  `ergonode_categories/synchronization/name_mode`. Wspólne włączenie danych i ich
  harmonogram także należą tutaj, choć zachowują ścieżki
  `ergonode_category_attributes/synchronization/status` oraz
  `ergonode_category_attributes/cron/{status,schedule}`.
- [CategoryReconciliationServiceInterface](Api/CategoryReconciliationServiceInterface.php)
  obsługuje `preview|apply`; osobne API procesów struktury i danych obsługują CLI/Cron.
  Algorytm propozycji tożsamości i wykluczeń należy do Category i jest współdzielony
  z jego automatycznym mapowaniem; ten moduł wykonuje wynik przy synchronizacji.
  `Category\Api\CategoryLayoutSaverInterface` zapisuje zaakceptowane mapowanie, a
  `CategoryTreeStateProviderInterface` udostępnia kanoniczny stan dla rozszerzeń.
- [API](Api) definiuje także punkty rozszerzeń ładowania encji, wartości przy tworzeniu,
  zapisu danych i celu nazwy. Implementacje bazowe pozwalają działać bez atrybutowego
  rozszerzenia. Dostępność synchronizacji, stan postępu, pauza i wznowienie należą
  do runtime; Admin UI przekazuje żądania i przedstawia wyniki.

### Zależności i wpływ zmian

[Composer](composer.json): `CategoryConsumer → Category`.
[DI](etc/di.xml) i [module.xml](etc/module.xml) pokazują również wykorzystanie
infrastruktury Core; kod korzysta z Language dostępnego w grafie zależności. Nie dodawaj odwrotnej zależności bazy od rozszerzenia.

| Zmieniana odpowiedzialność | Konsumenci i konieczny zakres sprawdzenia |
| --- | --- |
| Request/result, dopasowanie, usuwanie, wspólny stan drzewa | CategoryConsumerAdminUi, CategoryConsumerHistory, wywołania CLI/Cron |
| Ładowanie encji, tworzenie, cel nazwy, zapis danych | Preferencje DI CategoryAttributeConsumer; działanie z rozszerzeniem i bez niego |
| Zapis layoutu i blokada | Plugin publikacji CategoryPublisherAdminUi, pluginy historii, backfill atrybutów |
| Dostępność, kursory, stan i postęp operacji | Adaptery Admin UI, oba procesy i opcjonalne atrybuty |

### Poza zakresem i wskazówki dla agenta

Kontrolki importu należą do [CategoryConsumerAdminUi](../CategoryConsumerAdminUi/README.md),
a panel mapowania i Auto Connect do [CategoryAdminUi](../CategoryAdminUi/README.md),
wartości atrybutów do CategoryAttributeConsumer, historia drzew do CategoryConsumerHistory,
a zapis do Ergonode do modułów Publisher. Reguł synchronizacji nie kopiuj do kontrolera,
JavaScriptu, komendy ani crona. Nie dodawaj relacji produkt–kategoria do tej rodziny.

Synchronizacja nie usuwa automatycznie kategorii Magento. Kandydaci do usunięcia
są informacją; historyczna flaga `remove_missing` nie uruchamia usuwania.
Ograniczenia bezpieczeństwa usuwania nie są konfliktami synchronizacji i nie
zatrzymują jej kursora. Odinstalowanie Consumer usuwa tylko jego ACL synchronizacji;
neutralne ACL, w tym historyczne ID automatycznego mapowania, i widoczność usuwa właściciel danych.
Zmiany streamów muszą zachować ich niezależność; rozszerzenie danych nie otrzymuje
drugiego kursora ani osobnego harmonogramu. Dobierz testy z
[Test/Unit](Test/Unit), [Test/Integration](Test/Integration) i testów konsumentów;
granicę rozszerzenia chroni [CategoryAttributeBoundaryTest](Test/Unit/Contract/CategoryAttributeBoundaryTest.php).

## Szczegóły działania

Zależności paczek i kontrakt `--remove-data` opisuje
[`../BACKLOG.md`](../BACKLOG.md).

## Category Trees

A Category Tree defines one explicit relationship between a complete Ergonode
category tree and one Magento store-group root:

```text
global Ergonode API connection
  -> Category Tree
       -> one Ergonode tree_code
       -> one Magento root_category_id
```

The Magento root is required and must be assigned to a store group. A root can
belong to only one Category Tree. The configuration consists of `is_active`
and `remove_missing`. Only active trees are synchronized.

## Reconciliation contract

`CategoryReconciliationServiceInterface::execute()` is the only business entry
point used by Admin UI, CLI and structural cron events. Its request contains a
`category_tree_id`, `preview|apply`, draft code/ID pairs and draft visibility.

- `preview` reads the latest local Ergonode snapshot and a fresh Magento subtree without calling Ergonode,
  resolves database mapping → accepted draft → unique normalized sibling name,
  and returns canonical models without mutating Magento or persisting the draft;
- `apply` first fetches the complete Ergonode tree, uses the same resolver, then creates, maps, moves and orders
  parent-before-child. Missing source categories and their Magento mappings are
  preserved, including when the legacy `remove_missing` value is enabled;
- exclusions protect the category and its Magento subtree. They do not erase an
  accepted identity mapping. Explicit operator unmapping remains separate.
  Excluding a source category also excludes its source descendants before target
  protection is calculated, including when that parent has no Magento mapping.
  Existing descendant identities remain reserved; this exclusion does not create
  an unresolved-parent conflict or stop the stream cursor.

Apply keeps successful code-to-category mappings in memory across its passes.
Stored mappings and accepted drafts reserve their targets across all branches
before sibling-name matching. Numeric codes, including `0`, remain string
identities and can identify parents.
Later passes traverse completed parents to reach newly resolved descendants without
repeating the parents' writes, attribute updates or progress increments. The resolver
still runs after creation so identity precedence, conflicts and deletion eligibility
use the final state. When it finds no remaining executable assignments, there is no
extra empty execution pass. Progress counts each successfully reconciled category
once per apply; completed mappings are not retained across separate runs or resumes.
The immediate recovery link after creating a category remains in place if a later
move or pause interrupts the operation.

The working Magento tree index survives name/attribute backfill within a pass;
pending data changes refresh it between passes. Frontend cache invalidation is
deferred until the entire reconciliation or category data stream finishes, also
on partial failure. Existing category tags and block/full-page cache coverage
are retained.

The tree stream cursor is only a change trigger. Every triggered reconciliation
loads the complete tree before it can decide which Magento categories are
missing. Category data uses the independent `categoryStream` process owned by this module.
It reads names by default and optional attribute data through DI implementations.
Names initialize newly created categories; the default Keep mode preserves existing
names. Update mode synchronizes `category.name` to Magento `name`. The optional
attribute consumer can select another Magento text field as the name destination. Newly created
category URL keys are generated from the initial category name only.

Creation supplies the parent ID and leaves path and level initialization to the
Magento category repository. Prepopulating `path` on a new model lets Magento's
EAV custom attribute snapshot overwrite the generated path during persistence.
The reconciler consumes the category returned by the repository, including its
persisted path, so subsequent ordering cannot mistake siblings for descendants.

The normalizer performs Unicode NFC, collapses every Unicode separator or
whitespace run to one space, trims, and lowercases without transliteration.

## Runtime responsibility boundaries

Admin UI, CLI and stream handlers do not implement their own tree matching or
Magento mutations. They build a request and delegate to the same runtime
services:

- `CategoryReconciliationService` is the single orchestration entry point for
  one Category Tree in `preview` or `apply` mode;
- Category's `CategoryIdentityResolver` compares the supplied Ergonode and Magento
  trees and owns database mapping, accepted draft mapping and unique sibling-name resolution;
- `CategoryReconciliationExecutor` owns create, mapping, move and order
  operations; the category-data process owns later name updates;
- Category's `CategoryDeletionCandidateResolver` determines safe deletion candidates and
  `CategoryDeletionExecutor` is the only class that physically deletes Magento
  categories;
- the optional `Ergonode_CategoryAttributeConsumer` owns entity snapshots and
  mapped attribute writes, enriching the base category-data process;
- `CategoryTreeStateProviderInterface` exposes one canonical, read-only source
  and Magento tree state for optional category-consumer extensions;
- `CategoryStructureSynchronizationProcess` and
  `CategoryDataSynchronizationProcess` are the independent business
  entry points shared respectively by CLI and Cron.

Consequently, the base Admin UI Auto Connect and Consumer synchronization use
the same comparison rules. Consumer entry points select a mode, provide draft
input when applicable and execute Magento mutations only in apply mode.

## Tree comparison and deletion

For each configured Category Tree, reconciliation:

1. loads the latest local Ergonode snapshot for preview, or every page of the
   selected Ergonode tree for apply, and a fresh complete Magento subtree;
2. in apply mode, replaces the structural source snapshot only after the
   complete Ergonode tree has been obtained;
3. resolves identity in this order: stored mapping, accepted draft mapping,
   then one unique normalized-name match under the expected Magento parent;
4. compares the resolved parent and sibling order, then applies create,
   mapping, move and order changes parent-before-child;
5. derives deletion candidates only from historically mapped Magento
   categories whose Ergonode codes are absent from the complete source tree;
6. protects the Magento root, consumed mappings, excluded categories and their
   subtrees. An unmanaged or excluded descendant blocks deletion of its mapped
   ancestor;
7. preserves missing categories and mappings. Candidate IDs remain informational
   for existing result consumers; synchronization never invokes deletion.

The legacy `remove_missing` column is retained without migrating stored data,
but no longer authorizes automatic deletion. Physical category deletion requires
a separate manual Magento action. The module does not consume
`categoryTreeDeletedStream` or automatically deactivate local configurations.

## Storage

The following shared tables are owned by `Ergonode_Category`; Category Consumer
uses its persistence services and contracts:

- `ergonode_category_tree` stores Category Tree configurations using
  `category_tree_id`.
- `ergonode_category_tree_option` caches available Ergonode trees.
- `ergonode_category_snapshot` stores the latest source tree state.
- `ergonode_category_mapping` stores manual Category Mapping and
  Magento synchronization status.

Structural snapshots remain scoped by `category_tree_id`. Global category
entity snapshots belong to the optional `Ergonode_CategoryAttributeConsumer`
because they are populated and consumed only by `categoryStream`. The same
`tree_code` may be active for multiple roots, while identity remains
`(category_tree_id, ergonode_category_code) -> magento_category_id`.

This shared schema is intentionally a clean, destructive contract. Upgrading from an
earlier schema version requires dropping the affected tables owned by `Ergonode_Category` before
`setup:upgrade`; existing module data is not migrated or supported.

## Scheduling, streams and cursors

`CategoryStreamEligibility` owns the shared eligibility checks for both persistent
streams: the global connection must be enabled, category data must be enabled for
`category_stream`, and at least one Category Tree mapping must be active. Both
process entry points validate eligibility before invoking an importer. CLI and Admin receive the blocking reason; Cron uses the same
policy plus the respective cron switch and silently skips unavailable work.
With no active mappings, neither stream is read and neither cursor is reset or
saved, including `--reset-cursor` and Admin Download again. Manual tree refresh,
targeted mapping backfill and standalone explicit cursor reset are outside this
policy. Inactive-tree events still advance the global cursor when another mapping
is active; this policy does not introduce per-tree cursors or replay guarantees.
Optional history interceptors can still audit a rejected manual invocation;
the stream importer is not invoked by that audit.
The read-only `CategoryStreamAvailabilityProviderInterface` exposes the same
blocking reason to the Admin UI. It neither starts a process nor changes a cursor;
`CategoryStreamEligibility` is its implementation and the sole owner of the checks.

Cron schedules the structural job when the global connection and category cron
are enabled:

- `category_tree_stream` triggers full `apply` for every active root of each
  changed `tree_code`.

This module owns the `category_stream` process, enablement and its independent
cron schedule. It deduplicates codes, fetches each mapped entity once and fans it
out to active mapped trees. The optional `Ergonode_CategoryAttributeConsumer`
replaces `CategoryEntityLoaderInterface` and `CategoryEntitySynchronizerInterface`
through DI: it includes attributes and snapshots while reusing the base name
synchronizer. There is one cursor, lock, CLI entry point and cron job for this
stream. Source membership and exclusions on either side, including ancestors,
are checked before stream writes. Category's `CategoryExclusionResolver` computes the same
protection for structure and data: exclusions propagate down both hierarchies
and across accepted mappings until no additional node is protected. This covers
categories whose source and Magento parents differ. The data provider caches
this result per tree within a stream run and keeps raw visibility flags intact
for history. Missing or cyclic active ancestry remains ineligible for data writes.

There is no separate category deleted stream consumer. A complete tree refresh
is the single source of truth for category presence, deletion candidates and
attribute backfill eligibility. Historical entity snapshots are never applied
to a tree after their category code disappears from that tree snapshot.

The two processes never invoke one another. CLI and Cron delegate to the same
process contract for a given stream. Both processes, mapping writes, attribute
mapping backfill and explicit cursor reset use one synchronization lock.

Each process has its own persistent cursor. A cursor advances through unrelated
events, but is saved only after all pages and selected handlers for the current
run return successfully. An exception such as HTTP 429 leaves that stream's
cursor unchanged for retry. Reconciliation conflicts block deletion and cursor advancement, including execution
errors returned as conflicts. An ordinary Sync retries the affected trees from the
previous cursor. In managed Admin runs, both stream importers return pending cursors;
the action commits them only after all selected stages finish without conflicts.
Pause and failure keep the pending cursors uncommitted; Resume reuses completed stage
results. CLI/Cron commit at the end of their independent process. Explicit cursor
reset and Sync (force) still clear the selected cursor first, so an interrupted full
comparison can be retried with ordinary Sync. When category data synchronization is disabled, `category_stream` is not read
and its cursor remains paused. With the extension enabled, that switch pauses
both names and attributes.

The two persistent stream cursors are independent of the temporary cursor
used only while fetching all pages of one complete `categoryTree(code)`.
Legacy `category_tree_deleted_stream` or `category_deleted_stream` cursor rows
may remain in an upgraded database, but no runtime code reads or writes them.

The module registers `category_tree_stream` and `category_stream` in the shared synchronization
status provider. Disabling this module removes the capability from the Admin
status view even when a legacy cursor row remains in the database.
It also contributes lock-aware synchronization and cursor-reset operations for
both processes to the shared executable-operation registry.

Creating a Category Tree configuration is an explicit bootstrap operation in
Admin: refresh the available Ergonode trees, configure a Magento root and run
the first full synchronization. Stream jobs neither create configurations nor
rewind a global cursor for a new configuration. When an existing Magento
category receives its first mapping, the exact Ergonode category is fetched and
its mapped attributes are synchronized directly, without resetting
`category_stream`.

## Admin configuration

Structural stream configuration is available under Stores > Configuration >
Ergonode > Categories:

- `ergonode_categories/cron/status` enables scheduled execution and is disabled
  by default;
- `ergonode_categories/cron/schedule` contains the five-field Magento cron
  expression and defaults to `*/20 * * * *`.

The Categories section also owns the Names schedule. The attribute UI extension
renames it to Attributes schedule; it does not add another cron. Existing stored
paths `ergonode_category_attributes/synchronization/status` and
`ergonode_category_attributes/cron/{status,schedule}` are deliberately retained via
`config_path` to preserve saved settings without a data migration. This base module
owns their defaults and uninstall cleanup. `ergonode_categories/synchronization/name_mode`
selects Keep or Update; the extension adds In a field.

Each Category Tree stores `is_active` and the retained legacy `remove_missing` value.
Disabling cron does not disable Admin UI or CLI execution. Neither value enables
automatic deletion of Magento categories.

## CLI

Consume the persisted structure stream through the same process used by Cron:

```bash
bin/magento ergonode:category-trees:sync
bin/magento ergonode:category-trees:sync --reset-cursor
```

The default mode reads `categoryTreeStream` from its global persisted cursor,
deduplicates changed `tree_code` values and performs a complete fresh `apply`
only for active local Category Tree configurations matching those codes. Stream
events for inactive or unconfigured trees advance the cursor without loading
their complete trees.

`--reset-cursor` resets that global cursor, reads the stream to its current end
and performs a complete fresh `apply` for every active local Category Tree
configuration, regardless of which codes were returned by the stream. The
selection comes from `ergonode_category_tree`, so inactive and unconfigured
Ergonode trees are not loaded. Reset, stream traversal, reconciliation and the
new cursor save are serialized by the category synchronization lock. A failed
run leaves the cursor reset for a complete retry.

Consume category data (names and optional attributes) through its shared command:

```bash
bin/magento ergonode:categories:sync
bin/magento ergonode:categories:sync --reset-cursor
```

The commands are independent. A completed structural run with reconciliation
conflicts keeps its cursor unchanged and returns CLI failure; Cron logs a warning and
Admin shows a warning. A conflict-free completion is successful in all three
adapters.

## Applying mapping and mode changes

Saving mappings retains targeted fetch/backfill and never resets a stream cursor.
Changed mappings and synchronization settings show a notice to use Download again.
This action resets only the selected stream and processes it from the beginning;
a failed run does not advance the reset cursor. Enabling the attribute extension
requires this explicit reread to populate data previously consumed as names only.
The main Update action runs tree reconciliation before category data. All actions
cover active configured trees globally, not just the tree selected in the UI.

Rate-limit reporting preserves the originating message, including internal
Magento quota failures, together with the retry delay. Request limiting remains
owned by `Ergonode_Core`; this module does not maintain its own request quota.

## Operator progress and pause

CategoryConsumer owns the short-lived state and safe checkpoints of an Admin
synchronization. CategoryConsumerAdminUi owns the request adapters and popup.
The existing dependency direction is unchanged. An unguessable run identifier
correlates execution, status and pause requests, all guarded by the existing
category synchronization ACL and Magento form keys. The execution request closes
the PHP session before starting work so status and pause can run concurrently.

The popup polls the actual stage and completed category count. It can recover a
completed result when the long execution response is lost. It does not guess a
percentage or ETA. Pause takes effect at the next checkpoint, after the current
category operation or remote request. Already committed changes remain. Resume
requires an explicit request for the same run and scope; it rechecks the unfinished
stage and reuses completed tree/data results. Closing a paused popup leaves its
changes saved; a new Sync starts a new comparison. Browser navigation does not
cancel the PHP process. Exact resume after navigation is outside this basic flow.

The CategorySynchronizationStateInterface port separates run control from storage.
Control state uses untagged Magento cache entries with a one-day lifetime; it
requires no schema or data migration. Losing that state stops the worker at its
next checkpoint. Pause requests have a separate cache key, so progress updates
cannot overwrite them. The category synchronization lock covers both stages of a
managed run and is released on pause or failure. CLI and cron keep their existing
entry points; progress checkpoints are idle outside a managed Admin run.

Category order uses the actual predecessor, not contiguous numeric positions.
The platform adapter loads a fresh category and invokes its model move operation,
preserving Magento events, index updates and cache invalidation without the
CategoryManagement adapter's append/clamping behavior. An unchanged order causes
no move. The optional CategoryDataWorkProviderInterface lets a synchronizer report
that fetching category data would do no work. The base Keep-name mode uses it;
existing attribute consumers without this capability continue to receive data.

## Synchronization preflight and progress

Structural availability validates the existing CategoryCreationConfigurationProviderInterface
contract before the importer can reset a cursor or query Ergonode. Admin, CLI and cron
share this policy; direct reconciliation apply repeats it before refreshing the snapshot.
Preview, snapshot-only refresh, explicit cursor reset and existing-category data updates
do not require category-creation mappings. They do not create categories.

The optional attribute consumer remains the owner of attribute mapping validation.
No reverse dependency on that extension is introduced. CategoryTreeDownloadProgress
observes the existing CategoryTreePageReader implementation through an after plugin,
leaving pagination and transport owned by Category. This concrete interception point
is necessary because the reader currently has no page-progress contract; a new base
contract is outside this change. Counters describe received nodes and completed pages,
not an estimated total. The adapter is idle outside a synchronization run.

Structural results include tree_results: identity, reconciliation statistics, total
conflict count and up to ten primary conflict messages per tree. No-event runs report no new Ergonode changes,
which does not certify that historical conflicts have been resolved.

### Data reads when connecting existing categories

Automatic reconciliation and manual mapping saves consult the optional
`CategoryDataWorkProviderInterface` on the mapped attribute synchronizer once per
pass/save. Without configured name or attribute updates they persist mappings
without fetching individual Ergonode entities. Implementations without this
capability retain their existing synchronization behavior. Explicit category-data
refresh still calls `synchronize()` and can refresh the entity snapshot even when
no Magento value mappings are configured; `hasWork()` describes automatic work.

Admin progress records cumulative created, moved (including reordered), and deleted
Magento operations immediately after successful writes. Counts survive tree changes
and pause/resume; repeated comparison passes add only newly executed operations.
They describe the entire run, not only the current category or download page.

## Missing sources and operator decisions

Every structure/data synchronization checks all active remote tree codes, even
without deletion events. A confirmed missing tree produces a per-tree conflict;
other structural trees can still run. Its cursor stays at the previous position
so recovery does not lose events. Data synchronization filters affected mappings,
processes healthy targets, then reports the blocked sources without advancing its
cursor. Transport/authentication failures propagate without being classified as deletion.

Category owns persisted source observations. A returning tree must complete a
fresh download before its old snapshot can be used; structural synchronization
includes such trees even without a change event. Preview and mapping save reject
known unavailable/stale sources. No configuration is automatically disabled.

CategorySourceIssueProvider uses absences recorded by a complete remote download
and exposes still-mapped codes with their existing Magento labels/IDs. Local
snapshot removal alone is never evidence of remote absence.
This includes protected categories, independently of deletion eligibility. No
comparison is presented as confirmed absence while the source needs rechecking.
CategoryConsumerAdminUi renders these data and delegates retry/disable to existing
refresh/configuration operations. Existing model dependencies follow this family's
current integration pattern; no new module dependency is introduced.

The standalone CategoryDeletionExecutor remains available for existing explicit
callers (including CategoryAttributeConsumer integration coverage), but is no
longer a dependency of automatic reconciliation. Result fields `deleted` (always
zero in synchronization) and `delete_candidates` remain for history/UI consumers.

Category attribute mapping ACL definitions now belong to CategoryAttribute. Existing
resource identifiers are retained for role grants; this module defines tree permissions.

## Environment-specific automation settings

Global `etc/di.xml` registers the following owned settings as `environment`
with `Magento_Config` through `Magento\Config\Model\Config\TypePool`:

- `ergonode_categories/cron/status`
- `ergonode_categories/cron/schedule`
- `ergonode_category_attributes/cron/status`
- `ergonode_category_attributes/cron/schedule`
- `ergonode_category_attributes/synchronization/status`

Configuration export treats these switches and schedules as installation-specific.
Their defaults and runtime interpretation are unchanged; they are not sensitive.

## Automatic synchronization mode

Only the read connection mode permits scheduled synchronization. Core owns
AutomaticSynchronizationInterface; synchronization cron entrypoints consult it.
The read-and-write mode preserves manual operations and worker execution. The
restriction does not disable the whole cron group: manually requested media scans
and history housekeeping remain operational. Domain Admin modules register only
automation configuration paths with CoreAdminUi's visibility plugin. Saved values
are retained; manual import policies and settings remain visible.

## Unavailable connection in scheduled work

Automatic synchronization and recovery use Core's fresh connection probe before
starting domain work. Missing or invalid configuration and unsuccessful probes
skip the run without changing cursors, enqueueing work or logging connection
errors. A rejected connection discovered during execution is also skipped;
unexpected failures remain visible. Existing work is retained for the next run.
The local media scan and history retention remain independent of this policy.
Unit tests cover repeated rejection and recovery; the project integration cron
contract exercises Magento scheduling with existing work and stored checkpoints.


## Paczki i atomowość zapisu

`CategoryEntityLoaderInterface::loadMany` pobiera unikalne kody przez aliasy GraphQL
w paczkach do 50. Bazowa implementacja normalizuje nazwy, a opcjonalny consumer
atrybutów posiada własne pola i paginację wartości. Wspólny builder należy do
Category i nie wybiera poświadczeń. `load` pozostaje wejściem pojedynczego kodu.

Proces danych przetwarza kolejne paczki bez buforowania wszystkich encji. Kody
streamu i wymagane stany drzew nadal zajmują pamięć proporcjonalną do ich liczby.
Stan mapowań jest czyszczony przed i po imporcie, także przy wyjątku. Procesy
ustanawiają zakres współdzielenia odczytów z Category; kolejne lokale tego samego
drzewa dostają osobne snapshoty z jednego kompletnego pobrania.

Ukończone paczki pozostają zapisane przy późniejszym błędzie. Kursor nie przechodzi
dalej; retry porównuje aktualne wartości. Cache frontendu jest czyszczony zbiorczo,
również przy częściowym zapisie. Wykluczony zapisany cel i jego chronieni przodkowie
blokują modyfikacje bez utraty tożsamości.
Wykluczenie celu albo korzenia Magento obejmuje źródłowych potomków również przez
niezmapowane węzły. Ochrona zaakceptowanych mapowań w obu hierarchiach jest ustalana
przed dopasowaniem nazw, więc kolejność gałęzi nie zmienia chronionych tożsamości.
Takie wykluczenie nie blokuje kursorów; aktywne orphan/cykle nadal są konfliktami.

`CategoryMappingDataPreparer` pobiera nowe ręczne mapowania przed transakcją.
`CategoryMappingDataWriter` łączy zapis układu i wartości w transakcji przez
`CategoryWriteTransactionInterface`; adapter bazy należy do Consumer/ResourceModel.
Błąd wycofuje układ, widoczność, snapshot encji i wartości na wspólnym połączeniu.
Przygotowanie metadanych przed transakcją nie należy do tego rollbacku.
Aktualny pełny kontrakt opisuje [RECONCILIATION_PLAN.md](RECONCILIATION_PLAN.md).

## Rozszerzenie neutralnego zapisu

Wspólny zapis i walidacja layoutu, blokada operacji, odświeżanie źródła, kontekst
formularza i odczyt katalogu zostały przeniesione do Category. Consumer korzysta z ich
aktualnych kontraktów bez dawnych aliasów. `CategoryMappingSaveHandler` implementuje
Category\Api\CategoryMappingSaveHandlerInterface i komponuje istniejące
CategoryMappingDataPreparer/CategoryMappingDataWriter. Przy wyłączonym Consumer zapis
neutralnych mapowań nadal działa; nie uruchamia wtedy kopiowania danych do Magento.

### Ochrona danych przy ręcznym mapowaniu

Handler otrzymuje jawny identyfikator drzewa z neutralnego kontraktu Category.
Po zapisie mapowań i visibility, a przed synchronizacją wartości, DataWriter
kwalifikuje operacje przez CategoryDataMappingProvider na świeżym stanie drzewa.
Provider korzysta z bazowej reguły CategoryExclusionResolver współdzielonej ze strukturą i streamem;
uwzględnia wykluczenia obu stron, ich potomków i zaakceptowane nowe tożsamości.
Chronione nazwy/atrybuty pozostają bez zmian, a mapowania i surowa visibility
zostają zapisane. Ręczny zapis nie korzysta z cache przebiegu streamu ani jego
warunku aktywności drzewa. Nie resetuje żadnego kursora. Pobranie encji pozostaje
przed transakcją, lokalne zapisy nadal są atomowe.
