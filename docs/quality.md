# Bramki jakości

Narzędzia przeniesiono z `vendivo-1/backend` i dostosowano do lokalnych
pakietów Magento. Zmiany dotyczą infrastruktury jakości; włączenie bramek
nie oznacza, że wszystkie istniejące moduły już je spełniają.

## Instalacja i uruchamianie

```bash
ddev start
ddev composer install
ddev exec npm ci --ignore-scripts
./quality modules
./quality all
```

`./quality` uruchamia narzędzia przez DDEV bieżącego projektu. Skrypty i
konfiguracje są w `tools/quality`, a przenośne pakiety w `tools/packages`.
`dev/` pozostaje odtwarzanym przez Composer katalogiem Magento.
W kontenerze lub przygotowanym CI można używać `php tools/quality/run.php`.

`./quality modules` wykrywa moduły z `packages/*/*/etc/module.xml`.
Brak modułów, nieznany moduł i błędy konfiguracji kończą się błędem.
Nie skanujemy kopii tych samych pakietów przez symlinki w `vendor`.

## Codzienna praca

```bash
./quality module-check Ergonode_Media --base-commit=HEAD
./quality module-static Ergonode_Media --scope=full
./quality module-done Ergonode_Media --base-commit=HEAD
# Przy zmianach bazy/bootstrapu/DI, po przygotowaniu osobnej bazy testowej:
./quality module-done Ergonode_Media --integration --base-commit=HEAD
```

`--base-commit` powinien wskazywać commit sprzed ocenianych zmian. Dla zmian
committed podaj odpowiednią bazę gałęzi, nie bieżący HEAD. Manifest obejmuje
różnicę od bazy, staged, unstaged i untracked. Brak wiarygodnej bazy powoduje
analizę całego modułu. Manifest powstaje na hoście i jest synchronizowany do
DDEV przed analizą, więc nie zależy od dostępności `.git` w kontenerze.

- `module-check`: szybkie sprawdzenie; PHPCS i PHPMD obejmują zmienione pliki,
  pozostałe kontrole cały moduł. Zatrzymuje się na pierwszej nieudanej bramce.
- `module-static`: PHPCS, PHPMD, PHPStan i PHPArkitect; `--scope=full` zbiera
  wszystkie wyniki, `--scope=changed` daje szybki feedback.
- `module-done`: pełny moduł, metadane Composer, XML, granice, analizy, unit
  i istniejące testy JS. Integracja jest włączana jawnie.
- `all`: wszystkie pakiety, pełne analizy, unit i JS. Raporty każdej bramki
  są w `var/quality/full`. Nie uruchamia integracji ani audytów opcjonalnych.

Raporty modułowe są w `var/quality/<Vendor_Module>`: pełne logi i skróty JSON
nieudanych bramek. Każda porażka daje niezerowy kod wyjścia. `SKIP no-tests`
nie jest dowodem pokrycia modułu testami. Nie uruchamiaj dwóch analiz tego
samego modułu równocześnie, ponieważ zapisują te same raporty.

## Przyjęte kontrole i uzasadnienie

| Kontrola | Zakres i powód |
| --- | --- |
| PHPStan 2, level 2 | Cały kod produkcyjny pakietów; obsługa Magento, fabryk i magicznych metod. Poziom startowy, do stopniowego podnoszenia. Testy mają osobną bramkę wykonania. |
| PHPCS | Oficjalny Magento Coding Standard oraz wybrane reguły Vendivo: ACL Admin, ObjectManager, strict types, dokumentacja kontraktów API, fabryki i pluginy. PHP/PHTML; XML ma własne bramki. |
| PHPMD | Wąski zestaw Vendivo: przypisania w warunkach, warunki bez logiki, pozostałości debugowania i exit. |
| XML/XSD i format XML | Wykrywanie błędnej konfiguracji Magento i spójne formatowanie; zwykłe bramki nie formatują plików. |
| Composer | Metadane, autoload i bezpośrednie zależności wynikające z użycia klas w PHP/PHTML/XML/GraphQL. |
| Granice modułów | Moduły bazowe bez zależności od AdminUi i innych warstw prezentacji, modele bez zależności od kontrolerów/Block/Ui, interfejsy API i konfiguracja obszarów Magento. |
| Unit i JS | Istniejące testy pakietów, bez zależności od lokalnego wygenerowanego kodu jako warunku ukrywającego brak fabryk. |

### Jawne Factory

Odwołania do fabryk Ergonode i PackHauer wymagają rzeczywistego pliku klasy.
Reguła rozwiązuje ścieżki z `composer.json` pakietu i nie akceptuje pliku
istniejącego jedynie w `generated`. Uzasadnienie: PhpStorm widzi klasę także
po czyszczeniu `generated`, co ułatwia nawigację, typowanie i refaktoryzację.

### Pluginy before / after / around

Preferujemy `before` dla argumentów/czynności przed wywołaniem i `after`
dla wyniku/czynności po wywołaniu. `around` wymaga uzasadnienia koniecznością
objęcia oryginalnego wywołania sterowaniem, np. `try/finally`.

PHPCS wymaga parametru `$proceed` i jego wywołania oraz odrzuca proste
wrappery możliwe do zastąpienia `before`/`after` i pusty wrapper przekazujący
wywołanie. Uzasadnienie: ograniczenie niepotrzebnego łańcucha pluginów around.
Kontrola syntaktyczna nie dowodzi wywołania proceed na każdej ścieżce ani
nie klasyfikuje wszystkich złożonych wrapperów. Review musi ocenić pozostałe
przypadki. Celowe przerwanie łańcucha wymaga uzasadnienia, testu i jawnie
uzgodnionego wyjątku; nie dodajemy automatycznych wyciszeń.

### Zależności Composer

Moduł używający API innego pakietu deklaruje go bezpośrednio w `require`,
również gdy pakiet jest obecnie osiągalny przez inną zależność. Zmiana
zależności pośrednika nie może przypadkiem usuwać wymagania konsumenta.
Przeniesiony walidator i generator nie usuwają takich wpisów jako zbędnych.

Nie uznajemy braku statycznego odwołania za dowód zbędności wymagania:
pakiet może dostarczać konfigurację, zasoby lub rejestrację. Usuwanie
zależności wymaga przeglądu tych zastosowań. `module.xml/sequence` określa
kolejność ładowania i nie zastępuje deklaracji Composer.

### Schemat bazy

Po zmianie `db_schema.xml`, `module-done` sprawdza whitelistę. Adapter używa
trybu `check`, który porównuje wygenerowany wynik i odtwarza plik pierwotny
(również jego pierwotny brak). Nie uruchamia `setup:upgrade`. Generowanie
whitelisty jako trwała zmiana pozostaje osobną czynnością autora zmiany.

## Audyty opcjonalne

```bash
./quality dead-code
./quality duplicates packages/ergonode/module-language
./quality infection packages/ergonode/module-language/Model
```

- **Dead-code:** okresowy audyt lub usuwanie funkcjonalności. Preferuj cały
  graf projektu; ograniczenie do modułu może pominąć konsumentów. Wyniki
  wymagają oceny użyć dynamicznych Magento; nie oznaczają zgody na usunięcie.
- **Duplikaty:** audyt/refaktor podobnej logiki. Raport nie blokuje zwykłych
  zmian. Podobieństwo nie zawsze uzasadnia wspólną abstrakcję.
- **Infection:** jawnie wskazany katalog istotnej logiki i aktywny Xdebug lub
  PCOV. Dla Xdebug uruchom wcześniej `ddev xdebug on`; wrapper ustawia
  `XDEBUG_MODE=coverage` tylko dla audytu. Raport pokazuje jakość
  testów; nie narzucamy arbitralnego progu MSI na cały projekt. Runner blokuje
  równoległe uruchomienia. Pełny audit nie jest częścią `all` ani `module-done`.

Nie przeniesiono własnej kopii Magento Coding Standard z Vendivo ani testów
jej zgodności z v40, infrastruktury agentów i Storybook/E2E. Sztywne limity
rozmiaru metod i liczby zależności nie są obowiązkowymi bramkami.

## Integracja z bazą

Skopiuj `tools/quality/integration/install-config.php.dist` do
`tools/quality/integration/install-config.php` (plik ignorowany przez Git).
Przygotuj osobną, pustą bazę i konto z prawami ograniczonymi do tej bazy.
Ustaw własne hasło. Nazwa bazy musi kończyć się `_test`, różnić się od bazy
aplikacji i odpowiadać osobnemu prefiksowi indeksów OpenSearch.

```bash
./quality integration packages/ergonode/module-language
```

Magento instaluje i czyści środowisko testowe. Nie wskazuj bazy aplikacji.
Runner sprawdza konfigurację przed bootstrapem, nie tworzy sam bazy,
blokuje równoległe uruchomienia i korzysta z frameworka integracyjnego Magento
zainstalowanego przez Composer. Brak konfiguracji daje błąd, nie zielony SKIP.

## Testy infrastruktury

```bash
./quality tooling-tests
```

Obejmują oryginalne testy runnera manifestów, walidatora Composer i duplikatów
oraz regresje wykrywania pakietów, jawnych Factory i reguł around. Nowe reguły
powinny mieć test przypadku poprawnego i błędnego.

## Weryfikacja przeniesienia — 2026-10-02

Źródło narzędzi: `vendivo-1/backend`, commit
`1f80c9fda9fcd78e7ea3f643457f1a0d3a0d033b`. Kopie dostosowano wyłącznie w
`magento-ergonode`. Testowano w DDEV, PHP 8.5.5.

- Wykrywanie: 68 pakietów; PHPArkitect przeanalizował 1325 klas.
- Testy narzędzi: 36 testów PHPUnit oraz testy shellowe runnera przechodzą.
- Pełny przebieg: PHPStan, PHPMD, PHPArkitect, granice obszarów, format XML
  i XSD przechodzą.
- Composer: 286 zgłoszeń brakujących bezpośrednich zależności w istniejących
  modułach. Obecne manifesty odzwierciedlają wcześniejszą politykę Vendivo.
- PHPCS: 120 błędów i 7321 ostrzeżeń w 1212 plikach; m.in. brakujące jawne
  fabryki oraz wymagania standardu Magento. Nie dodano baseline.
- Unit: 2313 testów, 77930 assertions, 3 błędy brakujących klas Factory
  w testach CategoryAttributeHistory i ProductAttributeHistory.
- JS: 555 testów, 506 poprawnych, 48 nieudanych, 1 pominięty. Część testów
  wymaga katalogu Storybook lub ścieżek poprzedniego repozytorium.
- Opcjonalne audyty uruchomiono na Language: detektor duplikatów działa;
  dead-code raportuje kandydata, który wymaga oceny pełnego grafu konsumentów.
- Integracja: sprawdzono odmowę uruchomienia bez osobnej konfiguracji bazy;
  pełnego przebiegu integracyjnego nie wykonano.
- Infection: sprawdzono odmowę uruchomienia bez sterownika coverage;
  pełnego przebiegu mutacyjnego nie wykonano.

Wyniki to punkt startowy dla dostosowania istniejących modułów, nie zielona
akceptacja całego projektu. Migracja narzędzi nie poprawia automatycznie kodu
produkcyjnego, manifestów pakietów ani historycznych testów.
