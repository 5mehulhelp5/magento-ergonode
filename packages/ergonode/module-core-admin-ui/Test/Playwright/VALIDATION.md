# Weryfikacja testów konfiguracji — 2026-09-12

## Wyniki zadania

| Test | Wynik | Uruchomienie PackHauer |
| --- | --- | --- |
| ERG-CONN-001 | PASS | `ce854b3b-ceef-49f3-b216-470ea0aedb4a` |
| ERG-CONN-002 | PASS | `ad651139-ac82-4269-989d-b1c580f6006e` |
| ERG-CONN-003 | PASS | `a256eea5-3974-47e7-b63b-e7758c9451e2` |
| ERG-CONN-004 | PASS | `96c60894-14c3-4241-bcb1-a6f87f743c29` |
| ERG-CONN-005 | PASS | `6302f483-0c51-43c9-b3ad-3108e28565d2` |
| ERG-CONN-006 | PASS | `09d56315-4288-4820-85de-ac727189ea7b` |
| ERG-CONN-007 | PASS | `059e38d2-62f1-4aa1-ba3a-a0d8fae11306` |

Wszystkie scenariusze uruchomiono przez istniejący runner PackHauer i jego API Magento. Testy 006 i 007 zakończyły się sukcesem z rzeczywistym stagingiem Ergonode, bez podstawiania odpowiedzi. Każdy test zweryfikował dokładne odtworzenie sześciu ścieżek konfiguracji. Odtworzenie wykonało się także po timeoutcie pierwszej wersji testu 006; poprawiono dopasowanie wielkości liter w adresie `testConnection` i ponowiono ten scenariusz.

**Integracyjne: PASS — 12 testów, 67 asercji.** Osobna baza `magento_integration_tests`; bazy sklepu nie resetowano.

## Pozostałe bramki

- `agent-finish`: wszystkie dziewięć kontroli przed unit przeszło, w tym PHPCS, PHPMD, PHPStan, PHPArkitect, XML i granice modułów.
- Unit modułu: 65 testów, 152 asercje, jeden istniejący błąd kontraktu ACL. `AclResourceConstantTest` wskazuje `app/code/Ergonode/Publisher/Setup/Uninstall.php:34`. Ten sam literal jest obecny w HEAD; pliku nie zmieniono w tym zadaniu.
- `quality-full`: kontrola dokumentacji agentów i testy pakietów przeszły. Bramka zatrzymała się na testach JavaScript; pierwsza awaria to istniejący `CategoryAttributePublisherAdminUi/Test/Js/category-attribute-publisher-contract.test.cjs`, oczekujący brakującego `etc/di.xml`. Nie zmieniano tych plików ani nie wyciszano diagnostyki.
- Składnia adaptera PHP i skryptu odzyskania: PASS. Formatowanie Prettier oraz `git diff --check`: PASS.

Pełna bramka repozytorium nie jest zielona. Powyższe wyniki nie oznaczają gotowości całego projektu do publikacji.

## Polecenia

```bash
make -f .agents/backend/Makefile playwright-catalog RUNTIME='ddev exec --raw --' test=ERG-CONN-
make -f .agents/backend/Makefile playwright-run RUNTIME='ddev exec --raw --' test=ERG-CONN-
make -f .agents/backend/Makefile test-integration RUNTIME='ddev exec --raw --' args=app/code/Ergonode/CoreAdminUi/Test/Integration/Controller/Adminhtml/Connection/ConfigurationTest.php
make -f .agents/backend/Makefile agent-finish RUNTIME='ddev exec --raw --'
make -f .agents/backend/Makefile quality-full RUNTIME='ddev exec --raw --'
```

## Zakres i przekazanie

- Zatwierdzone przez użytkownika: testy, fixture i opisy konfiguracji w `Ergonode_CoreAdminUi`; uruchamianie przez istniejący `PackHauer_Playwright`; wspólna konfiguracja projektu poza modułem.
- Zmiany runtime PHP, schematu bazy i migracje: brak. MAG-SOLID-001..008 dla zmienionych klas runtime: nie dotyczy.
- Kontrakt runtime pozostaje bez zmian. Usunięto przestarzały ERG-CORE-002 odwołujący się do Endpoint, zastępując go aktualnym zestawem. Istniejący ERG-CORE-001 nawigacji pozostaje poza zakresem.
- Nowe zależności modułów Magento: brak. Testowe zależności npm: istniejący lokalny runner PackHauer, lokalny loader konfiguracji projektu i mysql2 do kopii/odtworzenia wpisów konfiguracji.
- Testy obejmują area adminhtml. Wymagane moduły i ACL są zadeklarowane w metadanych; scenariusz zmiany trybu dodatkowo wymaga Publisher i PublisherAdminUi.
- Dane dostępowe są w ignorowanym `app/etc/playwright.yaml`, z uprawnieniami 0600. Do repo trafia wyłącznie przykład. Cron i workery Ergonode były zatrzymane; testy nie uruchamiały importu ani publikacji.
- Procedura odzyskiwania po zabiciu procesu zachowuje kopię do czasu jawnego odświeżenia cache. Sprawdzono składnię skryptu; nie symulowano zabicia procesu podczas transakcji.

## Uzupełnienie: nazwane konta Magento

Wspólna konfiguracja używa teraz `magento.accounts` oraz `magento.defaultAccount`.
Dane istniejącego konta `admin` zachowano lokalnie. Nie tworzono użytkowników ani
nie zmieniano ról w Magento. Fixture pobiera wybrane konto ze wspólnego loadera.

- Testy wyboru konta: **6/6 PASS** (domyślne konto, nadpisanie przez środowisko,
  jawny wybór, brak awaryjnego logowania jako admin, walidacja i brak sekretów w błędach).
- Ponowne E2E logowania i formularza: **ERG-CONN-001 PASS**, uruchomienie
  `5314371b-6bac-4b53-aaaf-79a10bc00be8`, wraz z przywróceniem konfiguracji.
- Kontrola dokumentacji agentów i `git diff --check`: PASS.
- Plik lokalny nadal ma uprawnienia `0600` i pozostaje ignorowany przez Git.

```bash
make -f .agents/backend/Makefile playwright-config-test RUNTIME='ddev exec --raw --'
```
