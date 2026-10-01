# Magento Ergonode

Lokalny projekt Magento Open Source 2.4.9 uruchamiany przez DDEV.

| Element | Konfiguracja |
| --- | --- |
| PHP | 8.5 |
| Baza danych | MariaDB 11.8 |
| Wyszukiwarka | OpenSearch 3.1.0 |
| Serwer WWW | nginx + PHP-FPM, document root `pub` |
| Tryb Magento | developer |
| Locale / waluta | pl_PL / PLN |
| Strefa czasowa | Europe/Warsaw |

Sklep: <https://magento-ergonode.ddev.site/>. Panel administracyjny:
<https://magento-ergonode.ddev.site/admin/>. Mailpit:
<https://magento-ergonode.ddev.site:8026/>. OpenSearch Dashboards:
<https://magento-ergonode.ddev.site:5602/>.

Projekt zawiera Magento bez danych przykładowych oraz 62 moduły Ergonode
i dwa wymagane moduły PackHauer, instalowane przez Composer z lokalnego `packages`.
Ustawione `pl_PL` nie oznacza zainstalowania pełnego polskiego pakietu tłumaczeń.

## Wymagania i pierwsza instalacja

Potrzebne są Docker (np. OrbStack), DDEV 1.25.2 lub nowszy i własne klucze
do `repo.magento.com`. Klucze należy skonfigurować w globalnym Composerze
kontenera lub przez DDEV `homeadditions`; pliku `auth.json` nie zapisujemy w Git.
Na komputerze, na którym przygotowano projekt, klucze są już dostępne przez
globalne DDEV `homeadditions`.

```bash
git clone git@github.com:packhauer/magento-ergonode.git
cd magento-ergonode
ddev start
ddev composer install --no-interaction --prefer-dist
cp .env.example .env.local
chmod 600 .env.local
```

Ustaw własne `MAGENTO_ADMIN_PASSWORD` w `.env.local`, następnie uruchom:

```bash
ddev magento-install
```

Polecenie tworzy schemat bazy i administratora, ustawia HTTPS, OpenSearch,
tryb developer, wykonuje indeksowanie i konfiguruje cron. Jest przeznaczone
do świeżej instalacji: odmawia działania, gdy istnieje `app/etc/env.php`.
Domyślny login administratora to `admin`. Hasło istniejącej lokalnej instalacji
zapisano w ignorowanym pliku `.env.local`.

W lokalnym środowisku wyłączone są `Magento_TwoFactorAuth` i
`Magento_AdminAdobeImsTwoFactorAuth`, zgodnie z quickstartem DDEV. Tej konfiguracji
nie należy przenosić na środowisko produkcyjne.

## Pakiety Ergonode

Źródła skopiowano z `vendivo-1/backend/packages` do tego repozytorium:

- `packages/ergonode`: 62 aktywne moduły, obejmujące podstawę integracji,
  mapowania, import, publikację, historię i panel administracyjny.
- `packages/packhauer/module-unit-attribute`: wymagany przez moduły atrybutów
  produktu po stronie importu i publikacji.
- `packages/packhauer/module-file-attribute`: wymagany przez import produktów.

Repozytorium Composer typu `path` wskazuje `packages/*/*` i ma ustawione
`symlink: true`. Linki w `vendor` prowadzą do kopii w tym projekcie.
Moduły wycofane do `backlog`, pozostałe pakiety Vendivo oraz konfiguracja,
klucze API i dane bazy źródłowego projektu nie są importowane.

Zwykłe zmiany kodu w `packages` są widoczne przez symlinki w `vendor`.
Po zmianie manifestów pakietów wykonaj:

```bash
ddev mutagen sync
ddev composer update 'ergonode/*' 'packhauer/*' --minimal-changes --no-interaction
ddev magento setup:upgrade
ddev magento setup:di:compile
ddev magento cache:flush
```

Przy odtwarzaniu projektu z istniejącego `composer.lock` wystarczy
`ddev composer install`. Połączenie z Ergonode należy skonfigurować w panelu
administracyjnym dla własnego środowiska; instalacja pakietów nie uruchamia
synchronizacji z instancją używaną w `vendivo-1`.

## Codzienna praca

```bash
ddev start
ddev magento cache:flush
ddev magento indexer:reindex
ddev magento setup:upgrade
ddev stop
```

DDEV uruchamia daemon cron i przy każdym starcie odtwarza crontab Magento
dla zainstalowanego projektu. Zadania Magento są uruchamiane co minutę.
Poczta trafia do Mailpit. Do podstawowej instalacji używane są plikowe cache
i sesje; usługi RabbitMQ i Valkey nie są wymagane w tej konfiguracji.

```bash
ddev describe
ddev composer check-platform-reqs
ddev magento setup:db:status
ddev magento indexer:status
ddev exec supervisorctl status
```

## Git

Remote `origin`: `git@github.com:packhauer/magento-ergonode.git`. Główna gałąź: `main`.
Wersje zależności są utrwalone w `composer.lock`; konfiguracja modułów w `app/etc/config.php`.
Konfiguracja DDEV i jawne wersje OpenSearch są częścią repozytorium.

`.gitignore` wyklucza zależności, `app/etc/env.php`, `auth.json`, `.env.local`,
media, cache, logi, wygenerowane pliki i zrzuty bazy. Lokalna baza danych oraz
hasło administratora nie są częścią repozytorium.
Biblioteki bazowe Magento oraz jego narzędzia i testy w `lib`, `setup`, `dev`
i `phpserver` są odtwarzane przez Composer i również wykluczone z Git.

Dokumentacja: [Magento w DDEV](https://docs.ddev.com/en/stable/users/quickstart/#magento-2),
[wymagania Magento](https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/system-requirements),
[dodatek OpenSearch](https://github.com/ddev/ddev-opensearch).
