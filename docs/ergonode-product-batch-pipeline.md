# Wspólny przebieg synchronizacji produktów

Uzgodniony zakres punktu MED-CR-008 obejmuje pełny import i ręczny import produktów
z panelu. Paczka wykonuje się przez jeden nadrzędny `ProductBatchPipeline`, kolejno
w fazach `preprocess`, `process` i `postprocess`. Nie ma osobnego zlecenia wykonania
mediów dla tej paczki. Galeria zapisuje się przed rolami zdjęć.

## Fazy i rozszerzenia

- `preprocess`: `LoadSources` pobiera aktualny stan źródła dla każdego elementu.
  `PrepareTargets` wykonuje jedno zbiorcze zapytanie o istnienie produktów z paczki
  w Magento, z mapowaniem i opcjonalnym natywnym atrybutem identyfikacji. Przygotowuje
  istniejące cele i brakujące produkty. Nie czyta całego katalogu. Zapisane wartości
  EAV i galerie są odczytywane osobnymi zapytaniami zbiorczymi na potrzeby wykrycia
  rzeczywistej zmiany; nie są czytane ani hashowane bajty plików.
- `process`: `WriteProductData` wykorzystuje istniejące mapowanie, tworzenie
  produktów, zapis atrybutów i SKU. `ProductMediaSynchronizer` zapisuje zamiar
  aktualizacji w kontekście paczki. `WriteProductMedia` wykonuje ten zamiar,
  przygotowuje pliki i zapisuje galerię/role oraz atrybuty plikowe. Przejmuje
  wyłącznie pracę bieżącego produktu, bez przejmowania niezwiązanych mediów.
- `postprocess`: dowolne zarejestrowane rozszerzenia kończą swoje zadania.
  Potem zapisujemy hash zakończonego importu dla udanych produktów, a wspólny
  `ProductCacheFinalizer` odświeża cache zmienionych, zakończonych produktów.
  Przebieg bez zmian nie czyści cache. Nie czyścimy całego FPC ani ogólnego tagu
  `cat_p`; używamy konkretnych `cat_p_ID` oraz standardowego `clean_cache_by_tags`
  obsługiwanego przez Magento FPC/Varnish.

Rozszerzenie implementuje `BatchProcessorInterface::process(BatchContext)`.
Rejestrujemy je przez DI w tablicy `preprocessors`, `processors` lub
`postprocessors`. Klucze porządkują kolejność leksykograficznie, np.
`100_attributes`, `200_media`. Końcowe odświeżenie cache zawsze następuje po
wszystkich zarejestrowanych postprocesorach. Wspólny kontekst zawiera źródła,
rozpoznane produkty, wyniki/błędy oraz `data` dla rozszerzeń. Operacje pojedynczego
produktu wykonujemy przez `BatchContext::run`, aby błąd nie zatrzymał pozostałych
produktów. Nowej obsługi relacji produktowych w tym zakresie nie dodajemy.

## Błędy i kolejny ręczny import

Nieudane media ani produkty nie są automatycznie ponawiane, również po wygaśnięciu
lease. Błąd produktu ma SKU/ID, etap, referencję i wyjątek; wspólna awaria fazy ma
jeden wpis z listą produktów. Nie dodajemy kolejki napraw. Usunięto cron recovery
produktów i opcję CLI `--retry-failed`. Nowa próba wymaga nowego importu obejmującego
produkt, np. pełnego importu albo resetu odpowiedniego kursora.

Błąd procesora blokuje zależne wykonanie mediów tego produktu. Błąd przygotowania
lub zapisu galerii nie dopuszcza aktualizacji jej ról. Dane wcześniej zatwierdzone
mogą pozostać zapisane; nie dokładamy transakcji obejmującej całą paczkę. Hash udanego
importu zapisujemy dopiero po zakończeniu procesorów. Zmiany częściowo zatwierdzonych
produktów mogą wymagać indeksowania nawet wtedy, gdy późniejszy etap zawiódł.

Cache porównujemy wyłącznie na podstawie danych przed i po aktualizacji w bieżącym
przebiegu. Podpisy tych danych pozostają w pamięci kontekstu paczki; nie zapisujemy
osobnego stanu cache w bazie. Niepowodzenie czyszczenia (wynik `false` lub wyjątek)
trafia do jednego logu z ID/SKU produktów, etapem i przyczyną. Nie oznacza nieudanego
zapisu produktu i nie zleca pracy na kolejny import. Jeśli późniejszy przebieg nie
zmieni danych, nie podejmie ponownie wcześniejszego czyszczenia; cache można wtedy
wyczyścić ręcznie na podstawie logów. Hash zakończonego importu nadal służy do
pomijania niezmienionych danych źródłowych. Hashów źródłowych plików nie zmieniamy;
ręczna weryfikacja integralności nadal tylko raportuje problemy.

Tabela `ergonode_product_cache_state` nie jest już deklarowana ani używana przez
kod synchronizacji. Historyczny wpis w `db_schema_whitelist.json` pozostaje wyłącznie
po to, aby Magento mogło usunąć tę tabelę podczas aktualizacji schematu, jeśli została
utworzona przez wcześniejszą wersję. Uninstall również obsługuje sprzątanie starej tabeli.

## Jedno aktywne wykonanie

Wspólna blokada `ergonode_product_synchronization` obejmuje pracownika produktów,
pracownika samodzielnych aktualizacji mediów i ręczny przebieg w panelu. Worker
czeka na blokadę przed przejęciem pracy. Panel przy zajętej blokadzie zgłasza, że
synchronizacja działa w tle. Oczekiwanie przed pierwszą próbą nie ponawia nieudanej
operacji. Osobna kolejka mediów pozostaje dla niezależnych zmian multimediaStream;
korzysta z tej samej blokady i wspólnego końcowego mechanizmu cache.

## Weryfikacja i wdrożenie

Testy jednostkowe sprawdzają kolejność faz, kontekst, izolację błędów, brak zlecenia
mediów do innego workera podczas przebiegu produktów, dokładne tagi produktów,
przebieg bez zmian, logowanie błędów cache i brak pamiętania ich na kolejny import. Odczyt istnienia
produktów jest sprawdzany jako jedno zapytanie dla paczki.

Przed uruchomieniem na rzeczywistych danych potrzebna jest standardowa aktualizacja
schematu Magento dla wcześniejszych zmian mediów i usunięcia dawnej tabeli stanu cache,
jeśli istnieje, odświeżenie
konfiguracji DI oraz restart workerów. W ramach implementacji nie uruchamiamy
`setup:upgrade`, rzeczywistego importu, skanu ani czyszczenia cache sklepu. Testy
jednostkowe i statyczna walidacja nie potwierdzają efektu na rozgrzanym FPC/Varnish;
ten efekt wymaga oddzielnego, kontrolowanego testu środowiskowego.
