# Ergonode_CategoryPublisher

## Granica modułu i zasady rozbudowy

Właściciel bezstanowej publikacji encji kategorii do Ergonode przez GraphQL:
planuje i wykonuje tworzenie, aktualizację oraz jawne uzgadnianie stanu kategorii,
jej nazw. Przyjmuje żądany stan od wywołującego; publikację atrybutów rozszerza
CategoryAttributePublisher przez kontrakt contributorów.
[Mapa rodziny i zasady zmiany granic](../module-category/README.md#granica-modułu-i-zasady-rozbudowy).

### Dane i kontrakty

- Nie posiada tabel, trwałych zdalnych ID, statusów publikacji ani własnej
  konfiguracji połączenia. Kody języków i stan docelowy dostarcza wywołujący;
  konfigurację zapisu i transport współdzieli z Publisher/Core.
- [CategorySynchronizerInterface](Api/CategorySynchronizerInterface.php) oraz
  [CategoryBatchSynchronizerInterface](Api/CategoryBatchSynchronizerInterface.php)
  obsługują `create_only`, `create_strict`, `update` i `reconcile`.
  [API danych](Api/Data) opisuje stan i wynik, w tym potwierdzenie istnienia referencji.
- `CategoryDesiredStateFactoryInterface` tworzy stan docelowy;
  `CategorySynchronizationContributorInterface` rozszerza plan o następny
  wykonywalny etap. Planowanie kategorii pozostaje tutaj, wykonanie wspólnych mutacji w Publisher.
- Publikacja wartości może dodać atrybut do globalnego rejestru kategorii.
  Brak atrybutu w jednej kategorii nie uprawnia do usunięcia go z globalnego rejestru.

### Zależności i wpływ zmian

[Composer](composer.json) i [module.xml](etc/module.xml):
`CategoryPublisher → Publisher`; kod korzysta również z Core poprzez ten graf.
Nie ma zależności od CategoryConsumer, CategoryAttributeConsumer ani rodziny Product*.
Mapowanie pól Magento i tworzenie stanu wejściowego nie są częścią tego synchronizatora.

Obecnym odbiorcą runtime jest
[CategoryPublisherAdminUi](../module-category-publisher-admin-ui/README.md).
Zmiana wyników, trybu strict, obsługi kolizji, retry lub potwierdzonych nazw wpływa
na kolejkę ręcznej publikacji, zapis zdalnych ID i finalizację lokalnego snapshotu.
Kolizja w `create_strict` nie uprawnia do zmiany istniejącej kategorii;
niejednoznaczny wynik transportu nie jest potwierdzeniem utworzenia ani nieistnienia.
Zmiana transportu Publisher wpływa także na publisherów innych domen i powinna być
zweryfikowana na ich kontraktach, jeśli rozbudowa trafi do wspólnej warstwy.

### Poza zakresem i wskazówki dla agenta

Nie dodawaj tutaj UI, sesji administratora, REST-owego zapisu drzew, lokalnych
mapowań, kolejki trwałej ani schedulera. Zweryfikowany kontrakt GraphQL w repozytorium
nie udostępnia zapisu hierarchii; ręczne tworzenie drzewa i jego układu obsługuje
CategoryPublisherAdminUi. Nie zakładaj przyszłego API na podstawie nazwy modułu.

Przy zmianie mutacji sprawdź [kontrakt schematu](Test/Unit/Contract/SchemaContractTest.php),
[granicę modułu](Test/Unit/Contract/ModuleBoundaryTest.php),
[testy synchronizacji](Test/Unit/Model/Sync) i konsumenta Admin UI.
Automatyzacja zdalnych drzew lub przejęcie własności trwałych danych wymaga nowego,
jawnego uzgodnienia granicy; obecna dokumentacja tego nie zatwierdza.

## Szczegóły działania

This module reconciles category entities and names to Ergonode through GraphQL
mutations. CategoryAttributePublisher owns attribute registry and value publication
through the contribution contract.

It owns the category entity only. Automatic category tree creation, hierarchy
and leaf placement are unavailable because the verified GraphQL schema exposes
no tree write mutations.

## Scope

- create and update categories identified by stable category code;
- update translated category names;
- add attributes to the global category-attribute registry before values;
- publish and remove translated category attribute values;
- optionally remove categories in explicit reconcile mode;
- expose a category synchronization contract for tree and catalog workflows.

The module reads current Ergonode state for update/reconcile operations and does
not persist remote identifiers or publisher status. Callers provide Ergonode
language codes in the desired state.

`create_only` resumes missing non-destructive stages for an existing category,
including allowed attributes and values. Plans expose only the next executable
stage. Category values validate and normalize their translation payloads by the
12 supported schema types before a mutation is built. Synchronization results
state both their terminal outcome and whether the category reference is
verified present, absent or remains unknown.

`create_strict` sends category-create mutations directly, in batches of up to 50.
A successful response containing the requested category code is authoritative:
there is no routine existence query before creation or confirmation query after it.
Rejected creates and incomplete responses are resolved with a batched existence
query; an existing code produces a no-op and is never renamed or otherwise
modified. Callers use that no-op to report or reuse a category-code collision.
Ambiguous transport failures verify the batch before retrying only unapplied
operations. Verification is shared within an attempt and refreshed for another
attempt; HTTP rate limits retain the publisher's safe retry policy.

The GraphQL schema exposes category-attribute registration globally, not per
category. This entity synchronizer therefore never removes registry entries;
global pruning requires a separate registry-wide desired state.

`Ergonode_CategoryPublisherAdminUi` optionally supports tree creation and
layout changes as explicit administrator operations. It creates category entities
through this GraphQL module and uses the shared Publisher REST connection for
manual tree operations. Publisher owns credentials and transport; PublisherAdminUi
owns login and the session storage adapter. Remember me selects persistent storage
instead of session storage, as described in the PublisherAdminUi README. Category
publication checkpoints remain in the admin session. The manual workflow is
unavailable to cron, CLI and catalog automation.

See [PLAN.md](PLAN.md) for the archived Phase 2 implementation plan. The module
boundaries in this README and the AdminUi README describe the current runtime.

Category-create mutation results include the returned code and translated names.
The existing mutation-result contract carries this server response to callers;
the module remains stateless. Names are not assumed from submitted inputs.

## Batch reads and verification

Update, create-only and reconcile read category states in batches of up to 50.
Each category retains its requested language scope; reconcile reads all names.
A missing response alias is incomplete evidence and fails the read instead of
being interpreted as an absent category. A successful batch of 50 name updates
uses two read requests and one mutation request, excluding attribute contributors.
Ambiguous mutation verification reads only the categories of the dispatched batch,
shares reads and contributor plans within an attempt, and resets them before
each subsequent attempt. No state survives the synchronization call.

`CategoryBatchSynchronizationContributorInterface::forBatch()` optionally prepares
an isolated contributor for one planning or verification round. It receives only
live categories whose core state matches. The original contributor retains no
batch state; each round reads fresh data. Existing single-category contributors
remain supported. CategoryAttributePublisher uses this contract for batched value
reads and a shared registry read; this module does not acquire attribute ownership.

Numeric category codes retain their string identity when correlated with mutation
responses, including `"0"`, `"123"` and codes with leading zeroes such as `"001"`.
PHP may convert a result-array key to an integer; the category code carried in the
operation and response remains a string.
