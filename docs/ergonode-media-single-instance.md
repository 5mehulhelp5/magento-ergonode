# Import mediów Ergonode w jednej instancji Magento

Projekt używa jednej bazy i jednego lokalnego magazynu mediów. Tryb `shared`
oznacza wspólny plik dla wielu produktów tej instalacji.

## Moduły i dane

- `Ergonode_Media`: transfery, źródła, rewizje, indeks SHA-256, skan i kolejka.
- `Ergonode_ProductMedia`: kolejność galerii, role i zapis natywnych powiązań Magento.
- `Ergonode_ProductMediaConsumer`: przekazanie galerii i użyć atrybutów z importu produktu.
- `Ergonode_MediaAdminUi`: wybór galerii, reguły i panel skanu.

Pakiety znajdują się w `packages/ergonode` i są instalowane przez repozytorium
Composer typu `path`. Nie wymagają modułów naprawy ani skanera PackHauer.

`ergonode_media_asset` przechowuje tożsamość `Multimedia.path`, rewizję, status,
SHA-256 i cache źródła. `ergonode_media_local_file` indeksuje lokalne oryginały.
`ergonode_media_materialization` wskazuje plik lokalny dla zasobu i zakresu.
`ergonode_media_product_usage` zapisuje użycie przez produkt. Pozostałe tabele
obsługują atrybuty plikowe, kolejkę, stan trybu i skan.

## Ponowne użycie

1. Odczytaj aktualny stan zasobu z bazy. W obrębie partii użyj wcześniej ustalonej
   ścieżki, jeśli zgadzają się ID, rewizja, SHA-256, status `active` i zakres.
2. Przy pierwszym użyciu w partii sprawdź zapisane powiązanie i istnienie pliku.
   Aktualny plik nie wymaga pobrania ani ponownego hashowania, również po usunięciu
   tymczasowego cache źródła.
3. Nowy, zmieniony lub niekompletny zasób przygotuj i oblicz SHA-256.
4. Dla trybu `shared` użyj pliku o zawartości już zweryfikowanej w tej partii lub
   wyszukaj go w indeksie. Nowe powiązanie z indeksu weryfikuje rzeczywistą zawartość.
5. Zapisz nowy plik tylko wtedy, gdy brak zgodnej zawartości. Zarejestruj go w indeksie
   i zapisz powiązanie. Nazwa nowego wspólnego pliku zawiera pełny SHA-256.
6. Podepnij wspólny wpis galerii do produktu i zapisz role `image`, `small_image`
   oraz `thumbnail`. Kolejny produkt wykorzystuje ten sam plik.

`MaterializationCache` jest aktywna tylko podczas jednej partii pracy konsumenta.
Mapa ID/rewizja/SHA-256/zakres eliminuje powtarzane kontrole tego samego zasobu.
Druga mapa przechowuje zawartość rzeczywiście zweryfikowaną w danej partii i
ogranicza powtarzane hashowanie przy kolejnych tożsamościach źródłowych.
Samo `fileExists` nie dodaje pliku do mapy zweryfikowanej zawartości.

Obie mapy są czyszczone w `finally`, także po błędzie. Nowa partia ponownie
sprawdza plik; usunięty plik może zostać odtworzony z cache źródła. Aktualny stan
zasobu jest nadal odczytywany z bazy przy każdym użyciu, więc zmiana rewizji
nie korzysta ze starej ścieżki. Bezpośrednie wywołanie poza partią zachowuje kontrole.
Podczas partii wspólne pliki powinny być niezmienne.

Nieznane ścieżki źródłowe wymagają pierwszego pobrania, aby porównać ich zawartość.
Identyczne bajty pod dwiema ścieżkami oznaczają dwa pierwsze pobrania i jeden plik
w magazynie mediów. Cache w `var/ergonode/media` jest osobny dla każdej tożsamości
źródła; wspólne są pliki docelowe pod `pub/media`.

## Konfiguracja i skan

Lokalnie ustawiono `gallery`, tryb `shared` i `pl_PL` dla zakresów 0 oraz 1.
Klucze i ustawienia połączenia pozostają w lokalnej konfiguracji, poza Git.
Po odtworzeniu bazy skonfiguruj je ponownie w panelu Ergonode.

Przed pierwszym importem wykonaj `bin/magento ergonode:media:scan`.
Konsument w trybie `shared` czeka na pierwszy pełny skan. Skan indeksuje oryginały
z `catalog/product`, pomija cache, pliki tymczasowe, archiwa i symlinki.
Nie zmienia powiązań produktów ani nie usuwa obrazów. Kolejne skany wykorzystują
zapisany hash, jeśli rozmiar i czas modyfikacji nie zmieniły się.
Pliki dodane przez inne narzędzia wymagają odświeżenia indeksu.

Zmiany źródłowych mediów są odczytywane z `multimediaStream`. Nowy cursor oznacza
zasób jako `dirty`, zwiększa rewizję i zleca odświeżenie używających go produktów.
Zmiana `Multimedia.path` tworzy nową tożsamość i wymaga ponownej synchronizacji
referencji produktów. Aktualność nie jest sprawdzana cyklicznym pobieraniem binariów.

## Weryfikacja

Testy jednostkowe obejmują reuse istniejących plików, brak cache `var`, brak pliku,
zmianę rewizji, izolację zakresów, reset map po błędzie, skan, konfigurację i most
importu produktu. Polecenia są w głównym README.

`tests/media-import-smoke.php` uruchamia lokalny serwer HTTP bez kluczy i rzeczywisty
kod repozytorium, przygotowania źródła, indeksu, galerii, ról oraz konsumenta Magento.
Sprawdza trzy produkty, sto dodatkowych rozpoznań, identyczne bajty pod różnymi
ścieżkami źródłowymi, podmianę zasobu, usunięcie cache, odzyskanie brakującego pliku
oraz ponowne użycie istniejącego pliku z indeksu. Wymaga pustej tabeli pracy mediów,
trybu `shared` i ukończonego skanu. Dane testowe są wycofywane, a pliki usuwane.

Odczyt z testowego Ergonode potwierdził dostępność listy Gallery, `multimediaStream`,
metadanych pojedynczego zasobu oraz pobranie prawidłowego zdjęcia. Test podmiany
w smoke teście generuje zdarzenie strumienia lokalnie. Nie potwierdza, że konkretna
wersja Ergonode emituje takie zdarzenie po `multimediaReplace`; ten kontrakt wymaga
osobnego testu z podmianą dedykowanego zasobu w Ergonode.
