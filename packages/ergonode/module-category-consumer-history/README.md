# Ergonode Category Consumer History

## Granica modułu i zasady rozbudowy

Opcjonalny właściciel historii operacji na drzewach CategoryConsumer: przechwytuje
stan przed i po operacji, zapisuje różnice i odtwarza historyczny stan drzewa.
Nie jest mechanizmem przywracania danych Magento do przeszłego stanu.
[Mapa rodziny i zasady zmiany granic](../Category/README.md#granica-modułu-i-zasady-rozbudowy).

### Dane i kontrakty

- [Schemat](etc/db_schema.xml): `ergonode_category_history_operation`,
  `ergonode_category_history_change_set`, `ergonode_category_history_change`.
  Operacja grupuje wykonanie, change set dotyczy jednego `category_tree_id`,
  a delta zapisuje efektywną zmianę encji źródłowej lub Magento.
- [CategoryTreeHistoryQueryInterface](Api/CategoryTreeHistoryQueryInterface.php)
  udostępnia drzewa, listę/strony operacji i odtworzony stan. Zapytania, liczniki,
  kolejność i kursor stronicowania należą do tego modułu, nie do UI.
- [HistoryActorProviderInterface](Api/HistoryActorProviderInterface.php) pozwala
  podać kontekst administratora bez zależności runtime od sesji Admina.
- [Pluginy DI](etc/di.xml) obejmują zapis layoutu, odświeżenie snapshotu i proces
  struktury CategoryConsumer oraz `CategorySnapshotRemoverInterface` z Category.
  Czytają stan przez `CategoryTreeStateProviderInterface`; mutację wykonuje właściciel operacji.
- `CategoryEntitySynchronizerInterface` obejmuje również niezależny strumień danych:
  każda jego paczka zapisuje operację `synchronize_data` (`categoryStream`) dla
  rzeczywiście wskazanych przez mapowania drzew, także po częściowym błędzie.
  Dotyczy to bazowego zapisu nazwy i opcjonalnego rozszerzenia atrybutów.
  Zagnieżdżone wywołania w operacji struktury/layoutu korzystają z jej capture.
  Nowe delty nazw pozwalają odtworzyć wcześniejsze etykiety także kategorii
  niezmienionych w wybranej operacji; wcześniejszych luk nie uzupełnia się wstecz.
- Posiada niezależną konfigurację zapisu i retencji historii (poniżej); wcześniejsze
  operacje nie są odtwarzane wstecz.

### Zależności i wpływ zmian

[Composer](composer.json): `CategoryConsumerHistory → CategoryConsumer`.
Bezpośrednie użycie API Category jest jawne w [module.xml](etc/module.xml) i DI;
pakiet Category jest dostarczany przechodnio. Baza nie zależy od historii.
Moduł samodzielnie przechowuje historię drzewa i odtwarza jego stan.

Odbiorcą zapytań jest
[CategoryConsumerHistoryAdminUi](../CategoryConsumerHistoryAdminUi/README.md).
Zmiana kształtu stanów/delt, kodów akcji, identyfikatorów lub porządku wpływa na
odczyt już zapisanych operacji i prezentację obu drzew. Zmiana granic przechwytywania
wpływa na grupowanie operacji Admin/CLI/Cron, także błędów i operacji bez zmian.
Zmiana zapisu mapowania lub snapshotu w bazie wymaga sprawdzenia tych pluginów;
obejście przechwytywanych usług może pozostawić zmianę bez wpisu historii.

### Poza zakresem i wskazówki dla agenta

Nie zapisuj tu mapowań ani kategorii Magento, nie implementuj synchronizacji,
publikacji, UI lub historii innych domen. Bezpośrednie zmiany w Magento poza
CategoryConsumer nie są objęte tym rejestrem. Rekonstrukcja do odczytu nie jest undo.

Przy zmianie formatu lub retencji oceń odczyt istniejących danych i ciągłość delty;
usunięcie części historii może uniemożliwić wiarygodną rekonstrukcję. Migracja lub
usuwanie danych wymaga odrębnego uzgodnienia. Dobierz
[testy różnic, zapytań i przechwytywania](Test/Unit),
[integrację zapisu historii](Test/Integration) i testy konsumenta Admin UI.

## Szczegóły działania

Stores append-only, operation-grouped history for Category Consumer tree changes
and reconstructs a selected tree state from reversible per-entity deltas.

Reconstruction undoes later deltas and then applies the selected operation's own
`after` snapshots before deriving mapping labels and codes. These snapshots take
precedence for changed entities when older records contain a gap; a null `after`
removes the entity. This does not backfill missing operations or their counters,
nor guarantee recovery of unchanged entities across an unrecorded mutation.
History capture uses fresh canonical states from CategoryConsumer, including
mapping and Magento changes made within the same process.

One operation represents one Admin save, Admin synchronization, CLI execution,
cron execution, source snapshot refresh or removal from the local source list. An operation contains one change set
per Category Tree and every change set contains the net effective source and
Magento category changes produced by that operation.

The module owns:

- `ergonode_category_history_operation`, the execution header;
- `ergonode_category_history_change_set`, the per-tree group and summary;
- `ergonode_category_history_change`, the reversible before/after entity delta;
- history capture plugins for the public Category Consumer operation contracts and
  `Ergonode\Category\Api\CategorySnapshotRemoverInterface`;
- deterministic tree-state replay and read contracts.

The read contract exposes operation lists both as a bounded compatibility query and as a keyset
page ordered by descending operation ID. Paginated responses include the total count and the cursor
for the next group; they do not load or count history in the Admin UI module.

It does not synchronize categories, mutate mappings, render Admin UI, store
history for other Ergonode domains or depend on another history
module. Direct Magento category changes performed outside Category Consumer are
outside this history.

The direct dependency on `Ergonode_Category` captures `remove_snapshot` around its
public removal contract. Category still owns and performs the removal. History
declares this dependency in `module.xml`, while Composer receives the package
through Category Consumer according to the repository's reduced dependency graph.
History only reads the canonical before/after states through Category Consumer and stores
the delta. Removing a source row leaves its children and Magento categories intact.
Failed removals retain the original exception and use the existing failed-operation capture.
Previously unrecorded removals are not backfilled.

History starts when this module is enabled. Earlier tree states are not
backfilled because the previous parent, order and deleted node payload cannot be
recovered reliably. Retention removes only the oldest complete prefix of operations so replay of retained
operations keeps the later deltas it needs.

## Capture and retention configuration

Owns `ergonode_categories/history/enabled`, `cleanup_enabled`, `retention_days` and `schedule`
under the same prefix. Capture and cleanup default to enabled; retention defaults
to 30 days and the independent cleanup cron defaults to `30 2 * * *` in Magento's
configured timezone. The matching HistoryAdminUi module presents these settings.
Disabling capture bypasses snapshot reads and writes in all areas; operations and
existing history queries remain available. Cleanup is independent of capture.

Cleanup permanently deletes complete operations finished strictly before the UTC
cutoff (now minus the configured number of 24-hour days), including existing old
records on its first scheduled run. Invalid retention values skip deletion.
Deletion is batched and repeatable. It never changes mappings or catalog data.
There is no migration or backfill. Deleted history can only be recovered from a
backup; disable cleanup to retain all records.

Tree cleanup deletes an oldest contiguous prefix by operation ID; an unfinished or
retained operation protects every later operation, even when timestamps are out of
order. Foreign keys cascade deletion to change sets and deltas. Retained states
need only their own and later deltas. Pausing capture can still create unrecorded
changes; retention does not repair that existing replay limitation.

The retention cron intentionally remains independent of Ergonode connection
availability and operating mode. Tests exercise its entrypoint, invalid/disabled
retention, database failures and repeated execution. The project cron integration
contract verifies deletion of old fixture operations with the connection disabled.


## Bounded capture and consistent reads

The synchronization lock covers before-state capture, mutation, after-state capture
and history persistence. Replay and retention acquire the same lock. A structural
run owns one operation header; the reconciliation plugin appends each touched tree
before proceeding to the next. Nested refreshes are suppressed. An unrelated or
empty stream does not capture every configured tree. Headers remain `running` until
completion or a caught failure; a hard process termination may leave an unfinished
header, which the existing retention rule preserves.

`HistoryReader::getChangesAfter` yields later deltas in keyset pages of 500, ordered
by operation ID and change ID descending. The reconstructed tree and changes of the
selected operation are still materialized for the public response. JSON object and
list readers share safe decoding and preserve their separate shape validation.


## Neutralne kontrakty kategorii

Współdzielony kontekst katalogu, zapis layoutu, odświeżanie źródła i blokada operacji
należą teraz do Category. Odwołania do przeniesionych klas używają aktualnych kontraktów
Category; odpowiedzialność importu i historii tego modułu pozostaje bez zmian.
