# Testy konfiguracji połączenia Ergonode

Scenariusze są widoczne w **PackHauer → Playwright**, z właścicielem
`Ergonode_CoreAdminUi`. Opisy i kroki raportów są po polsku. Runner uruchamia
jeden wybrany test; każdy działa samodzielnie i nie wymaga poprzedniego scenariusza.
Dotychczasowy `ERG-CORE-002`, sprawdzający usuniętą sekcję Endpoint, został
zastąpiony przez poniższy zestaw.

## Wspólna konfiguracja projektu

Jedno źródło danych dla obecnych i kolejnych testów znajduje się w katalogu
**app/etc backendu**, w `app/etc/playwright.yaml`. Nie jest to plik modułu ani
konfiguracja produkcyjna Magento. Git ignoruje ten plik. Wersjonowany
`app/etc/playwright.example.yaml` pokazuje strukturę bez prawdziwych kluczy.

- `magento.baseUrl`, `adminPath`: adres lokalnego panelu Magento;
- `magento.accounts`: nazwane konta, np. `admin` i `operator`, każde z `username` i `password`;
- `magento.defaultAccount`: nazwa konta domyślnego, obecnie `admin`;
- `magento.database`: lokalna baza i opcjonalny `tablePrefix`, potrzebne do
  zachowania konfiguracji sprzed testu i jej dokładnego odtworzenia;
- `ergonode.url`, `readApiKey`: staging Ergonode i klucz wyłącznie do odczytu.

Plik ma uprawnienia `0600`. Nie kopiuj go do repozytorium, raportu ani obrazu
produkcyjnego. Rzeczywisty klucz nie występuje w tytułach kroków, asercjach ani
przykładach. Wypełnienie pola hasłowego nie dodaje klucza do raportu Playwright.

Współdzielony loader `@vendivo/test-config` jest instalowany z
`dev/tests/playwright/project-config`. Domyślnie szuka pliku względem
`MAGENTO_ROOT` lub katalogu uruchomienia. `PROJECT_TEST_CONFIG` pozwala wskazać
inny plik YAML: absolutny lub względny wobec rootu Magento, np. w CI. Testy wymagają lokalnego adresu Magento
(`*.ddev.site`, `localhost` lub `127.0.0.1`).

Konta są wspólne dla całego projektu. Lokalnie zachowano istniejące dane konta
`admin` pod `magento.accounts.admin`; dodanie wpisu do pliku nie tworzy konta
w Magento ani nie nadaje uprawnień. Następne istniejące konto dodaj jako kolejny
wpis w `accounts` i ustaw `defaultAccount`, jeśli ma być używane domyślnie.

Runner może wybrać inne konto przez `MAGENTO_TEST_ACCOUNT=operator` w środowisku
**procesu runnera**. Ustawienie tej zmiennej tylko przy adapterze PHP nie przekaże
jej do już działającego runnera. Zmiana `defaultAccount` w pliku jest odczytywana
przy następnym uruchomieniu testu, także z panelu PackHauer. Test może też jawnie
pobrać konto przez `magentoAccount(config, "operator")`. Nieznana nazwa albo
niekompletne dane przerywają test; nie ma zastępowania konta administratorem.

To dane wejściowe testów. Nie nadpisują automatycznie konfiguracji sklepu.
Fixture zapisuje profil tylko na czas danego scenariusza i przywraca wcześniejszy
stan, więc po testach sklep nie musi pozostać skonfigurowany do Ergonode.

## Przygotowanie środowiska bez resetu bazy

1. Korzystaj z działającego lokalnego Magento i runnera `PackHauer_Playwright`.
2. Zainstaluj zależności projektu: `ddev exec --raw -- npm ci`.
3. Utwórz `app/etc/playwright.yaml` na podstawie przykładu i ogranicz dostęp:
   `chmod 600 app/etc/playwright.yaml`.
4. Konto administracyjne musi mieć `Ergonode_Core::main` oraz
   `Ergonode_Core::config`. Testy nie tworzą kont i nie rozszerzają uprawnień.
5. Zatrzymaj automatyczne zadania Ergonode na czas testów zapisu i połączenia
   z zapisanym profilem. Włączenie prawidłowego profilu odczytu może pozwolić
   cronowi rozpocząć import. Scenariusze same nie uruchamiają synchronizacji.
   Nie edytuj równocześnie sześciu ścieżek konfiguracji używanych przez fixture.
6. Nie uruchamiaj `db-reinstall`, instalacji sample data ani resetu bazy.

Nowy runner ma osobny interfejs od starszego `npm run playwright:test`.
Wybierz test w panelu PackHauer lub użyj adaptera do tego samego API:

```bash
ddev exec --raw -- php dev/tests/playwright/packhauer.php catalog ERG-CONN-
ddev exec --raw -- php dev/tests/playwright/packhauer.php run ERG-CONN-
ddev exec --raw -- php dev/tests/playwright/packhauer.php run ERG-CONN-007
```

Polecenie `run` wykonuje dopasowane scenariusze kolejno i zatrzymuje się przy
pierwszym niepowodzeniu. `start ID` uruchamia asynchronicznie, `get UUID` zwraca
pełny raport, a `recent` pokazuje historię. Raporty są również w panelu PackHauer.

## Co dokładnie sprawdzamy

| ID | Kroki | Oczekiwany wynik |
| --- | --- | --- |
| ERG-CONN-001 | Fixture przygotowuje Disable/Test/Read only. Test sprawdza ukrycie pól, włącza formularz, wybiera Production, wyłącza i ponownie włącza. | Pozostaje widoczny tylko Status przy Disable; włączenie przywraca wybrane Production. Wybór nie jest zapisywany. |
| ERG-CONN-002 | W trybie odczytu wpisuje tymczasowy URL i przechodzi Test → Production → Test. | Tylko aktywny profil pokazuje URL, limit, klucz i przycisk; tymczasowy URL pozostaje po powrocie. |
| ERG-CONN-003 | W obu środowiskach przechodzi Read only → Read and write → Read only. | Widoczny jest właściwy klucz i przycisk. REST jest widoczny wyłącznie w trybie zapisu. Nie loguje się do REST i nie zapisuje trybu. |
| ERG-CONN-004 | Próbuje zapisu bez URL i klucza, z błędnym URL, z limitem -1 i 1.5. | Komunikat przy każdym błędnym polu; brak żądania POST zapisującego konfigurację. |
| ERG-CONN-005 | Przez formularz wybiera Test/Read only, wpisuje URL i klucz z pliku projektu, ustawia limit 0, zapisuje i odświeża. | Ustawienia pozostają; pole klucza zawiera maskę. Po teście wraca poprzednia konfiguracja. |
| ERG-CONN-006 | Wpisuje dane stagingu w formularzu Test/Read only, bez zapisania, i klika Test connection. | Prawdziwy backend otrzymuje test/read, wykonuje GraphQL, zwraca sukces; komunikat potwierdza połączenie i przycisk znów jest aktywny. |
| ERG-CONN-007 | Fixture samodzielnie zapisuje konfigurację przez Magento; test klika przycisk z kluczem zamaskowanym po odświeżeniu. | Przeglądarka wysyła maskę; backend odczytuje i odszyfrowuje zapisany klucz właściwego profilu, a połączenie kończy się sukcesem. |

Testy 006 i 007 nie podstawiają odpowiedzi: sprawdzają rzeczywiste połączenie
Magento → staging Ergonode. Zapytanie `__typename` potwierdza łączność, ale nie
potwierdza importu, publikacji ani uprawnień zapisu. Test 005 zapisuje prawdziwy
klucz lokalnie, ale nie wysyła zapytania do Ergonode.

## Fixture i kolejne scenariusze

`support/connection.ts` korzysta z fixture `@packhauer/playwright-runner/fixtures`.
Przed scenariuszem kopiuje z `core_config_data` tylko sześć ścieżek w zakresie
default/0:

- `ergonode_connection/general/enabled`, `environment`, `mode`;
- `ergonode_connection/test/url`, `requests_per_minute`, `consumer/api_key`.

Zachowuje identyfikatory wpisów, ich brak oraz dokładne szyfrogramy. Ustawia
izolowany stan Disable/Test/Read only; nie resetuje tabel ani danych sklepu.
Przygotowanie kompletnego profilu używa natywnego zapisu konfiguracji Magento,
więc działa normalna walidacja i szyfrowanie. Nie wpisujemy jawnego klucza przez SQL.

Po teście, także przy nieudanej asercji, fixture odtwarza wpisy transakcyjnie,
odświeża cache przez natywny zapis tej samej wartości Status i sprawdza zgodność
odtworzonych wpisów. Nie zmienia profilu Production ani klucza publikacji.
Blokada bazy zapobiega jednoczesnemu uruchomieniu dwóch takich fixtures.

Odtwarzanie i ręczne odzyskiwanie używają wspólnego `support/connection-snapshot.mjs`.
Przed zapisem odświeżającym konfigurację fixture ponownie sprawdza logowanie i
pobiera aktualne adresy formularza oraz klucz sesji. Ponowne odtworzenie dokładnych
wierszy odbywa się także wtedy, gdy HTTP zapisze Status, ale późniejszy odczyt
formularza zawiedzie. Błąd HTTP albo bazy pozostawia kopię do odzyskania; nie jest
traktowany jako pomyślny cleanup. Weryfikacja porównuje sześć ścieżek, nie całą bazę.

`ERG-CORE-001` korzysta z tej samej bazowej fixture PackHauer, ale tylko sprawdza
nawigację: nie uruchamia fixture zmieniającej konfigurację. Starsze scenariusze
innych modułów zachowują swój adapter z `dev/tests/playwright`.
Oba zestawy wybierają konto przez `loginToErgonodeAdmin` z tej samej konfiguracji
projektu. Test nawigacji dopuszcza dodatkowe zakładki z modułów opcjonalnych,
sprawdza tytuł docelowego ekranu Languages i wraca przez menu administratora.
Wyłączone połączenie może prawidłowo zastąpić panel mapowania komunikatem;
dostępność panelu sprawdzają osobne testy kontraktu ConnectionNotice.

Regresje cleanupu uruchamia `make -f .agents/backend/Makefile playwright-fixture-test`.
Wariant `RUNTIME='ddev exec -s playwright --raw -- env PACKHAUER_TEST_BROWSER=1 PACKHAUER_TEST_DB=1'`
sprawdza błędy asercji/przygotowania, timeout, błąd diagnostyki, odnowienie sesji,
odzyskanie kopii po SIGKILL oraz transakcyjne odtworzenie w tabeli tymczasowej
MariaDB. Nie zmienia konfiguracji sklepu i nie wymaga zatrzymania jego crona.

Kolejny scenariusz wymagający połączenia wywołuje
`connection.prepareReadConnection()` w swoim przygotowaniu. Nie zależy od wyniku
ERG-CONN-005 ani od kolejności testów. Scenariusze w innych modułach mogą używać
tego samego pliku projektu przez `@vendivo/test-config`; ich własne dane i fixture
należą do tych modułów.

Jeżeli proces zostanie zabity zanim wykona sprzątanie, zostaje chroniona kopia
`var/test-state/ergonode-connection.json` w systemie plików runnera. Następny test
zatrzyma się przed zmianą danych. Nie usuwaj tej kopii bez przywrócenia ustawień.
W panelu **PackHauer → Playwright** wybierz **Przywróć dane** przy konfiguracji
Ergonode. `connection.recovery.json` rejestruje procedurę `connection.recovery.ts`.
Używa ona tej samej blokady i `ConnectionForm.recover()`: wczytuje kopię, odtwarza
sześć ścieżek, loguje się ponownie, odświeża cache przez natywny zapis konfiguracji
i porównuje odtworzone wiersze. Nie pobiera profilu stagingowego ani nie przygotowuje
nowej fixture. Błąd zachowuje kopię; operację można ponowić. Uprawnienia konta
wykonującego operację pozostają takie same jak dla testów połączenia.

Zapasowa procedura ręczna pozostaje opisana w `support/recover-connection.mjs`;
służy środowiskom bez dostępnego panelu. Odzyskiwanie nadal wymaga zatrzymanych
zadań automatycznych i braku równoległych zmian konfiguracji. Panel nie zatrzymuje
crona ani już działających synchronizacji.

## Testy integracyjne

`Test/Integration/Controller/Adminhtml/Connection/ConfigurationTest.php` obejmuje
renderowanie i ACL formularza, zapis i szyfrowanie kluczy, zachowanie klucza
innego trybu, brak klucza, wyłączone połączenie, domyślne Disable/Test/Read only
(przy aktywnym Consumer), pusty lub błędny URL, ujemny/ułamkowy limit oraz
zachowanie profilu Test po zapisaniu Production. Dane są sztuczne; prawdziwy klucz
stagingu nie jest potrzebny.

Używa `@magentoDbIsolation` i osobnej bazy `magento_integration_tests`.
Bootstrap może przebudować tę bazę testową; nie resetuje bazy sklepu `db`.

```bash
make -f .agents/backend/Makefile test-integration RUNTIME='ddev exec --raw --' \
  args=app/code/Ergonode/CoreAdminUi/Test/Integration/Controller/Adminhtml/Connection/ConfigurationTest.php
```
