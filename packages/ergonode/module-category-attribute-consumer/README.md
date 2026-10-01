# Ergonode_CategoryAttributeConsumer

## Granica modułu i zasady rozbudowy

Opcjonalny właściciel rejestru atrybutów kategorii Ergonode, tworzenia definicji
i opcji Magento oraz synchronizacji wartości EAV kategorii. Neutralne mapowania
atrybutów i opcji należą do [CategoryAttribute](../CategoryAttribute/README.md). Rozszerza proces
danych CategoryConsumer przez jego kontrakty; nie przejmuje sterowania procesem.
[Mapa rodziny i zasady zmiany granic](../Category/README.md#granica-modułu-i-zasady-rozbudowy).

### Dane, konfiguracja i kontrakty

- [Schemat](etc/db_schema.xml) obejmuje `ergonode_category_attribute` oraz
  `ergonode_category_entity_snapshot`. Tabele mapowań i ich widoczność należą do CategoryAttribute. Snapshot encji jest globalny według kodu;
  snapshot struktury w Category jest osobny i ma zakres `category_tree_id`.
- Własność obejmuje polityki `ergonode_category_attributes/synchronization/`:
  `name_mode`, `is_active_mode`, `is_active_default`, `include_in_menu_mode` i
  `include_in_menu_default`, a także opcjonalny cel
  `ergonode_categories/synchronization/name_attribute`. Wspólny przełącznik `status`
  i harmonogram danych pod prefiksem `ergonode_category_attributes` należą do CategoryConsumer.
- [Własne API](Api) udostępnia rejestr, mapowania atrybutów/opcji, ich aktualizację
  i usuwanie snapshotu atrybutu. Zapis mapowania zachowuje uzupełnianie istniejących
  kategorii ze snapshotu pod wspólną blokadą synchronizacji.
- [Preferencje DI](etc/di.xml) implementują API CategoryConsumer: ładowanie encji,
  synchronizację i odświeżanie danych, konfigurację i dane przy tworzeniu oraz
  wybór celu nazwy. Aktualizacja nazwy korzysta ze wspólnego synchronizatora bazy.

### Zależności i wpływ zmian

[Composer](composer.json): `CategoryAttributeConsumer → AttributeConsumer` oraz
`CategoryAttributeConsumer → CategoryConsumer` oraz
`CategoryAttributeConsumer → CategoryAttribute`. CategoryAttribute dostarcza neutralne
API mapowań i metadane Magento; consumer przekazuje rozpoznane metadane do zapisu. Pierwszy dostarcza wspólne mechanizmy
atrybutów, drugi proces i punkty rozszerzeń. [module.xml](etc/module.xml) wskazuje
również Category, którego mapowania i struktura ograniczają docelowe kategorie.
Kod korzysta też z Attribute, Language i Core dostępnych w tym grafie.

Zmiana preferencji wpływa na wszystkie wywołania procesu danych, także CLI/Cron,
tworzenie kategorii podczas uzgadniania struktury i odświeżenie po nowym mapowaniu.
Zmiana typu wartości, opcji lub polityki nazwy wpływa na
[CategoryAttributeConsumerAdminUi](../CategoryAttributeConsumerAdminUi/README.md)
i kategorię Magento. Cel bezpośredniej nazwy i zwykłe mapowanie atrybutu nie mogą
konkurować o to samo pole. Dane ze starego snapshotu encji nie uprawniają do zapisu
kategorii nieobecnej już w aktualnym drzewie lub wykluczonej z synchronizacji.

### Poza zakresem i wskazówki dla agenta

Nie implementuj tutaj strukturalnego dopasowania, przenoszenia/usuwania kategorii,
drugiego streamu, kursora, crona, UI ani publikacji do Ergonode. Wspólną obsługę
typów atrybutów rozważ w Attribute/AttributeConsumer; reguły wyłącznie kategorii
pozostają tutaj. Definicje i wartości Magento zapisuj przez istniejące adaptery EAV.

Przy rozbudowie sprawdź [testy jednostkowe](Test/Unit),
[integrację DI, EAV i rozdziału storage](Test/Integration),
[granicę rozszerzenia w bazie](../CategoryConsumer/Test/Unit/Contract/CategoryAttributeBoundaryTest.php)
oraz konsumenta UI. Usuwanie danych modułu nie może kasować kategorii i wartości
Magento ani wspólnego kursora/harmonogramu. Zmiana trwałych danych wymaga osobnego
uzgodnienia migracji zgodnie z regułami repozytorium.

## Szczegóły działania

This optional module owns the Ergonode category-attribute registry, inbound
mapping orchestration and Magento category-value synchronization. Neutral
mapping persistence, visibility and Magento metadata belong to CategoryAttribute. It enriches the `categoryStream` process
owned by `Ergonode_CategoryConsumer` through DI preferences for the entity loader
and synchronizer. The expanded query includes `attributeList`; name updates reuse
the base `CategoryNameSynchronizerInterface` implementation.

Category data is fetched only when it can update a configured name destination or
an active value mapping. The creation provider uses fixed `is_active` and
`include_in_menu` values without a separate entity request when no data update is
configured; required mapped creation values still require a fresh entity. Initial
category names continue to come from the tree snapshot. When data is needed, the
existing entity fetch, value validation and snapshot writing remain in place.

The optional synchronizer implements the existing `CategoryDataWorkProviderInterface`
so the shared stream importer also skips an empty data phase. Skipping does not
advance the data cursor; source definitions and category membership are prepared
before the data-work decision. An explicit cursor reset
still applies; enabling name updates or completing mappings later makes the data
phase eligible again. Use Sync (force) when existing categories need a full refresh
after configuration changes. This policy belongs to this extension and introduces
no separate stream or cross-module dependency.

The module owns attribute mapping policies under `ergonode_category_attributes`.
The shared enablement, cursor and Names/Attributes cron belong to CategoryConsumer.
The extension has no separate stream, command or cron. It also owns the optional
`ergonode_categories/synchronization/name_attribute` destination: only Magento text
fields backed by varchar, without an option source, are allowed. `textarea` and
`url_key` are unavailable. The destination is excluded from ordinary attribute
mapping while active, so two sources cannot overwrite the same value.
The legacy attribute-to-Magento-name mapping remains available in Keep mode; it
is suspended when the direct category name owns Magento `name`.

With `developer:module:backlog --remove-data`, the module removes its two snapshot
tables, the retired deleted-stream cursor and
the exact current or legacy configuration paths declared by its config
provider. It preserves neutral attribute/option mappings and their visibility. It does not
remove Magento categories, category attributes or values,
and it does not touch Category Tree data, the shared `category_stream` cursor,
its cron rows or shared scheduling settings.

Creation-configuration errors name the missing Magento attributes and direct the
operator to complete their mappings or select creation defaults in Category Attributes
configuration under Categories > Attributes. CategoryConsumer consumes the existing configuration contract during
structural preflight, before resetting cursors or requesting remote data.

## Neutral mapping boundary

`CategoryAttributeMappingSaver` resolves source/target metadata and creates pending
Magento attributes only after neutral validation. `CategoryOptionMappingSaver`
resolves pending Magento options. Both delegate mapping persistence to
CategoryAttribute API. Updaters retain the shared synchronization lock and
backfill values after successful persistence. Mapping providers use neutral
read/state APIs and enrich them with current source metadata for inbound use.
`CategoryAttributePolicy` implements the neutral policy extension while retaining
name-target and manual-creation rules owned by this consumer. Its settings and
existing paths are unchanged. Removing the consumer preserves neutral mappings;
no existing records are migrated during this extraction.

## Automatic option mapping

`CategoryOptionAutoMatcherInterface` exposes neutral option suggestions to the
category Admin adapter through the existing consumer dependency. Its implementation
delegates to CategoryAttribute without downloading, saving or backfilling values.
The existing updater remains the explicit save entry point.

## Neutral editor integration

CategoryAttributeAdminUi owns the editor. The optional consumer Admin adapter contributes
source snapshots and inbound actions. `MappingSynchronizationInterface` runs an editor
save callback under the category synchronization lock and then backfills category values.
The runtime attribute/option updaters reuse that same boundary. MagentoOptionCreatorInterface
exposes existing option creation to the inbound adapter; neutral UI cannot create options.
No synchronization is triggered when the consumer adapter is absent.

## Definition deletion and category attribute membership

CategoryAttributeRegistryRefresher reconciles shared definitions and independently
loads every page of categoryAttributeList. A malformed or failed list does not
replace the previous registry. The existing category stream importer is decorated
to prepare this source state before checking data work or fetching stream events,
even when categoryStream has no new entries. Direct entity refresh, creation and
snapshot backfill use the same preparation service. No separate category cron or
cursor is introduced.

Value mappings require a definition present in the category registry and shared
snapshot. Deleted or detached sources are skipped and reported, retaining neutral
mappings and Magento EAV values. Missing values of an existing allowed definition
still follow the normal value-clearing policy. The category source provider cache
is invalidated after registry preparation.

Value mapping preparation requests Ergonode option definitions as a set through
AttributeConsumer's public bulk-read contract. The factory receives those definitions
and retains only category-specific mapping normalization; it performs no per-attribute
Ergonode snapshot reads. Snapshot storage and cache invalidation remain in AttributeConsumer.

## Streaming option lookup preparation

Value mapping preparation consumes AttributeConsumer's `iterateOptionDefinitions`
contract directly into category option-label lookups. It no longer materializes a
second bulk array of all Ergonode definitions. Snapshot pagination/cache policy stays
in AttributeConsumer; category mapping ownership and the resulting lookup shape stay
here. Lookup memory still scales with the labels needed for category synchronization.


## Batched category values

The category loader implements `CategoryEntityLoaderInterface::loadMany` using
read credentials and the Category-owned alias builder. Each alias has its own
attribute cursor; subsequent pages request only unfinished categories. Source
preparation runs before loading values, outside the manual-layout transaction.
The synchronizer invalidates attempted category IDs after a partial write failure,
so already written values cannot remain hidden behind the old frontend cache.

`CategoryEntityNormalizer` owns category attribute type resolution, translations and
content hashes; the loader owns request batching and independent pagination.


## Neutralne kontrakty kategorii

Współdzielony kontekst katalogu, zapis layoutu, odświeżanie źródła i blokada operacji
należą teraz do Category. Odwołania do przeniesionych klas używają aktualnych kontraktów
Category; odpowiedzialność importu i historii tego modułu pozostaje bez zmian.

## Backfill protection and cache completion

Backfill uses CategoryConsumer's existing CategoryDataMappingProvider for effective
source/target/ancestor eligibility, intersected with current tree snapshot membership.
Eligibility is reset per operation and matched by tree, source code and target ID
before deduplication. CategoryBackfillSnapshotReader owns the SQL behind a narrow
internal read port; it does not decide synchronization policy. The existing
CategoryCacheInvalidator runs once for changed or partially written category IDs;
no-op and excluded categories do not trigger cache cleaning.

The concrete CategoryConsumer provider and cache adapter are existing collaboration
points. A future public port from that owner can replace them; this repair does not
introduce a new inter-module edge or duplicate its exclusion logic.

Manual category refresh uses the same CategoryDataMappingProvider eligibility before
source preparation or entity fetching, inside the existing synchronization lock.
It matches the current tree, source code and Magento target ID and rejects excluded
sources, targets and descendants. Provider state is cleared for every refresh so
repeated requests observe current exclusions. A rejection leaves EAV and the entity
snapshot unchanged and does not invalidate the frontend cache. The form context
continues to describe identity; it does not own synchronization eligibility.
