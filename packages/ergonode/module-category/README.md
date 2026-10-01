# Ergonode Category

## Granica modułu i zasady rozbudowy

Moduł jest właścicielem wspólnego modelu kategorii Ergonode: konfiguracji powiązań
drzew, snapshotów struktury, mapowań tożsamości, propozycji automatycznego dopasowania
i dostępu do zdalnego rejestru. Umieszczaj tu reguły odczytu i przechowywania tych danych. Decyzje o zmianach
w katalogu Magento należą do [CategoryConsumer](../CategoryConsumer/README.md).

### Mapa odpowiedzialności rodziny Category*

| Funkcjonalność | Moduł odpowiedzialny |
| --- | --- |
| Wspólne dane drzew, snapshot struktury, mapowania, propozycje dopasowania i zdalne ID | Category (ten moduł) |
| Wykonanie synchronizacji i zapis kategorii Magento, procesy struktury i danych | [CategoryConsumer](../CategoryConsumer/README.md) |
| Neutralne mapowania atrybutów/opcji, widoczność i metadane Magento | [CategoryAttribute](../CategoryAttribute/README.md) |
| Rejestr źródłowy, tworzenie definicji/opcji, snapshot encji i zapis wartości EAV | [CategoryAttributeConsumer](../CategoryAttributeConsumer/README.md) |
| Panel mapowania drzew, automatyczne podpowiedzi, wspólna konfiguracja i nawigacja | [CategoryAdminUi](../CategoryAdminUi/README.md) |
| Kontrolki synchronizacji i ustawienia importu | [CategoryConsumerAdminUi](../CategoryConsumerAdminUi/README.md) |
| Ekrany atrybutów/opcji i ich ustawienia w Categories | [CategoryAttributeConsumerAdminUi](../CategoryAttributeConsumerAdminUi/README.md) |
| Historia neutralnych mapowań atrybutów kategorii | [CategoryAttributeHistory](../CategoryAttributeHistory/README.md) |
| Rejestr operacji na drzewach i odtwarzanie ich historycznego stanu | [CategoryConsumerHistory](../CategoryConsumerHistory/README.md) |
| Przeglądarka historii i panel operacji w ekranie mapowania | [CategoryConsumerHistoryAdminUi](../CategoryConsumerHistoryAdminUi/README.md) |
| Bezstanowa publikacja encji kategorii i wartości przez GraphQL | [CategoryPublisher](../CategoryPublisher/README.md) |
| Ręczna publikacja z Admina, sesja REST i zapis zdalnej hierarchii | [CategoryPublisherAdminUi](../CategoryPublisherAdminUi/README.md) |

### Dane, konfiguracja i kontrakty

- [Schemat](etc/db_schema.xml) należy do tego modułu: `ergonode_category_tree`,
  `ergonode_category_tree_option`, `ergonode_category_snapshot` i
  `ergonode_category_mapping`. Tożsamość mapowania jest określona przez
  `category_tree_id` i kod kategorii; sam `tree_code` nie identyfikuje korzenia Magento.
- [API](Api) udostępnia odczyt mapowań, odczyt/zapis zdalnych ID, usunięcie wiersza
  snapshotu, aktualizację snapshotu po publikacji i read-only `CategoryAutoMapperInterface`.
  Istniejący konsumenci używają
  również `Model/CategoryTree/CategoryTreeRepository` i
  `Model/Mapping/CategoryMappingWriter`; zmiana ich metod wymaga sprawdzenia wywołań.
- `ergonode_categories/tree/initial_page_size`: tutaj znajdują się domyślna wartość
  i polityka pobierania; pole oraz walidacja wejścia w Adminie są w CategoryAdminUi.
- Usunięcie wiersza snapshotu zachowuje jego potomków, mapowania i kategorie Magento.
  Aktualizacja snapshotu po publikacji zachowuje mapowania oraz zdalne ID.
  Usunięcie lokalnego snapshotu, odmapowanie i fizyczne usunięcie kategorii są różnymi operacjami.
- `--remove-data` usuwa również widoczność `category` we wspólnej tabeli Core
  oraz neutralne ACL drzewa i mapowania. Historyczne identyfikatory ACL
  `Ergonode_CategoryConsumer::*` pozostają stabilne; ich właścicielem jest Category.
  Odinstalowanie samego Consumer zachowuje te dane.

### Zależności i wpływ zmian

Kierunek `A → B` oznacza, że A korzysta z B. [Composer](composer.json) deklaruje
`Category → Language`; kod korzysta także z Core (transport/cache),
dostępnego przechodnio przez Language. Repozytorium stosuje zredukowany
graf zależności Composer: listę `require` czytaj razem z [module.xml](etc/module.xml),
[DI](etc/di.xml) i rzeczywistymi wywołaniami, a nie jako pełną listę powiązań runtime.

Zmiana modelu snapshotu lub mapowania wpływa na CategoryConsumer i jego Admin UI,
CategoryAttributeConsumer, przechwytywanie historii w CategoryConsumerHistory oraz
finalizację publikacji w CategoryPublisherAdminUi. Zmiana normalizacji kodu,
rodzica, kolejności lub zakresu drzewa wymaga sprawdzenia wszystkich tych konsumentów.
Category nie zależy od tych rozszerzeń; historia drzew podpina się do jego API pluginem.

### Poza zakresem i wskazówki dla agenta

Nie dodawaj tu mutacji drzew Magento, mapowania wartości EAV,
harmonogramów synchronizacji, UI, zapisu zdalnych drzew ani relacji produkt–kategoria.
Moduły Category* pozostają niezależne od rodziny Product*; chroni to
[test granicy produktowej](../CategoryConsumer/Test/Unit/Contract/CategoryProductBoundaryTest.php).

Przy rozbudowie dobierz weryfikację do zmienianego kontraktu: [testy jednostkowe](Test/Unit),
[integracja pobierania i snapshotów](Test/Integration) oraz testy odpowiednich konsumentów.
Ta mapa opisuje istniejący kod, nie zatwierdza nowych funkcji ani zależności.
Przed implementacją stosuj [reguły repozytorium](../../../../AGENTS.md), w tym jawne
uzgodnienie umiejscowienia funkcji, nowej zależności i ewentualnej migracji danych.
Przy zmianie granicy aktualizuj README właściciela i dotkniętych konsumentów;
brak zgodności z kodem lub metadanymi zgłoś przed przyjęciem nowej odpowiedzialności.

## Szczegóły działania

Owns shared category contracts, remote category registry access and persistence:
`ergonode_category_tree`, `ergonode_category_tree_option`,
`ergonode_category_snapshot` and `ergonode_category_mapping`.

`CategorySnapshotRemoverInterface::remove()` removes exactly one category row from
the selected local tree snapshot and clears its source cache. It preserves
descendant snapshot rows, identity mappings and Magento categories. A later complete
source refresh can restore the removed row. Invalid input or a missing row raises
`LocalizedException`.

Category Consumer uses the shared data and remote tree access to synchronize Magento.
Category Consumer Admin UI invokes the removal contract for its source-list action.
The optional Category Consumer History module intercepts that contract to record
the before/after delta; Category does not depend on history.

This module does not own Magento synchronization policy, history records or Admin UI.

`CategoryTreeSnapshotUpdaterInterface` records a complete hierarchy after its
successful remote publication. The caller supplies codes and parents in the
accepted tree's preorder. Existing snapshot translations are retained; missing
categories without confirmed details are loaded with the write credential in batches of 50, including
all configured languages. Complete details are required before the existing
transactional snapshot writer runs. Mapping rows and remote IDs are untouched.
This updates the accepted layout without re-reading the entire remote tree;
explicit Refresh still reconciles independently changed remote translations.

The snapshot update contract also accepts confirmed categories supplied directly
by the server-side publisher. For codes absent from the local snapshot, matching
confirmations containing names replace the detail query. Existing translations
remain unchanged; missing or mismatched confirmations use the normal batched
write-scope read. Callers pass an empty confirmation map when none is available.

## Adaptive tree downloads

Full tree reads start with the global `ergonode_categories/tree/initial_page_size` setting (default 700, integers 100–1000). This module owns the default, configuration read and tree-specific page-size policy. After a complete page, a successful response below 2 seconds increases the next request by 100, 2–4 seconds keeps it unchanged, and above 4 seconds decreases it by 100. The normal range is 100–1000. Each download starts from configuration again; no learned state is persisted.

Only the successful GraphQL call is timed. Normalization, persistence and failed attempts are excluded. Incomplete pages do not influence sizing. Timeout/complexity fallback remains bounded through Core's existing retrier, starting with the exact requested size then smaller values from 500, 200, 100, 50, 25. A successful fallback holds its size for the next page instead of growing immediately. Emergency sizes below 100 remain available; slow responses never raise them. Authorization and rate-limit errors propagate unchanged and do not trigger resizing. Accepted pages advance by their cursor and the snapshot is replaced only after the complete download.

## Persistent source observations

CategoryTreeSourceState owns source availability observations in Magento's existing
flag storage, keyed by local Category Tree ID. It records the last check, last
complete snapshot, mapped codes confirmed absent during that download, and whether
a fresh snapshot is required. Manual local snapshot edits do not change that evidence. This requires no
schema change, migration or modification of existing categories/mappings. A missing
observation survives Magento cache cleaning; successful presence checks alone do
not make an old snapshot current. Only a complete download clears that requirement.

CategoryTreeSourceChecker reads the explicit tree code through the existing Core
GraphQL contract. Only an explicit null tree is classified as missing; malformed
responses and transport/permission failures are unavailable. FreshCategoryTreeLoader
records the same states and preserves its previous snapshot when download fails.
The consumer decides which operations are blocked and its Admin UI presents the
state. Neither this storage nor remote checks disable configurations or delete
Magento categories. Existing model access is retained consistently with the
family's current contracts; module dependency directions remain unchanged.


## Scoped category reads

`CategoryQueries::entityBatch()` shares parameterized alias query construction between
read consumers and write-side detail loading, without choosing credentials or
normalizing extension-owned values. Callers split codes into batches of at most 50.
`CategoryTreeDownloadScope` reuses availability responses and one complete remote
tree within an explicitly bounded operation. It separates read/write credentials,
clears state on success and failure, and never replaces per-local-tree snapshot writes.

`CategoryTreeDownloader` owns pagination and normalization of a complete remote tree;
`FreshCategoryTreeLoader` validates the local configuration, writes its snapshot and
records availability. Neither class owns Magento reconciliation.

## Neutralny zapis mapowań i kontekst katalogu

Category posiada `CategoryLayoutSaverInterface`, `CategoryLayoutValidatorInterface`,
`CategoryFormContextProviderInterface`, `CategoryCreationContextProviderInterface` oraz
`CategoryTreeRefreshServiceInterface`. Walidator sprawdza unikalność kodów i celów,
przynależność Magento do korzenia i cykle w czasie liniowym względem węzłów/krawędzi.
Walidacja obejmuje nowe szkice oraz potwierdzone zdalne tożsamości z poprzednich paczek,
które nie trafiły jeszcze do końcowego snapshotu. Pełny szkic należy sprawdzić przed
pierwszą mutacją; walidacja każdej paczki i finalizacji ponawia ochronę przy zapisie.

Wspólna blokada `CategorySynchronizationLock` zachowuje istniejącą nazwę blokady i jest
współdzielona przez zapis, odświeżanie, publikację oraz Consumer. Odczyt katalogu
`MagentoCategoryProvider` zachowuje cache w zakresie operacji; metody aktualizujące
jego indeksy nie zapisują kategorii Magento. Kierunkowe mutacje nadal należą do Consumer.

Walidacja przynależności używa świeżego `MagentoCategoryProvider::getIds()` dla korzenia
i potomków: jeden odczyt ID bez hydratacji modeli oraz nazw/url_key. Zbiór ID jest
współdzielony wyłącznie w ramach jednej walidacji, bez cache między wywołaniami.
Pełny `getCategories()` i jego cache pozostają dla konsumentów danych katalogu.

`CategoryMappingSaveHandlerInterface` pozwala atomowo dołączyć skutki zaakceptowanego
mapowania. Domyślny handler zapisuje wyłącznie layout i widoczność. Consumer dostarcza
handler przygotowujący dane przed transakcją i zapisujący dane Magento razem z layoutem.
Category nie wybiera importu, polityki atrybutów ani transportu publikacji.

Zasoby ACL wspólnego zarządzania i mapowania są zdefiniowane w `etc/acl.xml` Category.
Identyfikatory `Ergonode_CategoryConsumer::category_tree_manage`, `category_tree_save`,
`category_tree_mapping`, `category_tree_mapping_refresh` i `category_tree_mapping_save`
zachowano ze względu na istniejące uprawnienia ról w bazie. Ich definicje działają bez
zainstalowanego Consumer; prefiks jest utrwalonym identyfikatorem, nie zależnością runtime.
Nie wykonano migracji ról ani istniejących mapowań.

### Kontekst rozszerzenia zapisu mapowań

`CategoryMappingSaveHandlerInterface::save` otrzymuje identyfikator drzewa, callback
zapisu i nowe pary kod–ID. Callback zapisuje layout oraz visibility tego drzewa;
rozszerzenie może po jego wykonaniu ocenić świeży stan w tej samej transakcji.
Bazowy handler zachowuje neutralny zapis bez importowania wartości Magento.
Reguły ochrony kopiowania danych należą do CategoryConsumer.

`CategoryAutoMapperInterface` buduje propozycje na bazie lokalnego snapshotu,
widoczności, szkicu mapowań i aktualnego odczytu kategorii Magento. Wspólny resolver
utrzymuje pierwszeństwo zapisanej tożsamości, szkicu i nazwy oraz wykluczenia obu drzew.
Wynik nie zapisuje mapowań i nie tworzy ani nie usuwa kategorii Magento. Consumer używa
tego samego resolvera podczas wykonania synchronizacji, ale sam odpowiada za mutacje,
kursory, cron i politykę importu danych.
