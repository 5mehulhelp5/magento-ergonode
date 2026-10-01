# Ergonode_CategoryPublisherAdminUi

## Granica modułu i zasady rozbudowy

Opcjonalny właściciel ręcznej publikacji kategorii i hierarchii z Magento Admin.
Łączy panel mapowania i formularz kategorii z GraphQL CategoryPublisher oraz
wspólnym klientem REST Publishera do zdalnego drzewa. To obejmuje serwerowe usługi ręcznej
operacji, nie tylko przyciski i prezentację.
[Mapa rodziny i zasady zmiany granic](../module-category/README.md#granica-modułu-i-zasady-rozbudowy).

### Dane i punkty integracji

- Nie deklaruje tabel ani trwałego schedulera. Przechowuje szkice i kolejkę bieżącej
  operacji w karcie przeglądarki, a cache tożsamości i potwierdzenia
  zakończonych etapów w sesji Admina. Nie jest to trwałe zadanie w tle.
- Category jest właścicielem zdalnych ID w mapowaniu oraz lokalnego snapshotu.
  Moduł zapisuje je przez `CategoryRemoteIdentityWriterInterface` i
  `CategoryTreeSnapshotUpdaterInterface` po odpowiednim potwierdzeniu zdalnym.
- [DI](etc/di.xml) dopina plugin do konkretnego
  `Category/Model/Mapping/CategoryLayoutSaver`. Zapis podstawowego layoutu
  i jego blokada pozostają w Category. Zmiana tej klasy może wpływać na
  publikację nawet przy niezmienionym publicznym interfejsie zapisu.
- Moduł korzysta z `veaCategoryMappingApi` gospodarza: szkice, `batchUpdate`,
  finalny zapis i odzyskiwanie po wymaganym logowaniu. Model selekcji, renderowanie
  i zdarzenia zapisu należą do CategoryAdminUi.
- Poświadczenia ręcznego REST są odrębne od konfiguracji zapisu GraphQL.
  [Model/ManualRest](Model/ManualRest) adaptuje zasoby kategorii do wspólnego
  transportu Publishera; limity żądań pozostają w Core, a poświadczenia w Publisherze.

### Zależności i wpływ zmian

[Composer](composer.json): zależności od CategoryPublisher (encje GraphQL),
CategoryAdminUi (panel mapowania) i PublisherAdminUi (wspólny adapter Admina).
[module.xml](etc/module.xml) wskazuje też Category. Kod korzysta z Category,
Publisher, Language i Core dostępnych w grafie; te powiązania runtime trzeba
uwzględnić przy zmianie implementacji, nawet jeśli nie są osobnymi wpisami `require`.

Zmiana payloadu hierarchii, generowania kodów albo kolejności finalizacji wpływa
na zdalne drzewo, zapis mapowań w Category, snapshot w Category oraz
historię przechwytującą zapis layoutu. Zmiana obsługi sesji/retry wpływa na możliwość
dokończenia częściowo opublikowanej operacji. Potwierdzenia utworzenia i nazw
muszą pochodzić z serwera; szkic przeglądarki nie jest takim potwierdzeniem.
Brak lokalnego REST ID nie oznacza, że istniejącą kategorię trzeba utworzyć ponownie.

### Poza zakresem i wskazówki dla agenta

Nie przenoś tu synchronizacji Ergonode → Magento, dopasowania drzew ani własności
schematu mapowań. Ogólne mutacje encji rozwijaj w CategoryPublisher, a komponenty
panelu w CategoryAdminUi. Operacji ręcznych nie udostępniaj automatycznie
cronowi, CLI ani publikacji produktów; byłaby to zmiana granicy i sposobu autoryzacji.

Przy zmianie przepływu sprawdź częściowy sukces, kolizję kodu, opóźnione ID,
wygaśnięcie sesji i finalizację snapshotu w [testach PHP](Test/Unit) oraz
[testach JS](Test/Js). Sprawdź także kontrakty CategoryPublisher, snapshotu Category
i zapisu layoutu Category. Przed zmianą UI przeczytaj
[kontrakt Storybook](../../../dev/tools/ergonode-storybook/README.md)
i użyj istniejących [stories](Test/Storybook).

## Szczegóły działania

This optional admin adapter contributes category and category-tree publishing
actions to the CategoryAdminUi mapping workspace. It owns preparation
of publication drafts and delegates transport and publication to the existing
publisher services. CategoryAdminUi owns the mapping model, selection,
rendering and persistence entry point.

Bulk creation prepares categories in parent-first order within the workspace's
synchronous `batchUpdate` callback, so completed edits render once. It reads
one model snapshot and indexes successful drafts as it proceeds;
children can therefore refer to parents prepared in the same batch. Failed
items are not added to the draft indexes. Preparing drafts does not publish
remote categories; publication remains part of the existing save flow.

The save flow owns the publication queue and its pause/resume state in the open
browser tab. Pause lets the in-flight batch finish, then waits before dispatching
another batch or saving the final tree and mappings. A pending rate-limit delay
finishes before the queue pauses; resuming never bypasses that delay. Completed
results, remote IDs and failed/blocked categories remain in the same queue state,
so resume does not replay completed batches. Final saving cannot be paused.
This is not a durable background job: keep the tab open until saving completes.
The existing CoreAdminUi progress dialog supplies the optional controls and
presentation; this module supplies the callbacks and safe queue boundaries.

Draft preparation indexes Magento IDs, source codes and mapped parents once per
operation. Normalized path prefixes are cached for that operation, preserving
root omission, empty segments and the existing 128-character code limit. Only
accepted drafts enter the collision and parent indexes. Empty selections do not
clone either model when updating the bulk toolbar.

Category creation actions require the write configuration before login or draft
preparation. The native Magento category form displays missing settings with a configuration
link and Check again action. The Ergonode workspace uses the shared connection
gate and retains operation-specific write checks on publication controls. The session-status endpoint rechecks this
state before creation and publication; it never returns credentials. The category
form uses the same check. Manual REST authentication remains separate and is
still used for tree operations. Non-retryable publication failures stop the queue
and keep pending work available for correction.

Publication resolves missing REST category IDs from recent list pages (50 rows,
sequence descending), yielding exact code matches and checkpointing partial IDs
through the existing category identity writer. A normal batch of 50 newly created
categories needs one GraphQL mutation and one REST list read before tree saving.
If concurrent creation pushes the batch across pages, matching pages are consumed
until all IDs are found. Sparse historical codes fall back to targeted lookups
instead of scanning the entire catalog. Existing saved IDs require no REST call.

The admin session retains successful GraphQL creates for the current publication
batch until ID resolution completes. A REST rate limit or delayed visibility can
therefore retry ID resolution without repeating successful creates. The receipt
is scoped to the exact request, tree and Ergonode origin, contains no credentials,
and is cleared when the batch completes. It uses no new database schema or durable
publisher status. A different batch replaces the receipt; independent tabs remain
safe through strict-create collision handling. Tree saving still checks the current remote hierarchy for categories missing
from the workspace before writing. After a successful PUT, its exact payload is
flattened into codes and passed to Category's snapshot updater. Finalization
therefore reads details only for categories absent from the local snapshot,
without paginating the entire remote tree. Explicit Refresh remains the full
reconciliation of external changes. The hierarchy builder indexes children by
parent once and retains stable sibling ordering and cycle validation.

Both bulk and individual publication use the workspace's optional `beforeRender`
save callback to clear successful draft flags before the single final render.
Failed saves do not invoke the callback; excluded categories remain pending.

Mapping-only saves compare the saved layout, including existing manual overrides, by parent and sibling order,
not by absolute position numbers: Magento sibling positions and snapshot traversal
positions are different coordinate systems. Unmapping an existing category does
not require a shared REST connection when the hierarchy is unchanged. Imported snapshot
categories can lack a cached REST ID; that absence never means that they need to
be created. Only explicit `to_ergonode.pending_create` drafts enter the browser's
publication queue. The backend still detects actual hierarchy changes and requests
a shared connection when necessary.
If a tree save reports `authentication_required`, the workspace recovery callback
opens the existing login dialog and retries the same save once after login.
Cancellation, another authentication failure and permission denials keep edits
unsaved; successful category-creation batches are not replayed.

Rate-limit reporting preserves the originating message, including internal
Magento quota failures, together with the retry delay. Request limiting remains
owned by `Ergonode_Core`; this module does not maintain its own request quota.

Successful category-create responses retain returned names in the admin session
until the corresponding snapshot write succeeds. This detail receipt spans
publication batches for the current tree and Ergonode origin and is separate
from the receipt preventing duplicate creates. It is saved before rate-limit
handling or REST ID lookup, so retries preserve confirmed names. Only successful
creates with matching codes and returned names enter it; collisions and ambiguous
results keep the normal detail lookup. Included codes are cleared after the
snapshot succeeds; failed finalization and excluded pending codes retain details.
The browser cannot supply these trusted details.

Tree REST identifiers are cached in the admin session by code and origin. Every
save still reads the current tree before PUT. A cached ID returning HTTP 404 or a
different code is invalidated and resolved once again. Authorization failures,
rate limits and server failures do not trigger a fresh lookup. A cold successful
50-category publication normally needs five remote requests; subsequent saves
with a cached tree identity need four (one GraphQL create, one category-ID list,
one current-tree GET and one tree PUT). Collisions or incomplete confirmations
may require additional reads. Persistent credentials are owned by Publisher; this module adds no schema.

## Wspólne połączenie REST

Transport i trwałe poświadczenia należą teraz do Ergonode_Publisher. Logowanie,
status i rozłączenie współdzielonego konta należą do PublisherAdminUi. Moduł kategorii
zachowuje domenowy gateway, cache tożsamości i checkpointy operacji w sesji Admin.
Nie posiada tokenów ani hasła; usunięcie tego UI nie usuwa wspólnego konta integracji.
Popup logowania, jego CSS i transport JSON należą do PublisherAdminUi i są wspólne
z publikacją produktów. Remember me decyduje o trwałym zapisie tokenów; bez niego
połączenie działa tylko w sesji administratora. Operacje i checkpointy kategorii
pozostają w tym module.
Uprawnienie do logowania to `Ergonode_Publisher::rest_connection`; uprawnienia akcji
kategorii pozostają niezależne. REST 429 zachowuje domenowy kontrakt Retry-After.

The category mapping workspace relies on CoreAdminUi's host connection gate.
It no longer mounts the publication-only banner above the workspace. Publication
controls retain their write-readiness checks; the native Magento category form
retains its local notice because it is not an Ergonode workspace.

The immediate admin executor is injected into StrictCategoryCreator as well as
the general synchronizer. Strict creation therefore returns rate limits after
one attempt for the browser to handle, instead of sleeping and retrying inside
the PHP request. Tree cycle validation shares checked ancestors across nodes;
remote membership validation collects IDs in one set without merging growing
subtree arrays.

## Granica panelu i walidacja publikacji

PublisherAdminUi korzysta z CategoryAdminUi oraz neutralnych kontraktów Category.
Nie wymaga CategoryConsumer ani CategoryConsumerAdminUi. Przed zdalnym zapisem pełny
szkic jest walidowany przez CategoryLayoutValidatorInterface; każda paczka i zapis
layoutu ponawiają walidację. Wspólna blokada serializuje publikację z lokalnym zapisem
oraz synchronizacją. Receipt pełnego layoutu jest czyszczony dopiero po udanym zapisie
lokalnym. Historyczne identyfikatory ACL mają definicje w Category i zachowują istniejące
uprawnienia bez migracji ról.

Automatyczne ponowienia kończą się po pięciu próbach albo 120 sekundach oczekiwania;
szkic i serwerowe potwierdzenia pozostają do ręcznego wznowienia przez Save. Retry-After
nie jest skracany. PendingCategoryPublisher używa paczek CategoryBatchPublisher, a
receipt layoutu jest niezależny od receipt bieżącej paczki. Odczyt historycznych REST ID
wybiera kolejne strony, jeżeli liczba wyników wskazuje mniejszy koszt niż pojedyncze
wyszukiwania; rzadkie trafienia w dużym katalogu nadal używają wyszukiwania po kodzie.
Wielokrotna inicjalizacja tego samego panelu nie dodaje kolejnych kontrolek ani dialogów.
Publisher rejestruje sprzątanie w `api.cleanup` gospodarza: usuwa dialog drzewa,
listenery i observer oraz wywołuje `destroy()` progress/auth u ich właścicieli.
Usunięcie panelu kończy oczekiwanie na retry/wznowienie. Odpowiedź trwającego
żądania nie uruchamia następnej paczki ani finalizacji po usunięciu panelu.

## Stabilny kontekst publikacji formularza

Publikacja z formularza zapamiętuje kategorię i drzewo w chwili kliknięcia.
Zmiana kategorii/drzewa lub zniszczenie komponentu kończy oczekiwanie na retry;
spóźniona odpowiedź nie aktualizuje nowego formularza ani nie uruchamia kolejnego zapisu.
Rozpoczętego żądania zdalnego nie cofa. Retry obejmuje maksymalnie pięć prób oraz
120 sekund licząc czas operacji i zaplanowane oczekiwania; Retry-After nie jest skracany.
Po zatrzymaniu można ponownie użyć przycisku publikacji, korzystając z checkpointów serwera.

Kody pozostają stringami także wtedy, gdy PHP używa numerycznego klucza tablicy.
Kod rodzica i segment nazwy `"0"` nie są puste. Generatory PHP i JS sprawdzają
wspólne fixtures, a indeksy kodów kolejki nie dziedziczą kluczy prototypu JavaScript.
