# Kontrakt uzgadniania kategorii

Ten dokument opisuje aktualny kontrakt implementacji. Zastępuje historyczny plan,
który przewidywał automatyczne usuwanie i inne zasady zapisywania kursorów.

## Odpowiedzialności

- `Ergonode_Category`: konfiguracje drzew, lokalne snapshoty struktury i tożsamości,
  transport pełnego drzewa, wspólna składnia zapytań GraphQL oraz zakres ponownego
  użycia pobranych danych. Nie decyduje o modyfikacjach katalogu Magento.
- `Ergonode_CategoryConsumer`: rozstrzyganie tożsamości, wykluczenia, tworzenie,
  przenoszenie i kolejność kategorii, nazwy, mapowania, procesy, kursory i blokada.
- `Ergonode_CategoryAttributeConsumer`: przygotowanie metadanych atrybutów,
  paginacja ich wartości, snapshot encji i zapis zmapowanych wartości EAV.
- `Ergonode_CategoryConsumerHistory`: przechwytywanie stanów pod blokadą,
  zapis różnic, odtwarzanie oraz retencja historii.
- Moduły `*AdminUi`: prezentacja i adaptery operacji. Nie są właścicielami
  reguł dopasowania, zapisu katalogu ani obliczania historii.

## Preview i apply

`CategoryReconciliationServiceInterface::execute` jest wspólnym wejściem.
Preview używa ostatniego kompletnego lokalnego snapshotu. Apply najpierw pobiera
całe źródłowe drzewo. Niepełna odpowiedź nie zastępuje kompletnego snapshotu.

Kolejność tożsamości: zapisane mapowanie, poprawny szkic mapowania, jednoznaczna
znormalizowana nazwa wśród rodzeństwa oczekiwanego rodzica. Niejednoznaczności
pozostają konfliktami. Porównanie nazw zachowuje diakrytyki i interpunkcję.

Wykluczenie źródła, celu Magento lub chronionego przodka blokuje zapis kategorii.
Zapisana tożsamość pozostaje zachowana; nie tworzymy kategorii zastępczej.
Preview i apply korzystają z tego samego resolvera. `Odmapuj` jest odrębną,
jawną operacją użytkownika.

Apply tworzy brakujące kategorie i uzgadnia położenie oraz kolejność przez
mechanizmy Magento. Indeks rodzeństwa zapewnia stały koszt odczytu poprzednika
po posortowaniu grup; po ruchu aktualizowane są grupy starego i nowego rodzica.
Pozycje równe rozstrzyga ID kategorii. Pełne rozstrzyganie powtarza się po
utworzeniu kategorii, jeżeli pozostają zależne, nierozwiązane przypisania.

Synchronizacja **nie usuwa automatycznie kategorii Magento**, również przy
historycznym `remove_missing=1`. `delete_candidates` jest informacją dla
konsumentów wyniku, a `deleted` synchronizacji wynosi zero. Fizyczne usuwanie
wymaga odrębnej, jawnej operacji. Brak całego drzewa w Ergonode nie wyłącza
lokalnej konfiguracji i nie usuwa mapowań.

## Zapytania i pamięć

Dwa procesy mają niezależne, globalne kursory: `category_tree_stream` oraz
`category_stream`. Nie ma konsumenta deleted stream ani kursora per lokalny root.
Bez aktywnych konfiguracji proces nie odczytuje ani nie resetuje streamu.

Kody zdarzeń są deduplikowane podczas dopisywania stron. Proces danych pobiera
mapowania oraz encje w paczkach maksymalnie 50 kodów. `loadMany` korzysta z aliasów
GraphQL; bazowy consumer pobiera nazwy. Rozszerzenie atrybutowe kontynuuje tylko
niedokończone kategorie i używa osobnego kursora dla każdej z nich. `load(code)`
deleguje do tego samego mechanizmu dla rzeczywistych operacji pojedynczej kategorii.
Consumer zawsze korzysta z poświadczeń odczytu; wspólny builder nie wybiera konta.

Pełne drzewo pobierane jest raz dla kolejnych konfiguracji tego samego `tree_code`
w jednym przebiegu. Konfiguracje są uporządkowane według kodu. Snapshot jest
zapisywany osobno dla każdego lokalnego drzewa. Zakres przechowuje tylko jedno
kompletne drzewo, oddziela odczyt od zapisu i jest czyszczony także przy wyjątku.
Sprawdzenie dostępności jest deduplikowane w obrębie przebiegu.

Pamięć nie jest stała: pozostają unikalne kody streamu, stany potrzebnych drzew
oraz bieżąca paczka wartości. Pełne drzewo nadal musi zmieścić się w pamięci.
Frontend cache jest czyszczony zbiorczo dla paczki danych albo uzgadnianego drzewa;
cache lokalnego providera jest unieważniany od razu. No-op nie czyści frontendu.

## Błędy, kursory i transakcje

Kursor jest zapisywany po ukończeniu procesu bez błędu i bez konfliktów.
Zdarzenia niezwiązane z aktywnymi mapowaniami również mogą przesunąć kursor.
W zarządzanym przebiegu Admin importery zwracają oczekujące kursory, a koordynator
zatwierdza je po zakończeniu żądanych etapów. Pauza lub błąd ich nie zatwierdza.
Jawny reset najpierw usuwa stary kursor; przerwany reset powtarza pełny import.

Import nie jest jedną globalną transakcją. Ukończone paczki pozostają zapisane,
gdy następna zawiedzie. Niekompletna paczka pobierania nie trafia do synchronizera.
Błąd podczas zapisu może pozostawić część zmian bieżącej paczki; unieważnienie
cache obejmuje próbowane cele. Ponowienie przetwarza te same zdarzenia i porównuje
wartości, dzięki czemu wcześniej ukończone zapisy nie wymagają duplikowania.

Ręczny zapis układu przygotowuje potrzebne encje przed transakcją. Układ,
widoczność, nazwy, EAV i snapshoty encji zapisują się na wspólnym lokalnym
połączeniu; błąd wycofuje tę transakcję. Frontend cache jest czyszczony po jej
zakończeniu. Przygotowanie rejestru metadanych jest odrębnym etapem, którego ten
rollback nie obejmuje.

## Historia

Capture przed operacją, mutacja, capture po operacji i zapis historii korzystają
z tej samej blokady synchronizacji. Odtwarzanie stanu i retencja również ją respektują.

Przebieg strukturalny tworzy jedną operację historii. Plugin uzgadniania przechwytuje
po jednym dotkniętym drzewie, przed odświeżeniem snapshotu, i zapisuje jego różnice
przed przejściem dalej. Zagnieżdżone odświeżenie nie tworzy osobnego wpisu.
Przebieg bez dotkniętych drzew nie odczytuje ich stanów. Operacja ma status
`running` do zakończenia; wyjątek kończy ją statusem `failed`.

Odtwarzanie czyta późniejsze delty stronami po 500 rekordów, malejąco po ID
operacji i zmiany, bez OFFSET. Wybrany stan i jego lista zmian nadal są materializowane
na potrzeby odpowiedzi. Retencja usuwa tylko najstarszy kompletny prefiks.
Nagłe zakończenie procesu może pozostawić nieukończony nagłówek chroniący retencję;
nie jest on automatycznie przedstawiany jako sukces.

## Weryfikacja

Aktualne scenariusze akceptacyjne i komendy znajdują się w [TESTING.md](TESTING.md).
Testy jednostkowe sprawdzają również granice paczek, niezależne kursory atrybutów,
wykluczoną zapisaną tożsamość, reużycie danych tylko w przebiegu i cleanup przy błędzie.
Testy integracyjne weryfikują rollback wartości i mapowania oraz odtwarzanie
historii przez granice stron i operacji.
